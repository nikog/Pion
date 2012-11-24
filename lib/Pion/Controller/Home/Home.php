<?php
namespace Pion\Controller\Home;

class Home extends \Pion\Pion {
	function _get() {
		$this->args['id'] = 13;

        $news = $this->execute('News', 'latest', 'Module');
        $title = 'Home';
        $content = $this->render('home', array('news' => $news));
		
		return array('title' => $title, 'content' => $content);
	}
}