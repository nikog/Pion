<?php
namespace Pion;

class Pion
{
	protected $baseUri;
	protected $defaultTemplate;
	protected $args;
	protected $request;
	protected static $responseSent;
	private $controllerDir;
	private $tplDefaults;

	public function __construct($baseUri = '/', $args = array())
	{
		// Try to quess base URI
		$this->baseUri = str_replace(
			$_SERVER['DOCUMENT_ROOT'],
			'',
			dirname($_SERVER['SCRIPT_FILENAME'])
		);

		$this->args = $args;

		$this->controllerDir = 'Controller';

		$this->tplDefaults = array(
			'name' => $this->defaultTemplate,
			'ext' => 'php',
			'dir' => 'templates'
		);

		if (!in_array('mod_rewrite', apache_get_modules())) {
			$this->baseUri .= 'index.php';
		}
	}

	/**
	*	Application main logic
	*	Will parse supplied URIs and call the action of
	*	the matched URI. If no match is found, action assigned
	*	to 404 through set404Action will be executed.
	*
	*	@param array $uris Two-dimensional array containing
	*	all four HTTP methods as keys with arrays containing
	*	URIs assigned to those methods as values.
	*/
	public function run($uris = null)
	{
		if (is_null($uris)) {
			throw new \InvalidArgumentException('No URIs has been defined.');
		}

		if(!is_array($uris)) {
			throw new \InvalidArgumentException('Given URIs must be an array.');
		}

		$this->request['method'] = strtolower($_SERVER['REQUEST_METHOD']);

		if (!isset($uris[$this->request['method']])) {
			throw new \InvalidArgumentException(
				"No URIs assigned to HTTP method {$this->request['method']}.");
		}

		if(is_array($uris[key($uris)])) {
			$uris = $uris[$this->request['method']];
		}

		$controllerData = $this->route($uris);

		// Template defaults
		$templateData = $this->tplDefaults;
		if($controllerData && array_key_exists('template', $controllerData)) {
			// Merge and overwrite custom values to defaults
			$templateData = array_merge(
				$templateData,
				$controllerData['template']
			);
		}

		if(!$templateData['name']) {
			$controllerData = $controllerData['content'];
		}

		// Route was not found; handle 404
		if ($controllerData === false) {
			try {
				$controllerData = $this->execute($uris['404']);
			} catch (InvalidActionException $e) {
				// No action for 404 was found, use default 404 handling
				header("HTTP/1.1 404 Not Found");
				// Clear templateData
				$templateData = array_map(function() {}, $templateData);
			}
		}

		// Output
		$this->respond(
			$controllerData,
			$templateData['name'],
			$templateData['ext'],
			$templateData['dir']
		);
	}

	/**
	*	@param array $uris Contains the defined uris for
	*	currently used http method
	*	@return mixed Returns whatever the action returns. Without match
	*	returns false.
	*/
	private function route($uris)
	{
		$this->request['uri'] = strtok($_SERVER['REQUEST_URI'], '?');

		foreach ($uris as $pattern => $action) {
			$pattern = "@^" . $this->baseUri . $pattern . "/?$@";

			if (preg_match($pattern, $this->request['uri'], $matches)) {
				$this->setArgs($matches);
				return $this->execute($action);
			}
		}
		return false;
	}



	/**
	*	Attempts to execute the supplied action.
	*	Action can be either closure or a class.
	*
	*	BindTo()-method will be called on closures if available.
	*
	*	Class should reside in either default controller directory 'Controller'
	*	or the provided controllerDir.
	*
	*	PSR-0 should be used on class path and namespace.
	*
	*	@param mixed $action Closure or name of controller class
	*	@param string $controllerMethod Call this method instead of
	*		guessing it by method and uri arguments.
	*	@param string $controllerDir Directory of the controller
	*	@return mixed Returns the return value of the action.
	*/
	protected function execute(
		$action,
		$controllerMethod = null,
		$controllerDir = null
	) {
		// Action is a closure
		if (is_callable($action)) {
			if (method_exists($action, 'bindTo')) {
				$action->bindTo($this);
			}

			return call_user_func_array($action, $this->args);
		}

		// Search from default controller directory if not defined
		$controllerDir = isset($controllerDir) ?
			$controllerDir : $this->controllerDir;


		$class = "\\Pion\\{$controllerDir}\\{$action}\\{$action}";

		// Action is a defined controller
		if (class_exists($class, true)) {
			$controller = new $class($this->baseUri, $this->args);

			// Attempt to quess method according to rails CRUD methods
			$crudMethod = $this->getCrudMethod($this->request['method']);
			$httpMethod = "_{$this->request['method']}";

			if ($controllerMethod) {
				if(method_exists($controller, $controllerMethod)) {
					$controllerData = $controller->$controllerMethod();
				} else {
					throw new \BadMethodCallException(
						"Class '{$class}' does not support ".
						"method {$controllerMethod}."
					);
				}
			} elseif (method_exists($controller, $crudMethod)) {
				$controllerData = $controller->$crudMethod();
			} elseif (method_exists($controller, $httpMethod)) {
				$controllerData = $controller->$httpMethod();
			} else {
				throw new \BadMethodCallException(
					"Class '{$class}' does not support ".
					"methods {$crudMethod} or {$httpMethod}."
				);
			}

			return $controllerData;
		}
		throw new InvalidActionException("Executed action '{$action}' ".
			"is not a defined closure or controller.");
	}



