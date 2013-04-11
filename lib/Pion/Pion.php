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
		$template = 'index',
		$args = null, 
		$baseUri = null)
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
		$this->request['method'] = strtolower($_SERVER['REQUEST_METHOD']);

		// strtok to strip parameters
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
				// Default 404 handling
				header("HTTP/1.0 404 Not Found");
				return;
			}
		}

		$response = $this->execute($action);

		if($response) {
			$this->respond($response);
		}
	}




	/**
	*	@param string $uri Optional parameter for manual request uri
	*	@return mixed Returns action assigned to the route, false otherwise.
	*/
	private function route($uri = null)
	{
		if(!is_null($uri)) {
			$this->request['uri'] = $uri;
		}

		$routes = $this->routes[$this->request['method']];
		$routes = array_merge($routes, $this->routes['match']);

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
	*	@return mixed Returns the return value of the action.
	*/
	protected function execute($action)
	{
		// Action is a function
		if (is_callable($action)) {
			if (method_exists($action, 'bindTo')) {
				$action->bindTo($this);
			}

			return call_user_func_array($action, $this->args);
		}

		// Action can be in shape of 'directory/class'
		$action = preg_replace('/', '\\', $action);
		$class = "\\{$controllerDir}\\{$action}";

		// Action is a defined controller
		if (class_exists($class, true)) {
			$controller = new $class($this->template, $this->args, $this->baseUri);

			$crudMethod = $this->getCrudMethod($this->request['method']);
			$httpMethod = "_{$this->request['method']}";

			// Check which method is available in order of 
			// given argument, rails crud method and httpmethod.

			// Action can be in form of 'class:method'
			if (!is_null(explode(':', $action)[1])) {
				$controllerMethod = explode(':', $action)[1];

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
	public function view($view, $data = array())
	{
		$viewPath = "{$dir}/{$view}.php";

		if (is_file($viewPath) {
			ob_start();
			include $viewPath;
			return ob_get_clean();
		}

		throw new FileNotFoundException("File '{$view}.php' was not found");
	}

	public static function response() 
	{
		return array('template' => $this->template, 'content' => $this->data);
	}

	/**
	*	Send response to client
	*	@param string $response Array containing template and content
	*/
	private function respond($response)
	{
		ob_start();
		if ($response['template']) {
			echo $this->view($response['template'], $response['content']);
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

	public function setTemplate($template)
	{
		$this->template = $template;
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

	// Getter for view values
	public function __get($key, $default = null)
	{
		if(isset($this->data[$key])) {
			return $this->data[$key];
		}
		return $default;
	}

	// Setter for view values
	public function __set($key, $value)
	{
		$this->data[$key] = $value;
	}

	// Nicer method names to add urls
	public function __call($method, $args)
	{
		if (!in_array(
				$method, 
				['get', 'post', 'put', 'delete', 'error', 'match']
				)) {
			return;
		}
		$this->routes[$method][$args[0]] = $args[1];
		return $this;
	}
}
class FileNotFoundException extends \LogicException {}
class InvalidActionException extends \LogicException {}