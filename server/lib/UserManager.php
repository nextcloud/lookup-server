<?php

namespace LookupServer;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class UserManager {

	/** @var \PDO */
	private $db;

	public function __construct(\PDO $db) {
		$this->db = $db;
	}

	public function search(Request $request, Response $response) {
		$response->getBody()->write("HELLLO HELLO");

		return $response;
	}

	public function register(Request $request, Response $response) {
		$response->getBody()->write("WTF DUDEs");

		$stmt = $this->db->prepare('select * from user');
		$stmt->execute();
		$rows = $stmt->rowCount();

		$response->getBody()->write($rows);

		$response->getBody()->write('OKE');


		return $response;
	}
}
