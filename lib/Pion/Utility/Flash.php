<?php
namespace Pion\Utility;

class Flash {
	function setFlash($flash) {
		$_SESSION['flash'] = $flash;
	}
	function getFlash($message) {
		if(isset($_SESSION['flash'])) {
			$flash = $_SESSION['flash'];
			unset($_SESSION['flash']);
			return $flash;
		}
		return false;
	}
}