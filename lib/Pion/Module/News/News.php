<?php
namespace Pion\Module\News;

class News extends \Pion\Pion {
	function latest() {
		$this->set('content', $this->loadView('latest'));
	}
}