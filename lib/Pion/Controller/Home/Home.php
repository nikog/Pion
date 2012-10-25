<?php
namespace Pion\Controller\Home;

class Home extends \Pion\Pion {
	function _get() {
		$this->args['id'] = 13;
		
		$this->data['product'] = $this->executeAction('Product', '_show');
		$this->data['title'] = 'Home';
		$this->data['content'] = $this->loadView('home');
	}
}