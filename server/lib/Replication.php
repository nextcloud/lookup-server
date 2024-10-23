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

namespace LookupServer;

use GuzzleHttp\Client;
use LookupServer\Service\SecurityService;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class Replication {

	public function __construct(
		private PDO $db,
		private SecurityService $securityService,
		private array $replicationHosts) {
	}


	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @param array $args
	 *
	 * @return ResponseInterface
	 */
	public function export(Request $request, Response $response, array $args = []): Response {
		$userInfo = $request->getUri()->getUserInfo();

		$userInfo = explode(':', $userInfo, 2);

		if (count($userInfo) !== 2 || $userInfo[0] !== 'lookup' || !$this->securityService->isValidReplicationAuth($userInfo[1])) {
			return $response->withStatus(401);
		}

		$params = $request->getQueryParams();
		if (!isset($params['timestamp'], $params['page']) || !ctype_digit($params['timestamp'])
			|| !ctype_digit($params['page'])) {
			return $response->withStatus(400);
		}

		$timestamp = (int)$params['timestamp'];
		$page = (int)$params['page'];

		$stmt = $this->db->prepare(
			'SELECT id, federationId, UNIX_TIMESTAMP(timestamp) AS timestamp
			FROM users 
			WHERE UNIX_TIMESTAMP(timestamp) >= :timestamp
			ORDER BY timestamp, id
			LIMIT :limit
			OFFSET :offset'
		);
		$stmt->bindParam('timestamp', $timestamp);
		$stmt->bindValue('limit', 100, PDO::PARAM_INT);
		$stmt->bindValue('offset', 100 * $page, PDO::PARAM_INT);

		$stmt->execute();

		$result = [];
		while ($data = $stmt->fetch()) {
			$user = [
				'cloudId' => $data['federationId'],
				'timestamp' => (int)$data['timestamp'],
				'data' => [],
			];

			$stmt2 = $this->db->prepare(
				'SELECT *
				FROM store
				WHERE userId = :uid'
			);
			$stmt2->bindValue('uid', $data['id']);
			$stmt2->execute();

			while ($userData = $stmt2->fetch()) {
				$user['data'][] = [
					'key' => $userData['k'],
					'value' => $userData['v'],
					'validated' => (int)$userData['valid'],
				];
			}
			$stmt2->closeCursor();

			$result[] = $user;
		}

		$response->getBody()->write(json_encode($result));

		return $response;
	}


	public function import(): void {
		$replicationStatus = [];

		if (file_exists(__DIR__ . '/../config/replication.json')) {
			$replicationStatus =
				json_decode(file_get_contents(__DIR__ . '/../config/replication.json'), true);
		}

		foreach ($this->replicationHosts as $replicationHost) {
			$timestamp = 0;

			if (isset($replicationStatus[$replicationHost])) {
				$timestamp = $replicationStatus[$replicationHost];
			}

			$page = 0;
			while (true) {
				// Retrieve public key && store
				$req = new \GuzzleHttp\Psr7\Request(
					'GET', $replicationHost . '?timestamp=' . $timestamp . '&page=' . $page
				);

				$client = new Client();
				$resp = $client->send($req, [
					'timeout' => 5,
				]);

				$data = json_decode($resp->getBody()->getContents(), true);
				if (count($data) === 0) {
					break;
				}

				foreach ($data as $user) {
					$this->parseUser($user);
					$replicationStatus[$replicationHost] = $user['timestamp'];
				}

				$page++;
			}

			file_put_contents(
				__DIR__ . '/../config/replication.json', json_encode($replicationStatus, JSON_PRETTY_PRINT)
			);
		}
	}


	/**
	 * @param array $user
	 */
	private function parseUser(array $user): void {
		$stmt = $this->db->prepare(
			'SELECT id, UNIX_TIMESTAMP(timestamp) AS timestamp
			FROM users
			WHERE federationId = :id'
		);
		$stmt->bindParam('id', $user['cloudId']);

		$stmt->execute();

		// New
		if ($stmt->rowCount() === 1) {
			$data = $stmt->fetch();
			if ($data['timestamp'] > $user['timestamp']) {
				$stmt->closeCursor();

				return;
			}

			$stmt2 = $this->db->prepare(
				'DELETE FROM users
				WHERE federationId = :id'
			);
			$stmt2->bindParam('id', $user['cloudId']);
			$stmt2->execute();
			$stmt2->closeCursor();
		}

		$stmt->closeCursor();

		$stmt = $this->db->prepare(
			'INSERT INTO users (federationId, timestamp) VALUES (:federationId, FROM_UNIXTIME(:timestamp))'
		);
		$stmt->bindParam(':federationId', $user['cloudId'], PDO::PARAM_STR);
		$stmt->bindParam(':timestamp', $user['timestamp'], PDO::PARAM_INT);
		$stmt->execute();
		$id = $this->db->lastInsertId();
		$stmt->closeCursor();

		foreach ($user['data'] as $data) {
			$stmt = $this->db->prepare(
				'INSERT INTO store (userId, k, v, valid) VALUES (:userId, :k, :v, :valid)'
			);
			$stmt->bindParam(':userId', $id, PDO::PARAM_INT);
			$stmt->bindParam(':k', $data['key'], PDO::PARAM_STR);
			$stmt->bindParam(':v', $data['value'], PDO::PARAM_STR);
			$stmt->bindParam(':valid', $data['validated'], PDO::PARAM_INT);

			$stmt->execute();
			$stmt->closeCursor();
		}
	}
}