	/**
	*	Check if any kind of response has been sent
	*	whether it's sent headers, respond() already called
	*	or 'global' output buffer having content.
	*/
	protected function isResponseSent()
	{
		if (headers_sent() || static::$responseSent || (ob_get_contents())) {
			return true;
		}
		return false;
	}



	/**
	*	Send response to client
	*	@param string $template Name of the template file
	*/
	public function respond(
		$controllerData,
		$template = null,
		$extension = null,
		$dirName = null
	) {
		if (!$this->isResponseSent()) {
			ob_start();
			if ($template) {
				echo $this->render(
					$template,
					$controllerData,
					$extension,
					$dirName
				);
			} else {
				echo $controllerData;
			}
			ob_end_flush();

			static::$responseSent = true;
		}

		//throw new ApplicationException("Response has already been sent.");
	}

	protected function redirect($path)
	{
		header("Location: {$this->baseUri}{$path}", true, 303);
		exit;
	}

	/**
	*	Attempts to quess and assign a Rails CRUD method:
	*	_show, _index, _delete, _update or _create
	*	@param string $method HTTP method used by client
	*/
	protected function getCrudMethod($method)
	{
		switch ($method) {
			case 'get':
				if(isset($this->args['id'])) {
					$method = '_show';
				} else {
					$method = '_index';
				}
				break;
			case 'post':
				if(isset($_POST['_METHOD'])) {
					if($_POST['_METHOD'] == 'delete') {
						$method = '_delete';
					} else if($_POST['_METHOD'] == 'put') {
						$method = '_update';
					}
				} else {
					$method = '_create';
				}
				break;
			case 'put':
				$method = '_update';
				break;
			case 'delete':
				$method = '_delete';
				break;
		}

		return $method;
	}

	/**
	*	Set arguments parsed from request URI
	*	@param array $args Parsed arguments
	*/
	public function setArgs($args)
	{
		$this->args = $args;
		return $this;
	}

	/**
	*	Set URI base/directory so you don't have to
	*	write it in each URI.
	*	@param string $base URI base directory
	*/
	public function setBaseUri($base)
	{
		$this->baseUri = $base;
		return $this;
	}

	public function setTemplateDefaults($defaults)
	{
		$this->tplDefaults = array_merge($this->tplDefaults, $defaults);
		return $this;
	}

	public function setDefaultControllerDir($dir)
	{
		$this->controllerDir = $dir;
		return $this;
	}

	/**
	*	Fetch view from {current_file_location}/views/{view}.{content_type}.php
	*	@param string $view Name of the view file
	*	@param string $ext Optional forced content type.
	*	Otherwise "Accept"-header will be used
	*	@return string
	*/
	public function render($view, $data = array(), $ext = null, $dir = 'views')
	{
		// Get directory of the class that called this method
		$reflector = new \ReflectionClass(get_class($this));
		$classDir = dirname($reflector->getFileName());

		$classPath = "{$classDir}/{$dir}/{$view}";

		if ($ext === null) {
			// Get filetype from Accept header
			$this->setContentTypes();

			foreach ($this->request['accept'] as $header => $ext) {
				if (is_file("{$classPath}.{$ext}.php")) {
					$includePath = "{$classPath}.{$ext}.php";
					break;
				} elseif (is_file("{$classPath}.{$ext}")) {
					$includePath = "{$classPath}.{$ext}";
					break;
				}
			}
		} else {
			if (is_file("{$classPath}.{$ext}.php")) {
				$includePath = "{$classPath}.{$ext}.php";
			} else {
				$includePath = "{$classPath}.{$ext}";
			}
		}


		if (isset($includePath)) {
			// Personal preference
			$data = (object) $data;

			ob_start();

			include $includePath;

			return ob_get_clean();
		}
		throw new FileNotFoundException("File '{$path}.{$ext}' was not found");
	}

	/**
	*	Set file extension preferences in order
	* 	from Accept header values.
	*/
	protected function setContentTypes()
	{
		// Some temporary examples
		$content_types = array(
			'text/html' => 'html',
			'application/json' => 'js',
			'application/xml' => 'xml',
		);

		$accept = $_SERVER['HTTP_ACCEPT'];

		$accept = explode(',', $accept);
		foreach ($accept as $key => $value) {
			$accept[$key] = explode(';', $accept[$key]);

			/*
			*	TODO:
			*	Parse q value and sort array.
			*/

			$type = $accept[$key][0];
			if (isset($content_types[$type])) {
				$this->request['accept'][$type] = $content_types[$type];
			}
		}
	}
}
class FileNotFoundException extends \LogicException {}
class InvalidActionException extends \LogicException {}