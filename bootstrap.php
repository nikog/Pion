<?php
require 'vendor/autoload.php';

use Pion\Pion;

$app = new Pion():

$app->get("/demo/(?P<first>\w+)(?:/(?P<second>\w+))?(?:/(?P<third>\d+))?", 
        function() {
            echo '<pre>';
            print_r($this->args);
            echo '</pre>';
        })
    ->match("/product(?:/page/(?P<page>\d+))?", "Product/Product")
    ->get("/product(?:/(?P<id>\d+))?", "Product/Product:_index")
    ->get("/errortest", "doesntexist")
    // This would require PHP >= 5.4 by using bind()
    ->get("/pagetest", 
        function() {
            $this->content = $this->view('pages.page');
            return $this->response();
        })
    // This would not
    ->get("/pagetest2", 
        function() use ($app) {
            $app->content = $this->view('pages.page');
            return $app->response();
        })
    ->get("/", "Home")
    ->error("404", "Error/Error404")
    ->post("/post", 
        function() {
            echo '<pre>';
            var_dump($_POST);
            echo '</pre>';
        }));

return $app;