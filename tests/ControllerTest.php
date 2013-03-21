<?php
require_once dirname(__FILE__).'/../classes/Controller.php';

class ControllerTest extends PHPUnit_Framework_TestCase 
{
	function setUp() 
	{
		$this->ctrl = new Pion\Controller();
		$this->ctrl->setBaseUri('/operator');

		$_SERVER['REQUEST_URI'] = '/operator/testuri';
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}

	function testCanCreateController() 
	{
		$this->assertEquals('Pion\Controller', get_class($this->ctrl));
	}

	/**
	* @expectedException Pion\ApplicationException
	*/
	function testExceptionIfNoURIsDefined() 
	{
		$this->ctrl->run();
	}

	/**
	* @expectedException Pion\ApplicationException
	*/
	function testExceptionIfNoURIsDefinedForUsedMethod() {
		$this->ctrl->run(
			array('post' => 
				array('/' => function() {})
			)
		);
	}

	function testCanRoute() {
		$uris = array(
			'/testuri' => function() {
				echo 'Testing.'.PHP_EOL;
			},
		);

		ob_start();

		$this->ctrl->run(array(
			'get' => $uris,
		));

		$content = ob_get_clean();

		$this->assertEquals('Testing.'.PHP_EOL, $content);
	}

}