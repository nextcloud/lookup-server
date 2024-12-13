<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace LookupServer\Validator;

use Exception;
use LookupServer\Service\SecurityService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\RouteParserInterface;
use Slim\Routing\Route;

class Email {
	public function __construct(
		private PDO $db,
		private RouteParserInterface $routeParser,
		private string $host,
		private string $from,
		private SecurityService $securityService,
	) {
	}

	public function validate(Request $request, Response $response, array $args = []): Response {
		/** @var Route $route */
		$route = $request->getAttribute('route');
		$token = $route->getArgument('token');

		$stmt = $this->db->prepare('SELECT * FROM emailValidation WHERE token = :token');
		$stmt->bindParam(':token', $token);
		$stmt->execute();
		$validation = $stmt->fetch();
		$stmt->closeCursor();

		if ($validation === false) {
			return $response->withStatus(403, 'Invalid token');
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

	public function emailUpdated(string $email, int $storeId): void {
		if ($this->securityService->isGlobalScale()) {
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
		$link = $this->host . $this->routeParser->urlFor('validateEmail', ['token' => $token]);
		$text = 'Please click this link to confirm your e-mail address: ' . $link;

		$headers = 'From: ' . $this->from . "\r\n" . 'X-Mailer: PHP/' . phpversion();
		mail($email, 'Email confirmation', $text, $headers);
	}


	/**
	 * @param int $length
	 * @param string $characters
	 *
	 * @return string
	 * @throws Exception
	 */
	private function generate(
		int $length,
		string $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
	): string {
		$maxCharIndex = strlen($characters) - 1;
		$randomString = '';

		while ($length > 0) {
			$randomNumber = random_int(0, $maxCharIndex);
			$randomString .= $characters[$randomNumber];
			$length--;
		}

		return $randomString;
	}
}
