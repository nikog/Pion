<?php
namespace Pion;

class Pion {
	/**
	*	@var string Base path of URI
	*/
	protected $baseUri;

	/**
	*	@var string Default template used
	*/
	protected $defaultTemplate;

	/**
	*	@var array Parsed arguments from URI
	*/
	protected $args;

	/**
	*	@var array Data usable by views and templates through get($key)
	*/
	private $viewData;

	/**
	*	@var array Contains request data such as uri, method, headers.
	*/
	protected $request;

	/**
	*	@var boolean Tells if response has already been sent manually
	*/
	protected static $responseSent;

	private $controllerDir;



	public function __construct($baseUri = '/') {
		// Try to quess base URI
		$this->baseUri = str_replace(
			$_SERVER['DOCUMENT_ROOT'], '', dirname($_SERVER['SCRIPT_FILENAME']));

		$this->args = array();
		$this->viewData = array();

		$this->controllerDir = 'Controller';

		if(!in_array('mod_rewrite', apache_get_modules()))
			$this->baseUri .= 'index.php';
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
	public function run($uris = null, $filter) {
		$this->request['method'] = strtolower($_SERVER['REQUEST_METHOD']);

		if(is_null($uris)) {
			throw new ApplicationException('No URIs has been defined.');
		} else if(!isset($uris[$this->request['method']])) {
			throw new ApplicationException("No URIs assigned to HTTP method {$this->request['method']}.");
		}

		// Discard uris assigned to other methods for now
		$uris = $uris[$this->request['method']];
		/*
		* Attempt to route. After successful routing, send response
		* if no response has been sent yet.
		* Handle 404 error accordingly if no matching URI is found.
		*/
		if(!$this->route($uris)) {
			$this->handle404();
		}
		if(!$this->isResponseSent()) {
			if($this->ctrl) $this->viewData = $this->ctrl->viewData;
			$this->respond($this->defaultTemplate);
		}
	}

	/**
	*	@param array $uris Contains the defined uris for currently used http method
	*	@return boolean True on URI match. False if no match.
	*/
	private function route($uris) {
		$this->request['uri'] = strtok($_SERVER['REQUEST_URI'], '?');

		foreach($uris as $pattern => $action) {
			$pattern = $this->baseUri . $pattern;

			if(preg_match("@^".$pattern."/?$@", $this->request['uri'], $matches)) {
				$this->setArgs($matches);

				if(!$this->executeAction($action)) {
					throw new ApplicationException("Assigned action '{$action}' ".
						"for URI '{$pattern} was not found to be a defined ".
						"closure, class or class method.");
				}
				return true;
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
	*	or the provided controllerDir. PSR-0 should be used on class path and namespace.
	*
	*	@param mixed $action Closure or name of controller class
	*	@param string $controllerMethod Call this method instead of guessing it by 
	*		method and uri arguments.
	*	@param string $controllerDir Directory of the controller
	*	@return mixed If executed action returns something, return that.
	*		If the action could be executed but did not return anything,
	*		return true. False will be returned if no action is found.
	*/
	protected function executeAction($action, $controllerMethod = null, $controllerDir = null) {
		// Action is a closure
		if(is_callable($action)) {
			if(method_exists($action, 'bindTo'))
				$action->bindTo($this);

			$out = call_user_func_array($action, $this->args);
			return $out === null ? true : $out;
		}

		// Action is a defined controller
		if($controllerDir === null)
			$controllerDir = $this->controllerDir;
		if(class_exists("\\Pion\\{$controllerDir}\\{$action}\\{$action}", true)) {
			$class = "\\Pion\\{$controllerDir}\\{$action}\\{$action}";

			$controller = new $class($this->baseUri);
			$controller->setArgs($this->args);

			// Attempt to quess method according to rails CRUD methods
			$crudMethod = $this->getCrudMethod($this->request['method']);
			$httpMethod = "_{$this->request['method']}";

			if($controllerMethod) {
				$out = $controller->$controllerMethod();
			} else if(method_exists($controller, $crudMethod)) {
				$out = $controller->$crudMethod();
			} else if(method_exists($controller, $httpMethod)) {
				$out = $controller->$httpMethod();
			} else {
				throw new ApplicationException(
					"Class {$action} doesn't support ".
					"methods {$crudMethod} or {$httpMethod}.");
			}
			$this->ctrl = $controller;
			return $out === null ? true : $out;
		}
		return false;
	}

	/**
	*	Execute action defined through set404Action()
	*	Otherwise response body will be empty
	*/
	protected function handle404() {
		header("HTTP/1.1 404 Not Found");
		if(isset($this->_404)) {
			$this->executeAction($this->_404);
		}
	}

	/**
	*	Check if any kind of response has been sent
	*	whether it's sent headers, respond() already called
	*	or 'global' output buffer having content.
	*/
	protected function isResponseSent() {
		if(headers_sent() || static::$responseSent || 
				(ob_get_contents())) {
			return true;
		}
		return false;
	}

	/**
	*	Send response to client
	*	@param string $template Name of the template file
	*/
	protected function respond($template = null) {
		if(!static::$responseSent) {
			$data = (object) $this->viewData;

			ob_start();
			if($template) {
				echo $this->loadView($template, 'php', 'templates');
				//include __DIR__."/templates/{$template}.php";
			} else {
				echo $data->content;
			}
			ob_end_flush();

			static::$responseSent = true;
		} else {
			throw new ApplicationException("Response has already been sent.");
		}
	}

	protected function redirect($path) {
		header("Location: {$this->baseUri}{$path}", true, 303);
		exit;
	}

	/**
	*	Attempts to quess and assign a Rails CRUD method:
	*	_show, _index, _delete, _update or _create
	*	@param string $method HTTP method used by client
	*/
	protected function getCrudMethod($method) {
		switch($method) {
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
	public function setArgs($args) {
		$this->args = $args;
		return $this;
	}
	/**
	*	Set URI base/directory so you don't have to
	*	write it in each URI.
	*	@param string $base URI base directory
	*/
	public function setBaseUri($base) {
		$this->baseUri = $base;
		return $this;
	}
	/**
	*
	*/
	public function setDefaultTemplate($tpl) {
		$this->defaultTemplate = $tpl;
		return $this;
	}
	public function setDefaultControllerDir($dir) {
		$this->controllerDir = $dir;
		return $this;
	}
	/**
	*	Assign action to execute when no URI is matched
	*	@param mixed $action Closure, class name or class method
	*/
	public function set404Action($action) {
		$this->_404 = $action;
		return $this;
	}

	/**
	*	Fetch view from {current_file_location}/views/{view}.{content_type}.php
	*	@param string $view Name of the view file
	*	@param string $ext Optional forced content type. Otherwise "Accept"-header will be used
	*	@return string
	*/
	public function loadView($view, $type = null, $tplDir = 'views') {
		$callers = debug_backtrace();

		$class = (isset($callers[1]['class'])) ? $callers[1]['class'] : get_class($this);

		// Get directory of this class or the class that extends this class
		$reflector = new \ReflectionClass($class);
		$classDir = dirname($reflector->getFileName());

		$path = "{$classDir}/{$tplDir}/{$view}";

		if($type === null) {
			// Get filetype from Accept header
			$this->setContentTypes();

			foreach ($this->request['accept'] as $header => $type) {
				if (is_file("{$path}.{$type}.php")) {
					$includePath = "{$path}.{$type}.php";
					break;
				} else if (is_file("{$path}.{$type}")) {
					$includePath = "{$path}.{$type}";
					break;
				}
			}
		} else {
			if(is_file("{$path}.{$type}.php")) {
				$includePath = "{$path}.{$type}.php";
			} else {
				$includePath = "{$path}.{$type}";
			}
		}

		
		if(isset($includePath)) {
			$data = (object) $this->viewData;

			ob_start();

			include $includePath;

			return ob_get_clean();
		}
		throw new ApplicationException("File '{$path}.{$type}' was not found");
	}

	protected function set($key, $data) {
		$this->viewData[$key] = $data;
	}
	protected function get($key, $default = null) {
		return isset($this->viewData[$key]) ? $this->viewData[$key] : $default;
	}

	/**
	*	Set file extension preferences in order
	* 	from Accept header values.
	*/
	protected function setContentTypes() {
		// Some temporary examples
		$content_types = array(
			'text/html' => 'html',
			'application/json' => 'js',
			'application/xml' => 'xml',			
		);

		$accept = $_SERVER['HTTP_ACCEPT'];

		$accept = explode(',', $accept);
		foreach($accept as $key => $value) {
			$accept[$key] = explode(';', $accept[$key]);

			/*
			*	TODO:
			*	Parse q value and sort array.
			*/

			$type = $accept[$key][0];
			if(isset($content_types[$type])) {
				$this->request['accept'][$type] = $content_types[$type];
			}
		}
	}

	protected function asset($asset) {
		return "{$this->baseUri}/assets/{$asset}";
	}
}
class ApplicationException extends \Exception { }