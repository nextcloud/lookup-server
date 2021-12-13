<?php


namespace LookupServer;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


class InstanceManager {


	/** @var PDO */
	private $db;

	/** @var SignatureHandler */
	private $signatureHandler;

	/** @var bool */
	private $globalScaleMode = false;

	/** @var string */
	private $authKey = '';

	/** @var array */
	private $instances = [];


	/**
	 * InstanceManager constructor.
	 *
	 * @param PDO $db
	 * @param SignatureHandler $signatureHandler
	 * @param array $settings
	 */
	public function __construct(
		PDO $db,
		SignatureHandler $signatureHandler,
		bool $globalScaleMode,
		string $authKey,
		?array $instances
	) {
		$this->db = $db;
		$this->signatureHandler = $signatureHandler;
		$this->globalScaleMode = $globalScaleMode;
		$this->authKey = $authKey;
		if (is_array($instances)) {
			$this->instances = $instances;
		}
	}


	public function insert(string $instance) {
		$stmt = $this->db->prepare('SELECT id, instance, timestamp FROM instances WHERE instance=:instance');
		$stmt->bindParam(':instance', $instance, PDO::PARAM_STR);
		$stmt->execute();

		$data = $stmt->fetch();
		if ($data === false) {
			$time = time();
			$insert = $this->db->prepare(
				'INSERT INTO instances (instance, timestamp) VALUES (:instance, FROM_UNIXTIME(:timestamp))'
			);
			$insert->bindParam(':instance', $instance, PDO::PARAM_STR);
			$insert->bindParam(':timestamp', $time, PDO::PARAM_INT);

			$insert->execute();
		}
	}


	/**
	 * let Nextcloud servers obtains the full list of registered instances in the global scale scenario
	 * If result is empty, sync from the users list
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @return Response
	 */
	public function getInstances(Request $request, Response $response): Response {
		if ($this->globalScaleMode !== true) {
			$response->withStatus(404);

			return $response;
		}

		$body = json_decode($request->getBody(), true);
		if ($body === null || !isset($body['authKey'])) {
			$response->withStatus(400);

			return $response;
		}

		if ($body['authKey'] !== $this->authKey) {
			$response->withStatus(403);

			return $response;
		}

		$instances = $this->getAll();
		if (empty($instances)) {
			$this->syncInstances();
			$instances = $this->getAll();
		}

		$response->getBody()
				 ->write(json_encode($instances));

		return $response;
	}


	/**
	 * @return array
	 */
	public function getAll(): array {
		if (is_array($this->instances) && !empty($this->instances)) {
			return $this->instances;
		}

		$stmt = $this->db->prepare('SELECT instance FROM instances');
		$stmt->execute();

		$instances = [];
		while ($data = $stmt->fetch()) {
			$instances[] = $data['instance'];
		}
		$stmt->closeCursor();

		return $instances;
	}


	public function getAllFromConfig(): array {
		return $this->instances;
	}

	/**
	 * sync the instances from the users table
	 */
	public function syncInstances(): void {
		$stmt = $this->db->prepare('SELECT federationId FROM users');
		$stmt->execute();
		$instances = [];
		while ($data = $stmt->fetch()) {
			$pos = strrpos($data['federationId'], '@');
			$instance = substr($data['federationId'], $pos + 1);
			if (!in_array($instance, $instances)) {
				$instances[] = $instance;
			}
		}
		$stmt->closeCursor();

		foreach ($instances as $instance) {
			$this->insert($instance);
		}

		$this->removeDeprecatedInstances($instances);
	}


	/**
	 * @param string|null $instance
	 * @param bool $removeUsers
	 */
	public function remove(string $instance, bool $removeUsers = false): void {
		$stmt = $this->db->prepare('DELETE FROM instances WHERE instance = :instance');
		$stmt->bindParam(':instance', $instance);
		$stmt->execute();
		$stmt->closeCursor();

		if ($removeUsers) {
			$this->removeUsers($instance);
		}
	}


	/**
	 * @param string $instance
	 */
	private function removeUsers(string $instance) {
		$search = '%@' . $this->escapeWildcard($instance);
		$stmt = $this->db->prepare('SELECT id FROM users WHERE federationId LIKE :search');
		$stmt->bindParam(':search', $search);
		$stmt->execute();

		while ($data = $stmt->fetch()) {
			$this->removeUser($data['id']);
		}

		$stmt->closeCursor();
		$this->removingEmptyInstance($instance);
	}


	/**
	 * @param int $userId
	 */
	private function removeUser(int $userId) {
		$stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
		$stmt->bindParam(':id', $userId);
		$stmt->execute();

		$stmt = $this->db->prepare('DELETE FROM store WHERE userId = :id');
		$stmt->bindParam(':id', $userId);
		$stmt->execute();
	}


	/**
	 * @param string $input
	 *
	 * @return string
	 */
	private function escapeWildcard(string $input): string {
		$output = str_replace('%', '\%', $input);
		$output = str_replace('_', '\_', $output);

		return $output;
	}


	/**
	 * @param string $cloudId
	 */
	public function newUser(string $cloudId): void {
		$pos = strrpos($cloudId, '@');
		$instance = substr($cloudId, $pos + 1);

		$this->insert($instance);
	}


	/**
	 * @param string $cloudId
	 */
	public function removingUser(string $cloudId): void {
		$pos = strrpos($cloudId, '@');
		$instance = substr($cloudId, $pos + 1);

		$this->removingEmptyInstance($instance);
	}


	/**
	 * @param string $instance
	 */
	private function removingEmptyInstance(string $instance) {
		$search = '%@' . $this->escapeWildcard($instance);

		$stmt = $this->db->prepare('SELECT federationId FROM users WHERE federationId LIKE :search');
		$stmt->bindParam(':search', $search);
		$stmt->execute();
		if ($stmt->fetch() === false) {
			$this->remove($instance);
		}
	}


	/**
	 * @param array $instances
	 */
	private function removeDeprecatedInstances(array $instances): void {
		$current = $this->getAll();

		foreach ($current as $item) {
			if (!in_array($item, $instances)) {
				$this->remove($item);
			}
		}
	}

}

