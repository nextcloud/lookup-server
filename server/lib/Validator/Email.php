<?php

namespace LookupServer\Validator;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Email {

	/** @var \PDO */
	private $db;

	/** @var \Slim\Interfaces\RouterInterface */
	private $router;

	/** @var string */
	private $host;

	/** @var string */
	private $from;

	/** @var bool */
	private $globalScale;

	/**
	 * Email constructor.
	 * @param \PDO $db
	 * @param \Slim\Interfaces\RouterInterface $router
	 * @param string $host
	 * @param string $from
	 * @param bool $globalScale
	 */
	public function __construct(\PDO $db,
								\Slim\Interfaces\RouterInterface $router,
								$host,
								$from,
								$globalScale) {
		$this->db = $db;
		$this->router = $router;
		$this->host = $host;
		$this->from = $from;
		$this->globalScale = $globalScale;
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
			$stmt = $this->db->prepare('UPDATE store SET valid = 1 WHERE id = :storeId');
			$stmt->bindParam('storeId', $validation['storeId']);
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
		if ($this->globalScale) {
			// When in global scale mode we should not send e-mails
			return;
		}

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
		$link = $this->host . $this->router->pathFor('validateEmail', ['token' => $token]);
		$text = 'Please click this link to confirm your e-mail address: ' . $link;

		$headers = 'From: '.$this->from."\r\n" .'X-Mailer: PHP/' . phpversion();
		mail($email, 'Email confirmation', $text, $headers);
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
