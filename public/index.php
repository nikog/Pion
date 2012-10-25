<?php
require_once __DIR__ . '/../bootstrap.php';

class Application extends \Pion\Pion {
	function _argdemo() {
		echo '<pre>';
		print_r($this->args);
		echo '</pre>';
	}
}

$getUris = array(
	// Matches "demo/one(/two)(/3)"
	"/demo/(?P<first>\w+)(?:/(?P<second>\w+))?(?:/(?P<third>\d+))?" => '_argdemo',
	"/product(?:/page/(?P<page>\d+))?" => "Product",
	"/product(?:/(?P<id>\d+))?" => "Product",
	"/home" => 'Home',
	"/test" => function() {
		phpinfo();
	},
	);

$postUris = array(
	"/post" => function() {
		echo '<pre>';
		var_dump($_POST);
		echo '</pre>';
	},
	);

$uris = array(
	'get' => $getUris,
	'post' => $postUris,
	'put' => null,
	'delete' => null
	);

$app = new Application();
$app->setDefaultTemplate('index')
	->set404Action('Error404')
	->run($uris, $filters);