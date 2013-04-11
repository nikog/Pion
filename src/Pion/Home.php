<?php
namespace \Pion\Controller\Home;

class Home extends \Pion\Pion {
    function _get() {
        $news = $this->execute('News:latest');

        // Optional, index is already default
        $this->setTemplate('index');

        // Variable names are up to user
        $this->title = 'Home';
        $this->content = $this->view('home', array('news' => $news));

        return $this->response();
    }
}