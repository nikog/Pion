<?php
namespace Pion\Controller\Error404;

class Error404 extends \Pion\Pion {
	function _get() {
		$this->data['title'] = 'Not Found';
		$this->data['content'] = $this->loadView('404');
	}
}