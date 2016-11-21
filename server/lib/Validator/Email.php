<?php

namespace LookupServer\Validator;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Email {

	/** @var \PDO */
	private $db;

	/**
	 * Email constructor.
	 * @param \PDO $db
	 */
	public function __construct(\PDO $db) {
		$this->db = $db;
	}

	public function validate(Request $request, Response $response) {
		/** @var $route \Slim\Route */
		$route = $request->getAttribute('route');
		$token = $route->getArgument('token');

		$stmt = $this->db->prepare('SELECT * FROM emailValidation WHERE token = :token');
		$stmt->bindParam(':token', $token);
		$stmt->execute();
		$validation = $stmt->fetch();
		$stmt->closeCursor();

		if ($validation === false) {
			$response->withStatus(403);
			$response->getBody()->write('Invalid token');
		} else {
			$stmt = $this->db->prepare('UPDATE store SET valid = 1');
			$stmt->execute();
			$stmt->closeCursor();

			$stmt = $this->db->prepare('DELETE FROM emailValidation WHERE id = :id');
			$stmt->bindParam(':id', $validation['id']);
			$stmt->execute();
			$stmt->closeCursor();

			$response->getBody()->write('Email verified');
		}

		return $response;
	}

	public function emailUpdated($email, $storeId) {
		// Delete old tokens
		$stmt = $this->db->prepare('DELETE FROM emailValidation WHERE storeId = :storeId');
		$stmt->bindParam(':storeId', $storeId);
		$stmt->execute();
		$stmt->closeCursor();

		// Generate Token
		$token = $this->generate(16);

		// Insert token
		$stmt = $this->db->prepare('INSERT INTO emailValidation (storeId, token) VALUES (:storeId, :token)');
		$stmt->bindParam(':storeId', $storeId);
		$stmt->bindParam(':token', $token);
		$stmt->execute();
		$stmt->closeCursor();

		// Actually send e-mail
		$text = 'Please click this link to ';

	}

	private function generate($length,
							 $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789') {
		$maxCharIndex = strlen($characters) - 1;
		$randomString = '';

		while($length > 0) {
			$randomNumber = \random_int(0, $maxCharIndex);
			$randomString .= $characters[$randomNumber];
			$length--;
		}
		return $randomString;
	}
}
