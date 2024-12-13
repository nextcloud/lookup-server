<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace LookupServer;

use Exception;
use LookupServer\Service\SecurityService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class InstanceManager {
	public function __construct(
		private PDO $db,
		private SecurityService $securityService,
		private array $instances
	) {
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
		if (!$this->securityService->isGlobalScale()) {
			return $response->withStatus(404);
		}

		$body = json_decode($request->getBody()->getContents(), true);
		if ($body === null || !isset($body['authKey'])) {
			$response->withStatus(400);

			return $response;
		}

		if (!$this->securityService->isValidAuth($body['authKey'])) {
			return $response->withStatus(403);
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

	private function insert(string $instance): void {
		if (!$this->securityService->isGlobalScale()) {
			return;
		}

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

			try {
				$insert->execute();
			} catch (Exception $e) {
			}
		}
	}

	/**
	 * @return array
	 */
	private function getAll(): array {
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

	/**
	 * sync the instances from the users table
	 */
	private function syncInstances(): void {
		$stmt = $this->db->prepare('SELECT federationId FROM users');
		$stmt->execute();
		$instances = [];
		while ($data = $stmt->fetch()) {
			$pos = strrpos($data['federationId'], '@');
			$instance = substr($data['federationId'], $pos + 1);
			if (substr($instance, 0, 7) === 'http://') {
				$instance = substr($instance, 7);
			}
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
	 * @param string $instance
	 * @param bool $removeUsers
	 */
	private function remove(string $instance, bool $removeUsers = false): void {
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
	private function removeUsers(string $instance): void {
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
	private function removeUser(int $userId): void {
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
	private function removingEmptyInstance(string $instance): void {
		if (!$this->securityService->isGlobalScale()) {
			return;
		}

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

