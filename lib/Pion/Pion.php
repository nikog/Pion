<?php
namespace Pion;

class Pion
{
	// Base of the URL after domain
	private static $baseUri;

	// Default base view/template
	private static $template;

	// Stuff from request ie. method, headers etc.
	private static $request;

	private $routes;

	// Arguments parsed from URL
	protected $args;

	// Data stored by magic setters. Respond() will use these contents.
	private $data = array();

	public function __construct(
		$args = null, 
		$baseUri = null,
		$template = 'index')
	{
		if($baseUri) {
			$this->baseUri = $baseUri;
		}

		if($args) {
			$this->args = $args;
		}

		// By default, look for application/views/index.php
		$this->template = 'index';

		return $this;
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
	public function run()
	{
		if (!isset($this->baseUri)) {
			// Try to quess base URI
			$this->baseUri = str_replace(
				$_SERVER['DOCUMENT_ROOT'],
				'',
				dirname($_SERVER['SCRIPT_FILENAME'])
			);

			if (!in_array('mod_rewrite', apache_get_modules())) {
				$this->baseUri .= 'index.php';
			}
		}

		$this->request['method'] = strtolower($_SERVER['REQUEST_METHOD']);
		$this->request['uri'] = strtok($_SERVER['REQUEST_URI'], '?');

		if (!isset($routes[$this->request['method']])) {
			throw new \InvalidArgumentException(
				"No routes assigned to HTTP method {$this->request['method']}.");
		}

		// Fetch action assigned to route
		$action = $this->route();

		// Route was not found; handle 404
		if ($action === false) {
			try {
				$response = $this->execute($routes['error']['404']);
			} catch (InvalidActionException $e) {
				header("HTTP/1.0 404 Not Found");
				return;
			}
		}

		$response = $this->execute($action);
	}




	/**
	*	@param string $uri Optional parameter for manual uri
	*	@return mixed Returns action assigned to the route, false otherwise.
	*/
	private function route($uri = null)
	{
		if(!is_null($uri)) {
			$this->request['uri'] = $uri;
		}

		$routes = $this->routes[$this->request['method']];

		foreach ($routes as $pattern => $action) {
			$pattern = "@^" . $this->baseUri . $pattern . "/?$@";

			if (preg_match($pattern, $this->request['uri'], $matches)) {
				$this->$args = $matches;
				return $action;
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
		$controllerDir = null)
	{
		// Action is a function
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

		$action = preg_replace('/', '\\', $action);
		$class = "\\{$controllerDir}\\{$action}";

		// Action is a defined controller
		if (class_exists($class, true)) {
			$controller = new $class($this->args, $this->baseUri);

			// Attempt to quess method according to rails CRUD methods
			$crudMethod = $this->getCrudMethod($this->request['method']);
			$httpMethod = "_{$this->request['method']}";

			// Check which method is available in order of 
			// given argument, rails crud method and httpmethod.

			if ($controllerMethod || !is_null(explode(':', $action)[1])) {
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
		} elseif (is_file("{$dir}/{$view}.php")) {
			$includePath = "{$dir}/{$view}.php";
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




	protected function redirect($path)
	{
		header("Location: {$this->baseUri}{$path}", true, 303);
		exit;
	}




	public static function response() 
	{
		return array('baseView' => $this->baseView, 'content' => $this->data);
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

	

	
	/**
	*	Attempts to quess and assign a Rails CRUD method:
	*	_show, _index, _delete, _update or _create
	*	@param string $method HTTP method used by client
	*/
	protected function getCrudMethod($method)
	{
		switch ($method) {
			case 'get':
				if (isset($this->args['id'])) {
					$method = '_show';
				} else {
					$method = '_index';
				}
				break;
			case 'post':
				if ($this->post('_METHOD') == 'delete') {
					$method = '_delete';
				} elseif ($this->post('_METHOD') == 'put') {
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

	public function getGet($name, $default = null) 
	{
		if (isset($_GET[$name])) {
			return $_GET[$name];
		} 
		return $default;
	}
	public function getPost($name, $default = null) 
	{
		if (isset($_POST[$name])) {
			return $_POST[$name];
		} 
		return $default;
	}
	public function __get($key, $default = null)
	{
		if(isset($this->data[$key])) {
			return $this->data[$key];
		}
		return $default;
	}
	public function __set($key, $value)
	{
		$this->data[$key] = $value;
	}

	public function __call($method, $args)
	{
		if (!in_array($method, ['get', 'post', 'put', 'delete', 'error'])) {
			return;
		}
		$this->routes[$method][$args[0]] = $args[1];
		return $this;
	}
}
class FileNotFoundException extends \LogicException {}
class InvalidActionException extends \LogicException {}