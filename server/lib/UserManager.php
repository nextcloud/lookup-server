<?php

namespace LookupServer;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use LookupServer\Validator\Email;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class UserManager {

	/** @var \PDO */
	private $db;

	/** @var Email */
	private $emailValidator;

	public function __construct(\PDO $db, Email $emailValidator) {
		$this->db = $db;
		$this->emailValidator = $emailValidator;
	}

	public function search(Request $request, Response $response) {
		$params = $request->getQueryParams();

		if (!isset($params['search']) || $params['search'] === '') {
			$response->withStatus(404);
			return $response;		}

		$search = $params['search'];
		$stmt = $this->db->prepare('SELECT *
FROM (
	SELECT userId AS userId, SUM(valid) AS karma
	FROM `store`
	WHERE userId IN (
		SELECT DISTINCT userId
		FROM `store`
		WHERE v LIKE :search
	)
	GROUP BY userId
) AS tmp
WHERE karma > 0
ORDER BY karma
LIMIT 50');
		$search = '%' . $search . '%';
		$stmt->bindParam(':search', $search, \PDO::PARAM_STR);
		$stmt->execute();

		/*
		 * TODO: Better fuzzy search?
		 */

		$users = [];
		while($data = $stmt->fetch()) {
			$users[] = $this->getForUserId((int)$data['userId']);
		}
		$stmt->closeCursor();

		$response->getBody()->write(json_encode($users));
		return $response;
	}

	private function getForUserId($userId) {
		$stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
		$stmt->bindParam(':id', $userId, \PDO::PARAM_INT);
		$stmt->execute();
		$data = $stmt->fetch();
		$stmt->closeCursor();

		if (!$data) {
			return [];
		}

		$result = [
			'federationId' => $data['federationId']
		];

		$stmt = $this->db->prepare('SELECT * FROM store WHERE userId = :id');
		$stmt->bindParam(':id', $userId, \PDO::PARAM_INT);
		$stmt->execute();

		while($data = $stmt->fetch()) {
			$result[$data['k']] = [
				'value' => $data['v'],
				'verified' => $data['valid']
			];
		}

		$stmt->closeCursor();
		return $result;
	}

	/**
	 * Split a cloud id in a user and host post
	 *
	 * @param $cloudId
	 * @return string[]
	 */
	private function splitCloudId($cloudId) {
		$loc = strrpos($cloudId, '@');

		$user = substr($cloudId, 0, $loc);
		$host = substr($cloudId, $loc+1);
		return [$user, $host];
	}

	/**
	 * @param string $cloudId
	 * @param string[] $data
	 * @param int $timestamp
	 */
	private function insert($cloudId, $data, $timestamp) {
		$stmt = $this->db->prepare('INSERT INTO users (federationId, timestamp) VALUES (:federationId, FROM_UNIXTIME(:timestamp))');
		$stmt->bindParam(':federationId', $cloudId, \PDO::PARAM_STR);
		$stmt->bindParam(':timestamp', $timestamp, \PDO::PARAM_INT);
		$stmt->execute();
		$id = $this->db->lastInsertId();
		$stmt->closeCursor();

		$fields = ['name', 'email', 'address', 'website', 'twitter', 'phone'];

		foreach ($fields as $field) {
			if (!isset($data[$field]) || $data[$field] === '') {
				continue;
			}

			$stmt = $this->db->prepare('INSERT INTO store (userId, k, v) VALUES (:userId, :k, :v)');
			$stmt->bindParam(':userId', $id, \PDO::PARAM_INT);
			$stmt->bindParam(':k', $field, \PDO::PARAM_STR);
			$stmt->bindParam(':v', $data[$field], \PDO::PARAM_STR);
			$stmt->execute();
			$storeId = $this->db->lastInsertId();
			$stmt->closeCursor();

			if ($field === 'email') {
				$this->emailValidator->emailUpdated($data[$field], $storeId);
			}
		}
	}

	/**
	 * @param int $id
	 * @param string[] $data
	 * @param int $timestamp
	 */
	private function update($id, $data, $timestamp) {
		$stmt = $this->db->prepare('UPDATE users SET timestamp = FROM_UNIXTIME(:timestamp) WHERE id = :id');
		$stmt->bindParam(':id', $id, \PDO::PARAM_STR);
		$stmt->bindParam(':timestamp', $timestamp, \PDO::PARAM_INT);
		$stmt->execute();
		$stmt->closeCursor();

		$fields = ['name', 'email', 'address', 'website', 'twitter', 'phone'];

		$stmt = $this->db->prepare('SELECT * FROM store WHERE userId = :userId');
		$stmt->bindParam(':userId', $id, \PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		$stmt->closeCursor();

		foreach ($rows as $row) {
			$key = $row['k'];
			$value = $row['v'];
			if (($loc = array_search($key, $fields)) !== false) {
				unset($fields[$loc]);
			}

			if (!isset($data[$key]) || $data[$key] === '') {
				// key not present in new data so delete
				$stmt = $this->db->prepare('DELETE FROM store WHERE id = :id');
				$stmt->bindParam(':id', $row['id']);
				$stmt->execute();
				$stmt->closeCursor();
			} else {
				// Key present check if we need to update
				if ($data[$key] === $value) {
					continue;
				}
				$stmt = $this->db->prepare('UPDATE store SET v = :v, valid = 0 WHERE id = :id');
				$stmt->bindParam(':id', $row['id']);
				$stmt->bindParam(':v', $data[$key]);
				$stmt->execute();
				$stmt->closeCursor();

				$this->emailValidator->emailUpdated($data[$key], $row['id']);
			}
		}

		//Check for new fields
		foreach ($fields as $field) {
			// Not set or empty field
			if (!isset($data[$field]) || $data[$field] === '') {
				continue;
			}

			// Insert
			$stmt = $this->db->prepare('INSERT INTO store (userId, k, v) VALUES (:userId, :k, :v)');
			$stmt->bindParam(':userId', $id, \PDO::PARAM_INT);
			$stmt->bindParam(':k', $field, \PDO::PARAM_STR);
			$stmt->bindParam(':v', $data[$field], \PDO::PARAM_STR);
			$stmt->execute();
			$storeId = $this->db->lastInsertId();
			$stmt->closeCursor();

			$this->emailValidator->emailUpdated($data[$field], $storeId);
		}
	}

	public function register(Request $request, Response $response) {
		$body = json_decode($request->getBody(), true);

		if ($body === null || !isset($body['message']) || !isset($body['message']['data']) ||
			!isset($body['message']['data']['federationId']) || !isset($body['signature']) ||
			!isset($body['message']['timestamp'])) {
			$response->withStatus(400);
			return $response;
		}

		$cloudId = $body['message']['data']['federationId'];

		// Get fed id
		list($user, $host) = $this->splitCloudId($cloudId);

		// Retrieve public key && store
		$ocsreq = new \GuzzleHttp\Psr7\Request(
			'GET',
			'http://'.$host . '/ocs/v2.php/identityproof/key/' . $user,
			[
				'OCS-APIREQUEST' => 'true',
				'Accept' => 'application/json',
			]);

		$client = new Client();
		try {
			$ocsresponse = $client->send($ocsreq, ['timeout' => 10]);
		} catch(RequestException $e) {
			$response->withStatus(400);
			return $response;
		}

		$ocsresponse = json_decode($ocsresponse->getBody(), true);

		if ($ocsresponse === null || !isset($ocsresponse['ocs']) ||
			!isset($ocsresponse['ocs']['data']) || !isset($ocsresponse['ocs']['data']['public'])) {
			$response->withStatus(400);
			return $response;
		}

		$key = $ocsresponse['ocs']['data']['public'];

		// verify message
		$message = json_encode($body['message']);
		$signature= base64_decode($body['signature']);

		$res = openssl_verify($message, $signature, $key, OPENSSL_ALGO_SHA512);

		if ($res === 1) {
			$result = $this->insertOrUpdate($cloudId, $body['message']['data'], $body['message']['timestamp']);
			if ($result === false) {
				$response->withStatus(403);
			}
		} else {
			// ERROR OUT
			$response->withStatus(403);
		}

		return $response;
	}

	/**
	 * @param string $cloudId
	 * @param string[] $data
	 * @param int $timestamp
	 * @return bool
	 */
	private function insertOrUpdate($cloudId, $data, $timestamp) {

		$stmt = $this->db->prepare('SELECT * FROM users WHERE federationId = :federationId');
		$stmt->bindParam(':federationId', $cloudId);
		$stmt->execute();
		$row = $stmt->fetch();
		$stmt->closeCursor();

		// If we can't find the user
		if ($row === false) {
			// INSERT
			$this->insert($cloudId, $data, $timestamp);
		} else {
			// User found. Check if it is not a replay from an old
			if ($timestamp <= (int)$row['timestamp']) {
				return false;
			}

			// UPDATE
			$this->update($row['id'], $data, $timestamp);
		}

		return true;
	}
}
