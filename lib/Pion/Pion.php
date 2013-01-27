<?php
namespace Pion;

class Pion
{
	protected $baseUri;
	protected $baseView;
	protected $applicationDirectory;
	protected $args;
	protected $request;
	protected static $responseSent;
	private $controllerDir;
	private $data = array();

	public function __construct($baseUri = '/', $args = array())
	{
		// Try to quess base URI
		$this->baseUri = str_replace(
			$_SERVER['DOCUMENT_ROOT'],
			'',
			dirname($_SERVER['SCRIPT_FILENAME'])
		);

		$this->args = $args;

		// By default, look for Controllers from Controller/ namespace
		$this->controllerDir = 'Controller';

		// By default, look for application/views/index.php
		$this->baseView = 'index';

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
			throw new \InvalidArgumentException('Given URIs must be in array.');
		}

		$this->request['method'] = strtolower($_SERVER['REQUEST_METHOD']);

		if (!isset($uris[$this->request['method']])) {
			throw new \InvalidArgumentException(
				"No URIs assigned to HTTP method {$this->request['method']}.");
		}

		$uris = $uris[$this->request['method']];

		// Route query and fetch response
		$response = $this->route($uris);

		// Route was not found; handle 404
		if ($response === false) {
			try {
				$response = $this->execute($uris['404']);
			} catch (InvalidActionException $e) {
				// No action for 404 was found, send plain 404
				header("HTTP/1.1 404 Not Found");

				return;
			}
		}

		// Output
		$this->respond($response);
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
		if(!isset($controllerDir)) {
			$controllerDir = $this->controllerDir;
		}

		$class = "\\{$controllerDir}\\{$action}\\{$action}";

		// Action is a defined controller
		if (class_exists($class, true)) {
			$controller = new $class($this->baseUri, $this->args);

			// Attempt to quess method according to rails CRUD methods
			$crudMethod = $this->getCrudMethod($this->request['method']);
			$httpMethod = "_{$this->request['method']}";

			if ($controllerMethod) {
				// Method to call was given as argument

				if(method_exists($controller, $controllerMethod)) {
					$response = $controller->$controllerMethod();
				} else {
					throw new \BadMethodCallException(
						"Class '{$class}' does not support ".
						"method {$controllerMethod}."
					);
				}

			} elseif (method_exists($controller, $crudMethod)) {
				$response = $controller->$crudMethod();

			} elseif (method_exists($controller, $httpMethod)) {
				$response = $controller->$httpMethod();

			} else {
				throw new \BadMethodCallException(
					"Class '{$class}' does not support ".
					"methods {$crudMethod} or {$httpMethod}."
				);
			}

			return $response;
		}
		throw new InvalidActionException("Executed action '{$action}' ".
			"is not a defined closure or controller.");
	}

	/**
	*	Send response to client
	*	@param string $response Array containing baseView and content
	*/
	private function respond($response)
	{
		ob_start();
		if ($response['baseView']) {
			echo $this->view($response['baseView'], $response['content']);
		} else {
			echo $response;
		}
		ob_end_flush();
	}

	public static function response() 
	{
		return array('baseView' => $this->baseView, 'content' => $this->data;
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
				if($this->post('_METHOD') == 'delete') {
					$method = '_delete';
				} elseif($this->post('_METHOD') == 'put') {
					$method = '_update';
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

	public function setBaseView($baseView)
	{
		$this->baseView = $baseView;
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
	public function view($view, $data = array(), $dir = 'views')
	{
		// Get directory of the class that called this method
		$reflector = new \ReflectionClass(get_class($this));
		$classDir = dirname($reflector->getFileName());

		$classPath = "{$classDir}/{$dir}/{$view}";

		if (is_file("{$classPath}.php")) {
			$includePath = "{$classPath}.php";
		} elseif (is_file("{$this->applicationDirectory}/{$dir}/{$view}.php")) {
			$includePath = "{$this->applicationDirectory}/{$dir}/{$view}.php";
		}

		if (isset($includePath)) {
			// Personal preference
			$data = (object) $data;

			ob_start();

			include $includePath;

			return ob_get_clean();
		}
		throw new FileNotFoundException("File '{$path}.php' was not found");
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

	public function get($name, $default = null) 
	{
		if (isset($_GET[$name])) {
			return $_GET[$name];
		} 
		return $default;
	}
	public function post($name, $default = null) 
	{
		if (isset($_POST[$name])) {
			return $_POST[$name];
		} 
		return $default;
	}

	public function __get($key, $default = null) {
		if(isset($this->data[$key])) {
			return $this->data[$key];
		}
		return $default;
	}
	public function __set($key, $value) {
		$this->data[$key] = $value;
	}
}
class FileNotFoundException extends \LogicException {}
class InvalidActionException extends \LogicException {}