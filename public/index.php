<?php

use Pion\Pion;

require_once __DIR__ . '/../bootstrap.php';

$app = new Pion();

$getUris = array(
	// Matches "demo/one(/two)(/3)"
	"/demo/(?P<first>\w+)(?:/(?P<second>\w+))?(?:/(?P<third>\d+))?" =>
	function() use ($app) {
		echo '<pre>';
		print_r($this->args);
		echo '</pre>';
	},
	"/product(?:/page/(?P<page>\d+))?" => "Product",
	"/product(?:/(?P<id>\d+))?" => "Product",
	"/home" => 'Home',
	"/test" => function() use ($app) {
		echo $app->render('template', null, 'html', 'templates');
	},
	"/json" => function() use ($app) {
		$json = json_encode(array('hello' => 'world'));

		return array(
			// Set template to none
			'template' => array('name' => null),
			'content' => $json);

		//$app->respond(json_encode(array('hello' => 'world')));
	},
	"/errortest" => 'doesntexist',
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

$app->setTemplateDefaults(array('name' => 'index'))
	->run($uris, $filters);