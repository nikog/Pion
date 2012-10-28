<?php
namespace Pion\Controller\Home;

class Home extends \Pion\Pion {
	function _get() {
		$this->args['id'] = 13;
		
		$this->set('newsObj', $this->executeAction('News', 'latest', 'Module'));
		$this->set('title', 'Home');
		$this->set('content', $this->loadView('home'));
	}
}