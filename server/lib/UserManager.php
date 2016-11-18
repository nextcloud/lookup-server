<?php

namespace LookupServer;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class UserManager {

	/** @var \PDO */
	private $db;

	public function __construct(\PDO $db) {
		$this->db = $db;
	}

	public function search(Request $request, Response $response) {
		$search = $request->getQueryParams()['search'];

		$stmt = $this->db->prepare('SELECT userId, SUM(valid) as karma
									FROM `store`
									WHERE v LIKE :search
									GROUP BY userId
									ORDER BY karma');
		$search = '%' . $search . '%';
		$stmt->bindParam(':search', $search, \PDO::PARAM_STR);
		$stmt->execute();

		/*
		 * TODO: Dont' fetch everything. Fetch top x?
		 * TODO: Better fuzzy search?
		 * TODO: Only fetch at least 1 karma
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
			$result[$data['k']] = $data['v'];
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
	 * @param $cloudId
	 * @return bool If we can actually cleanup the server
	 */
	private function cleanup($cloudId, $timestamp) {
		$stmt = $this->db->prepare('SELECT id, timestamp 
									FROM users 
									WHERE federationId = :federationId');
		$stmt->bindParam(':federationId', $cloudId, \PDO::PARAM_STR);
		$stmt->execute();

		$data = $stmt->fetch();
		$stmt->closeCursor();

		if ($data) {

			if ($timestamp <= (int)$data['timestamp']) {
				return false;
			}

			$stmt = $this->db->prepare('DELETE FROM store WHERE userId = :id');
			$stmt->bindParam(':id', $data['id'], \PDO::PARAM_INT);
			$stmt->execute();
			$stmt->closeCursor();

			$stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
			$stmt->bindParam(':id', $data['id'], \PDO::PARAM_INT);
			$stmt->execute();
			$stmt->closeCursor();
		}

		return true;
	}

	private function insertStore($userId, $key, $value) {
		if ($value === '') {
			return;
		}

		$stmt = $this->db->prepare('INSERT INTO store (userId, k, v) VALUES (:userId, :k, :v)');
		$stmt->bindParam(':userId', $userId, \PDO::PARAM_INT);
		$stmt->bindParam(':k', $key, \PDO::PARAM_STR);
		$stmt->bindParam(':v', $value, \PDO::PARAM_STR);
		$stmt->execute();
		$stmt->closeCursor();
	}

	private function insert($data, $timestamp) {
		$stmt = $this->db->prepare('INSERT INTO users (federationId, timestamp) VALUES (:federationId, FROM_UNIXTIME(:timestamp))');
		$stmt->bindParam(':federationId', $data['federationId'], \PDO::PARAM_STR);
		$stmt->bindParam(':timestamp', $timestamp, \PDO::PARAM_INT);
		$stmt->execute();
		$id = $this->db->lastInsertId();
		$stmt->closeCursor();

		$this->insertStore($id, 'name', $data['name']);
		$this->insertStore($id, 'email', $data['email']);
		$this->insertStore($id, 'address', $data['address']);
		$this->insertStore($id, 'website', $data['website']);
		$this->insertStore($id, 'twitter', $data['twitter']);
		$this->insertStore($id, 'phone', $data['phone']);
	}


	private function verify($data) {
		//TODO Verify!
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
			'https://'.$host . '/ocs/v2.php/identityproof/key/' . $user,
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
			$this->cleanup($cloudId, $body['message']['timestamp']);
			$this->insert($body['message']['data'], $body['message']['timestamp']);
		} else {
			// ERROR OUT
			$response->withStatus(403);
		}

		return $response;
	}
}
