<?php

namespace LookupServer;

use GuzzleHttp\Client;
use Slim\Http\Request;
use Slim\Http\Response;

class Replication {

	/** @var \PDO */
	private $db;

	/** @var string */
	private $auth;

	/** @var string[] */
	private $replicationHosts;

	public function __construct(\PDO $db, $auth, $replicationHosts) {
		$this->db = $db;
		$this->auth = $auth;
		$this->replicationHosts = $replicationHosts;
	}

	public function export(Request $request, Response $response) {
		$userInfo = $request->getUri()->getUserInfo();

		$userInfo = explode(':', $userInfo, 2);

		if (count($userInfo) !== 2 || $userInfo[0] !== 'lookup' || $userInfo[1] !== $this->auth)  {
			$response = $response->withStatus(401);
			return $response;
		}

		$params = $request->getQueryParams();
		if (!isset($params['timestamp'], $params['page']) || !ctype_digit($params['timestamp']) ||
		    !ctype_digit($params['page'])) {
			$response = $response->withStatus(400);
			return $response;
		}

		$timestamp = (int)$params['timestamp'];
		$page = (int)$params['page'];

		$stmt = $this->db->prepare('SELECT id, federationId, UNIX_TIMESTAMP(timestamp) AS timestamp
			FROM users 
			WHERE UNIX_TIMESTAMP(timestamp) >= :timestamp
			ORDER BY timestamp, id
			LIMIT :limit
			OFFSET :offset');
		$stmt->bindParam('timestamp', $timestamp);
		$stmt->bindValue('limit', 100, \PDO::PARAM_INT);
		$stmt->bindValue('offset', 100 * $page, \PDO::PARAM_INT);

		$stmt->execute();

		$result = [];
		while($data = $stmt->fetch()) {
			$user = [
				'cloudId' => $data['federationId'],
				'timestamp' => (int)$data['timestamp'],
				'data' => [],
			];

			$stmt2 = $this->db->prepare('SELECT *
				FROM store
				WHERE userId = :uid');
			$stmt2->bindValue('uid', $data['id']);
			$stmt2->execute();

			while($userData = $stmt2->fetch()) {
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

	public function import(Request $request, Response $response) {
		$replicationStatus = [];

		if (file_exists(__DIR__ . '/../config/replication.json')) {
			$replicationStatus = json_decode(file_get_contents(__DIR__ . '/../config/replication.json'), true);
		}

		foreach ($this->replicationHosts as $replicationHost) {
			$timestamp = 0;

			if (isset($replicationStatus[$replicationHost])) {
				$timestamp = $replicationStatus[$replicationHost];
			}

			$page = 0;
			while(true) {
				// Retrieve public key && store
				$req = new \GuzzleHttp\Psr7\Request('GET', $replicationHost . '?timestamp=' . $timestamp . '&page=' . $page);

				$client = new Client();
				$resp = $client->send($req, [
					'timeout' => 5,
				]);

				$data = json_decode($resp->getBody(), true);
				if (count($data) === 0) {
					break;
				}

				foreach ($data as $user) {
					$this->parseUser($user);
					$replicationStatus[$replicationHost] = $user['timestamp'];
				}

				$page++;
			}

			file_put_contents(__DIR__. '/../config/replication.json', json_encode($replicationStatus, JSON_PRETTY_PRINT));
		}

		return $response;
	}

	private function parseUser($user) {
		$stmt = $this->db->prepare('SELECT id, UNIX_TIMESTAMP(timestamp) AS timestamp
			FROM users
			WHERE federationId = :id');
		$stmt->bindParam('id', $user['cloudId']);

		$stmt->execute();

		// New
		if ($stmt->rowCount() === 1) {
			$data = $stmt->fetch();
			if ($data['timestamp'] > $user['timestamp']) {
				$stmt->closeCursor();
				return;
			}

			$stmt2 = $this->db->prepare('DELETE FROM users
				WHERE federationId = :id');
			$stmt2->bindParam('id', $user['cloudId']);
			$stmt2->execute();
			$stmt2->closeCursor();
		}

		$stmt->closeCursor();

		$stmt = $this->db->prepare('INSERT INTO users (federationId, timestamp) VALUES (:federationId, FROM_UNIXTIME(:timestamp))');
		$stmt->bindParam(':federationId', $user['cloudId'], \PDO::PARAM_STR);
		$stmt->bindParam(':timestamp', $user['timestamp'], \PDO::PARAM_INT);
		$stmt->execute();
		$id = $this->db->lastInsertId();
		$stmt->closeCursor();

		foreach ($user['data'] as $data) {
			$stmt = $this->db->prepare('INSERT INTO store (userId, k, v, valid) VALUES (:userId, :k, :v, :valid)');
			$stmt->bindParam(':userId', $id, \PDO::PARAM_INT);
			$stmt->bindParam(':k', $data['key'], \PDO::PARAM_STR);
			$stmt->bindParam(':v', $data['value'], \PDO::PARAM_STR);
			$stmt->bindParam(':valid', $data['validated'], \PDO::PARAM_INT);

			$stmt->execute();
			$stmt->closeCursor();
		}
	}
}
