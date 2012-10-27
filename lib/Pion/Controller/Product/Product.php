<?php
/**
*	For using the controller through another controller,
*	consider using return $this instead of $this->respond().
*	Response will be sent automaticly at the end of run() if
*	no response is sent yet.
*/
namespace Pion\Controller\Product;

use \Pion\Utility;

class Product extends \Pion\Pion {
	function _index() {
		$searchParams = $_GET['search'];
		$this->getProducts($searchParams);

		$this->set('title', 'Product list');
		$this->set('content', $this->loadView('productList'));
	}

	function _show() {
		$this->set('product', $this->getProduct($this->args['id']));

		$this->set('title', 'The Product');
		$this->set('content', $this->loadView('product'));

		return $this;
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