<?php
namespace Controller\Home;

class Home extends \Pion\Pion {
    function _get() {
        $this->args['id'] = 13;

        $news = $this->execute('News', 'latest', 'Module');

        // Optional, index is already default
        $this->setBaseView('index');

        $this->title = 'Home';
        $this->content = $this->view('home', array('news' => $news));

        return $this->response();
    }
}