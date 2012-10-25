<?php
namespace Pion\Utility;

class Model {
	private $db = 'testdb';
	private $host = 'localhost';
	private $user = 'root';
	private $pass = '';

	private $pdo;

	function __construct() {
		$this->pdo = new \PDO("mysql:dbname={$db};host={$host}", $user, $pass);
	}

	function query($query, $data = false) {
		$stmt = $this->pdo->prepare($query);
		if($data) {
			foreach($data as $key => $value) {
				$key += 1;
				if(is_numeric($value)) {
					$stmt->bindValue(":{$key}", $value, \PDO::PARAM_INT);
				} else {
					$stmt->bindValue(":{$key}", $value);
				}
			}
		}
		$stmt->execute();

		return $stmt->fetchAll();
	}

	function create($data = array(), $table) {
		$columns = implode(', ', array_keys($data));
		$variables = implode(', :', array_keys($data));

		$query = "INSERT INTO {$table} ({$columns}) VALUES (:{$variables})";

		$this->query($query, $data);
	}

	function read($id, $table) {
		return $this->query("SELECT * FROM {$table} WHERE id = :id", array('id' => $id));
	}

	function update($data = array(), $table) {
		$querySet = '';
		foreach($data as $row) {
			array_push($querySet, "{$row} = :{$row}");
		}
		$setString = implode(', ', $querySet);

		$query = "UPDATE {$table} SET {$setString} WHERE id = :id";

		$this->query($query, $data);
	}

	function delete($id, $table) {
		return $this->query("SELECT * FROM {$table} WHERE id = :id", array('id' => $id));
	}
}