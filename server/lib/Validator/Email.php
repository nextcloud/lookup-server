<?php

declare(strict_types=1);
/**
 * lookup-server - Standalone Lookup Server.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Maxence Lange <maxence@artificial-owl.com>
 *
 * @copyright 2017
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
