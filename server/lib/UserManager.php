<?php

namespace LookupServer;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use LookupServer\Tools\Traits\TArrayTools;
use LookupServer\Tools\Traits\TDebug;
use LookupServer\Validator\Email;
use LookupServer\Validator\Twitter;
use LookupServer\Validator\Website;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserManager {
	use TDebug;
	use TArrayTools;

	private PDO $db;
	private Email $emailValidator;
	private Website $websiteValidator;
	private Twitter $twitterValidator;
	private SignatureHandler $signatureHandler;
	private int $maxVerifyTries = 10;
	private bool $globalScaleMode;
	private string $authKey;

	/**
	 * UserManager constructor.
	 *
	 * @param PDO $db
	 * @param Email $emailValidator
	 * @param Website $websiteValidator
	 * @param Twitter $twitterValidator
	 * @param SignatureHandler $signatureHandler
	 * @param bool $globalScaleMode
	 * @param string $authKey
	 */
	public function __construct(
		PDO $db,
		Email $emailValidator,
		Website $websiteValidator,
		Twitter $twitterValidator,
		SignatureHandler $signatureHandler,
		bool $globalScaleMode,
		string $authKey
	) {
		$this->db = $db;
		$this->emailValidator = $emailValidator;
		$this->websiteValidator = $websiteValidator;
		$this->twitterValidator = $twitterValidator;
		$this->signatureHandler = $signatureHandler;
		$this->globalScaleMode = $globalScaleMode;
		$this->authKey = $authKey;
	}


	/**
	 * @param string $input
	 *
	 * @return string
	 */
	private function escapeWildcard(string $input): string {
		//Escape %
		$output = str_replace('%', '\%', $input);

		return str_replace('_', '\_', $output);
	}


	/**
	 * @param Request $request
	 * @param Response $response
	 * @param array $args
	 *
	 * @return Response
	 */
	public function search(Request $request, Response $response, array $args = []): Response {
		$params = $request->getQueryParams();

		if ($this->get('search', $params) === '') {
			return $response->withStatus(404);
		}

		$search = (string)$params['search'];
		// search for a specific federated cloud ID
		$searchCloudId = $this->getBool('exactCloudId', $params);
		// return unique exact match, e.g. the user with a specific email address
		$exactMatch = $this->getBool('exact', $params);

		// parameters allow you to specify which keys should be checked for a search query
		// by default we check all keys, this way you can for example search for email addresses only
		$parameters = [];
		if ($exactMatch === true) {
			$keys = $this->get('keys', $params, '{}');
			$keysDecoded = json_decode($keys, false, 2);
			if (is_array($keysDecoded)) {
				$parameters = $keysDecoded;
			}
		}

		if ($searchCloudId === true) {
			$users = $this->getExactCloudId($search);
		} else if ($this->globalScaleMode === true) {
			// in a global scale setup we ignore the karma
			// the lookup server is populated by the admin and we know
			// that it contain only valid user information
			$users = $this->performSearch($search, $exactMatch, $parameters, 0);
		} else {
			// in a general setup we only return users who validated at least one personal date
			$users = $this->performSearch($search, $exactMatch, $parameters, 1);
		}

		// if we look for a exact match we return only this one result, not a list of one element
		if ($exactMatch && !empty($users)) {
			$users = $users[0];
		}

		$response->getBody()->write(json_encode($users));

		return $response;
	}


	/**
	 * search user, for example to share with them
	 * return all results with karma >= 1
	 *
	 * @param string $search
	 * @param bool $exactMatch
	 * @param array $parameters
	 * @param int $minKarma
	 *
	 * @return array
	 */
	private function performSearch($search, $exactMatch, $parameters, $minKarma) {
		/**
		 * We assume that we want to check for matches in both userid
		 * and email. However, if the search string looks like an email
		 * address, we check if there are multiple accounts with the same
		 * email address registred. If so, we limit the search to userid.
		 * We will never search the name keys.
		 */
		$searchKeys = ['userid', 'email'];
		if (preg_match('/@\w?\w+(\.\w+)*$/', $search) === 1) {
			$numStmt = $this->db->prepare('SELECT count(*) as count FROM `store` WHERE v = :search AND k = "email"');
			$numStmt->bindParam('search', $search, \PDO::PARAM_STR);
			$numStmt->execute();
			$numResult = (int) $numStmt->fetch()['count'];
			$numStmt->closeCursor();
			if ($numResult > 1) {
				$searchKeys = ['userid'];
			}
		}
		$operator = $exactMatch ? ' = ' : ' LIKE ';
		$limit = $exactMatch ? 1 : 50;

		$constraint = '';
		if (!empty($parameters)) {
			$constraint = 'AND (';
			$c = count($parameters);
			for ($i = 0; $i < $c; $i++) {
				if ($i !== 0) {
					$constraint .= ' OR ';
				}
				$constraint .= '(k = :key' . $i . ')';
			}
			$constraint .= ')';
		}
		$constraint .= ' AND  (';
		foreach ($searchKeys as $key) {
			$constraint .= 'k = "' . $key . '" OR ';

		}
		$constraint = preg_replace('/" OR $/', '" )', $constraint);
		$stmt = $this->db->prepare('SELECT *
FROM (
	SELECT userId AS userId, SUM(valid) AS karma
	FROM `store`
	WHERE userId IN (
		SELECT DISTINCT userId
		FROM `store`
		WHERE v ' . $operator . ' :search ' . $constraint .'
	)
	GROUP BY userId
) AS tmp
WHERE karma >= :karma
ORDER BY karma
LIMIT :limit'
		);

		$stmt->bindParam(':karma', $minKarma, PDO::PARAM_INT);
		$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

		$search = $exactMatch ? $search : '%' . $this->escapeWildcard($search) . '%';
		$stmt->bindParam('search', $search, PDO::PARAM_STR);

		// bind parameters
		foreach ($parameters as $parameter) {
			$i = 0;
			$q = $this->db->quote($parameter);
			$stmt->bindParam(':key' . $i, $q);
		}

		$stmt->execute();

		/*
		 * TODO: Better fuzzy search?
		 */

		$users = [];
		while ($data = $stmt->fetch()) {
			$users[] = $this->getForUserId((int)$data['userId']);
		}
		$stmt->closeCursor();

		return $users;
	}


	private function getExactCloudId(string $cloudId): array {
		$stmt = $this->db->prepare('SELECT id FROM users WHERE federationId = :id');
		$stmt->bindParam(':id', $cloudId);
		$stmt->execute();
		$data = $stmt->fetch();

		if (!$data) {
			return [];
		}

		return $this->getForUserId((int)$data['id']);
	}


	/**
	 * @param int $userId
	 *
	 * @return array
	 */
	private function getForUserId(int $userId): array {
		$stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
		$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
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
		$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
		$stmt->execute();

		while ($data = $stmt->fetch()) {
			$result[$data['k']] = [
				'value' => $data['v'],
				'verified' => $data['valid']
			];
		}

		$stmt->closeCursor();

		return $result;
	}

	/**
	 * @param string $cloudId
	 * @param string[] $data
	 * @param int $timestamp
	 */
	private function insert(string $cloudId, array $data, int $timestamp): void {
		$stmt = $this->db->prepare(
			'INSERT INTO users (federationId, timestamp) VALUES (:federationId, FROM_UNIXTIME(:timestamp))'
		);
		$stmt->bindParam(':federationId', $cloudId, PDO::PARAM_STR);
		$stmt->bindParam(':timestamp', $timestamp, PDO::PARAM_INT);
		$stmt->execute();
		$id = $this->db->lastInsertId();
		$stmt->closeCursor();

		$fields = [
			'name', 'email', 'address', 'website', 'twitter', 'phone', 'twitter_signature',
			'website_signature', 'userid'
		];

		foreach ($fields as $field) {
			if (!isset($data[$field]) || $data[$field] === '') {
				continue;
			}

			$stmt = $this->db->prepare('INSERT INTO store (userId, k, v) VALUES (:userId, :k, :v)');
			$stmt->bindParam(':userId', $id, PDO::PARAM_INT);
			$stmt->bindParam(':k', $field, PDO::PARAM_STR);
			$stmt->bindParam(':v', $data[$field], PDO::PARAM_STR);
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
	private function update(int $id, array $data, int $timestamp): void {
		$stmt = $this->db->prepare('UPDATE users SET timestamp = FROM_UNIXTIME(:timestamp) WHERE id = :id');
		$stmt->bindParam(':id', $id, PDO::PARAM_STR);
		$stmt->bindParam(':timestamp', $timestamp, PDO::PARAM_INT);
		$stmt->execute();
		$stmt->closeCursor();
		$fields = [
			'name', 'email', 'address', 'website', 'twitter', 'phone', 'twitter_signature',
			'website_signature', 'userid'
		];

		$stmt = $this->db->prepare('SELECT * FROM store WHERE userId = :userId');
		$stmt->bindParam(':userId', $id, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		$stmt->closeCursor();
		foreach ($rows as $row) {
			$key = $row['k'];
			$value = $row['v'];
			if (($loc = array_search($key, $fields)) !== false) {
				unset($fields[$loc]);
			}

			if ($this->get($key, $data) === '') {
				// key not present in new data so delete
				$stmt = $this->db->prepare('DELETE FROM store WHERE id = :id');
				$stmt->bindParam(':id', $row['id']);
				$stmt->execute();
				$stmt->closeCursor();
			} else {
				// Key present check if we need to update
				if ($data[$key] === $value) {
					$this->needToVerify($id, $row['id'], $data, $key);
					continue;
				}
				$stmt = $this->db->prepare('UPDATE store SET v = :v, valid = 0 WHERE id = :id');
				$stmt->bindParam(':id', $row['id']);
				$stmt->bindParam(':v', $data[$key]);
				$stmt->execute();
				$stmt->closeCursor();
				if ($key === 'email') {
					$this->emailValidator->emailUpdated($data[$key], $row['id']);
				}
			}

			// remove verification request if correspondig data was deleted
			$this->removeOpenVerificationRequestByStoreId($row['id']);
		}

		//Check for new fields
		foreach ($fields as $field) {
			// Not set or empty field
			if ($this->get($field, $data) === '') {
				continue;
			}

			// Insert
			$stmt = $this->db->prepare('INSERT INTO store (userId, k, v) VALUES (:userId, :k, :v)');
			$stmt->bindParam(':userId', $id, PDO::PARAM_INT);
			$stmt->bindParam(':k', $field, PDO::PARAM_STR);
			$stmt->bindParam(':v', $data[$field], PDO::PARAM_STR);
			$stmt->execute();
			$storeId = $this->db->lastInsertId();
			$stmt->closeCursor();

			if ($field === 'email') {
				$this->emailValidator->emailUpdated($data[$field], $storeId);
			}
		}
	}


	/**
	 * @param string $userId
	 * @param int $storeId
	 * @param array $data
	 * @param string $key
	 */
	private function needToVerify(string $userId, int $storeId, array $data, string $key): void {
		$stmt = $this->db->prepare('SELECT * FROM toVerify WHERE  storeId = :storeId');
		$stmt->bindParam(':storeId', $storeId, PDO::PARAM_INT);
		$stmt->execute();
		$alreadyExists = $stmt->fetch();

		if ($alreadyExists === false && isset($data['verificationStatus'][$key])
			&& $data['verificationStatus'][$key] === '1') {
			$tries = 0;
			$stmt = $this->db->prepare(
				'INSERT INTO toVerify (userId, storeId, property, location, tries) VALUES (:userId, :storeId, :property, :location, :tries)'
			);
			$stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
			$stmt->bindParam(':storeId', $storeId, PDO::PARAM_INT);
			$stmt->bindParam(':property', $key);
			$stmt->bindParam(':location', $data[$key]);
			$stmt->bindParam(':tries', $tries, PDO::PARAM_INT);
			$stmt->execute();
			$stmt->closeCursor();
		}
	}


	public function register(Request $request, Response $response, array $args = []): Response {
		$body = json_decode($request->getBody(), true);

		if ($body === null || !isset($body['message']) || !isset($body['message']['data'])
			|| !isset($body['message']['data']['federationId'])
			|| !isset($body['signature'])
			|| !isset($body['message']['timestamp'])) {
			return $response->withStatus(400);
		}

		$cloudId = $body['message']['data']['federationId'];

		try {
			$verified = $this->signatureHandler->verify($cloudId, $body['message'], $body['signature']);
		} catch (Exception $e) {
			return $response->withStatus(400);
		}

		if ($verified) {
			$result =
				$this->insertOrUpdate($cloudId, $body['message']['data'], $body['message']['timestamp']);
			if ($result === false) {
				return $response->withStatus(403);
			}
		} else {
			// ERROR OUT
			return $response->withStatus(403);
		}

		return $response;
	}


	/**
	 * returns details about a list of registered users
	 *
	 * @param Request $request
	 * @param Response $response
	 * @param array $args
	 *
	 * @return Response
	 */
	public function batchDetails(Request $request, Response $response, array $args = []): Response {
		$body = json_decode($request->getBody(), true);

		if ($body === null || !isset($body['authKey']) || !isset($body['users'])) {
			return $response->withStatus(400);
		}

		if ($body['authKey'] !== $this->authKey) {
			return $response->withStatus(403);
		}

		$response->getBody()->write(json_encode($this->selectDetails($body['users'])));

		return $response;
	}


	/**
	 * let Nextcloud servers auto register users, used in the global scale scenario
	 *
	 * @param Request $request
	 * @param Response $response
	 * @param array $args
	 *
	 * @return Response
	 */
	public function batchRegister(Request $request, Response $response, array $args = []): Response {

		$body = json_decode($request->getBody(), true);

		if ($body === null || !isset($body['authKey']) || !isset($body['users'])) {
			return $response->withStatus(400);
		}

		if ($body['authKey'] !== $this->authKey) {
			return $response->withStatus(403);
		}

		foreach ($body['users'] as $cloudId => $data) {
			$this->insertOrUpdate($cloudId, $data, time());
		}

		return $response;
	}

	/**
	 * let Nextcloud servers remove users from the lookup server, used in the global scale scenario
	 *
	 * @param Request $request
	 * @param Response $response
	 * @param array $args
	 *
	 * @return Response
	 */
	public function batchDelete(Request $request, Response $response, array $args = []): Response {

		$body = json_decode($request->getBody(), true);

		if ($body === null || !isset($body['authKey']) || !isset($body['users'])) {
			return $response->withStatus(400);
		}

		if ($body['authKey'] !== $this->authKey) {
			return $response->withStatus(403);
		}

		foreach ($body['users'] as $cloudId) {
			$this->deleteDBRecord($cloudId);
		}

		return $response;

	}


	/**
	 * @param Request $request
	 * @param Response $response
	 * @param array $args
	 *
	 * @return Response
	 * @throws GuzzleException
	 */
	public function delete(Request $request, Response $response, array $args = []): Response {
		$body = json_decode($request->getBody(), true);

		if ($body === null || !isset($body['message']) || !isset($body['message']['data'])
			|| !isset($body['message']['data']['federationId'])
			|| !isset($body['signature'])
			|| !isset($body['message']['timestamp'])) {
			return $response->withStatus(400);
		}

		$cloudId = $body['message']['data']['federationId'];

		try {
			$verified = $this->signatureHandler->verify($cloudId, $body['message'], $body['signature']);
		} catch (Exception $e) {
			return $response->withStatus(400);
		}


		if ($verified) {
			$result = $this->deleteDBRecord($cloudId);
			if ($result === false) {
				return $response->withStatus(404);
			}
		} else {
			// ERROR OUT
			return $response->withStatus(403);
		}

		return $response;
	}


	/**
	 * @param Request|null $request
	 * @param Response|null $response
	 * @param array $args
	 *
	 * @return Response|null
	 */
	public function verify(
		?Request $request = null,
		?Response $response = null,
		array $args = []
	): ?Response {
		$verificationRequests = $this->getOpenVerificationRequests();
		foreach ($verificationRequests as $verificationData) {
			$success = false;
			switch ($verificationData['property']) {
				case 'twitter':
					$userData = $this->getForUserId($verificationData['userId']);
					$success = $this->twitterValidator->verify($verificationData, $userData);
					break;
				case 'website':
					$userData = $this->getForUserId($verificationData['userId']);
					$success = $this->websiteValidator->verify($verificationData, $userData);
					break;
			}
			if ($success) {
				$this->updateVerificationStatus($verificationData['storeId']);
				$this->removeOpenVerificationRequest($verificationData['id']);
			} else {
				$this->incMaxTries($verificationData);
			}
		}

		return $response;
	}


	private function selectDetails(array $userIds): array {
		$stmt = $this->db->prepare('SELECT
    `u`.`federationId`,
    `s`.`v` AS `displayName`
FROM
    `store` AS `s`,
    `users` AS `u`
WHERE
    `u`.`id` = `s`.`userId` AND `s`.`k` = \'name\' 
  AND `u`.`federationId`
          IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')'
		);

		$stmt->execute($userIds);

		$details = [];
		while ($data = $stmt->fetch()) {
			$details[$data['federationId']] = $data['displayName'];
		}

		$stmt->closeCursor();

		return $details;
	}
	

	/**
	 * increase number of max tries to verify account data
	 *
	 * @param array $verificationData
	 */
	private function incMaxTries(array $verificationData): void {
		$tries = (int)$verificationData['tries'];
		$tries++;

		// max number of tries reached, remove verification request and return
		if ($tries > $this->maxVerifyTries) {
			$this->removeOpenVerificationRequest($verificationData['id']);

			return;
		}

		$stmt = $this->db->prepare('UPDATE toVerify SET tries = :tries WHERE id = :id');
		$stmt->bindParam('id', $verificationData['id']);
		$stmt->bindParam('tries', $tries);
		$stmt->execute();
		$stmt->closeCursor();
	}

	/**
	 * if data could be verified successfully we update the information in the store table
	 *
	 * @param int $storeId
	 */
	private function updateVerificationStatus(int $storeId): void {
		$stmt = $this->db->prepare('UPDATE store SET valid = 1 WHERE id = :storeId');
		$stmt->bindParam('storeId', $storeId);
		$stmt->execute();
		$stmt->closeCursor();
	}

	/**
	 * remove data from to verify table if verification was successful or max. number of tries reached.
	 *
	 * @param $id
	 */
	private function removeOpenVerificationRequest($id) {
		$stmt = $this->db->prepare('DELETE FROM toVerify WHERE id = :id');
		$stmt->bindParam(':id', $id);
		$stmt->execute();
		$stmt->closeCursor();
	}

	/**
	 * remove data from to verify table if the user data was removed or changed
	 *
	 * @param $storeId
	 */
	private function removeOpenVerificationRequestByStoreId($storeId) {
		$stmt = $this->db->prepare('DELETE FROM toVerify WHERE storeId = :storeId');
		$stmt->bindParam(':storeId', $storeId);
		$stmt->execute();
		$stmt->closeCursor();
	}


	/**
	 * get open verification Requests
	 *
	 * @return array
	 */
	private function getOpenVerificationRequests(): array {
		$stmt = $this->db->prepare('SELECT * FROM toVerify LIMIT 10');
		$stmt->execute();
		$result = $stmt->fetchAll();
		$stmt->closeCursor();

		return $result;
	}

	/**
	 * @param string $cloudId
	 * @param string[] $data
	 * @param int $timestamp
	 *
	 * @return bool
	 */
	private function insertOrUpdate(string $cloudId, array $data, int $timestamp): bool {
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

	/**
	 * Delete all personal data. We keep the basic user entry with the
	 * federated cloud ID in order to propagate the changes
	 *
	 * @param string $cloudId
	 *
	 * @return bool
	 */
	private function deleteDBRecord(string $cloudId): bool {

		$stmt = $this->db->prepare('SELECT * FROM users WHERE federationId = :federationId');
		$stmt->bindParam(':federationId', $cloudId);
		$stmt->execute();
		$row = $stmt->fetch();
		$stmt->closeCursor();

		// If we can't find the user
		if ($row === false) {
			return false;
		}

		// delete user data
		$stmt = $this->db->prepare('DELETE FROM store WHERE userId = :userId');
		$stmt->bindParam(':userId', $row['id']);
		$stmt->execute();
		$stmt->closeCursor();

		return true;
	}
}
