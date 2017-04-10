<?php

namespace LookupServer;

use Slim\Http\Request;
use Slim\Http\Response;

class Replication {

	/** @var \PDO */
	private $db;

	/** @var string */
	private $auth;

	public function __construct(\PDO $db, $auth) {
		$this->db = $db;
		$this->auth = $auth;
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

		$stmt = $this->db->prepare('SELECT * 
			FROM users 
			WHERE timestamp >= :timestamp
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
				'federationId' => $data['federationId'],
				'timestamp' => $data['timestamp'],
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
					'validated' => $userData['valid'],
				];
			}
			$stmt2->closeCursor();

			$result[] = $user;
		}

		$response->getBody()->write(json_encode($result));
		return $response;
	}
}
