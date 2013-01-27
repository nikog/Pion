<?php
namespace Controller\Error404;

class Error404 extends \Pion\Pion {
	function _get() {
		$title = '404 Not Found';
		$content = $this->render('404');
	}
}