<?php

namespace LookupServer;

use GuzzleHttp\Client;
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

	public function register(Request $request, Response $response) {
		$body = json_decode($request->getBody(), true);

		//TODO: Error out

		$cloudId = $body['message']['data']['federationId'];

		// Get fed id
		list($user, $host) = $this->splitCloudId($cloudId);

		/*
		 * Retrieve public key && store
		 * TODO: To HTTPS
		 * TODO: Cache?
		 */
		$ocsreq = new \GuzzleHttp\Psr7\Request(
			'GET',
			'http://'.$host . '/ocs/v2.php/identityproof/key/' . $user,
			[
				'OCS-APIREQUEST' => 'true',
				'Accept' => 'application/json',
			]);

		$client = new Client();
		$ocsresponse = $client->send($ocsreq, ['timeout' => 10]);
		//TODO: handle timeout
		//TODO: handle on 200 status
		$ocsresponse = json_decode($ocsresponse->getBody(), true);

		$key = $ocsresponse['ocs']['data']['public'];

		// verify message
		$message = json_encode($body['message']);
		$signature= base64_decode($body['signature']);


		$res = openssl_verify($message, $signature, $key, OPENSSL_ALGO_SHA512);

		if ($res === 1) {
			$this->cleanup($cloudId, $body['message']['timestamp']);
			$this->insert($body['message']['data'], $body['message']['timestamp']);
			//Delete old data if it is there
			$response->getBody()->write("ALL IS GOOD!");
		} else {
			// ERROR OUT
			$response->withStatus(403);
		}
		return $response;
	}

	public function update(Request $request, Response $response) {
		return $response;
	}
}
