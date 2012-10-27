<?php
namespace Pion\Controller\Home;

class Home extends \Pion\Pion {
	function _get() {
		$this->args['id'] = 13;
		
		$this->set('product', $this->executeAction('Product', '_show'));
		$this->set('title', 'Home');
		$this->set('content', $this->loadView('home'));
	}
}