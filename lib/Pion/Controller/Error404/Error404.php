<?php
namespace Pion\Controller\Error404;

class Error404 extends \Pion\Pion {
	function _get() {
		$this->set('title', 'Not Found');
		$this->set('content', $this->loadView('404'));
	}
}