<?php
/**
*	For using the controller through another controller,
*	consider using return $this instead of $this->respond().
*	Response will be sent automaticly at the end of run() if
*	no response is sent yet.
*/
namespace Controller\Product;

use \Pion\Utility;

class Product extends \Pion\Pion {
	function _index() {
		$searchParams = $_GET['search'];
		$this->getProducts($searchParams);

		$title = 'Product list';
		$content = $this->render('productList');

		return array($title, $content);
	}

	function _show() {
		$product = $this->getProduct($this->args['id']);

		$title = 'The Product';
		$content = $this->render('product');

		return array($title, $content);
	}

	function getProducts($searchParams) {
		/**
		*	TODO:
		*	Get product from database
		*/
	}

	function getProduct($id) {
		/**
		*	TODO:
		*	Get product from database
		*/
		return array(
			'id' => $id,
			'name' => 'Banana'
			);
	}
}