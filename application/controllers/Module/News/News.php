<?php
namespace Pion\Module\News;

class News extends \Pion\Pion {
	function latest() {
		$content = $this->render('latest');

        return $content;
	}
}