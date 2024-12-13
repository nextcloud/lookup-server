<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace LookupServer\Validator;


use Abraham\TwitterOAuth\TwitterOAuth;
use Exception;
use LookupServer\SignatureHandler;
use PDO;

class Twitter {

	private TwitterOAuth $twitterOAuth;
	private SignatureHandler $signatureHandler;
	private PDO $db;

	/**
	 * Twitter constructor.
	 *
	 * @param TwitterOAuth $twitterOAuth
	 * @param SignatureHandler $signatureHandler
	 * @param PDO $db
	 */
	public function __construct(TwitterOAuth $twitterOAuth, SignatureHandler $signatureHandler, PDO $db) {
		$this->twitterOAuth = $twitterOAuth;
		$this->signatureHandler = $signatureHandler;
		$this->db = $db;
	}

	/**
	 * verify Twitter proof
	 *
	 * @param array $verificationData from toVerify table
	 * @param array $userData stored user data
	 *
	 * @return bool
	 */
	public function verify(array $verificationData, array $userData): bool {
		$twitterHandle = $verificationData['location'];
		$isValid = $this->isValidTwitterHandle($twitterHandle);
		$result = false;

		if ($isValid === false) {
			return $result;
		}

		try {
			$userName = substr($twitterHandle, 1);
			list($tweetId, $text) = $this->getTweet($userName);
			if ($text !== null) {
				$cloudId = $userData['federationId'];
				list($message, $md5signature) = $this->splitMessageSignature($text);
				$signature = $userData['twitter_signature']['value'];
				$result = $this->signatureHandler->verify($cloudId, $message, $signature);
				$result = $result && md5($signature) === $md5signature;
			}
		} catch (Exception $e) {
			return false;
		}

		if ($result === true) {
			$this->storeReference((int)$verificationData['userId'], $tweetId);
		}

		return $result;
	}

	/**
	 * get tweet text and id
	 *
	 * @param string $userName user name without the '@'
	 *
	 * @return array
	 */
	private function getTweet(string $userName): array {

		try {
			$search = 'from:' . $userName . ' Use my Federated Cloud ID to share with me';
			$statuses = $this->twitterOAuth->get('search/tweets', ['q' => $search]);

			$id = $statuses->statuses[0]->id;
			$text = $statuses->statuses[0]->text;
		} catch (Exception $e) {
			return [null, null];
		}

		return [$id, $text];
	}

	/**
	 * check if we have a correct twitter Handle
	 *
	 * @param string $twitterHandle
	 *
	 * @return bool
	 */
	private function isValidTwitterHandle(string $twitterHandle): bool {
		$result = preg_match('/^@[A-Za-z0-9_]+$/', $twitterHandle);

		return $result === 1;
	}

	/**
	 * split message and signature
	 *
	 * @param string $proof
	 *
	 * @return array
	 */
	private function splitMessageSignature(string $proof): array {
		$signature = substr($proof, -32);
		$message = substr($proof, 0, -32);

		return [trim($message), trim($signature)];
	}

	/**
	 * store reference to tweet
	 *
	 * @param int $userId
	 * @param string $tweetId
	 */
	private function storeReference(int $userId, string $tweetId) {

		$key = 'tweet_id';

		// delete old value, if exists
		$stmt = $this->db->prepare('DELETE FROM store WHERE userId = :userId AND k = :k');
		$stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
		$stmt->bindParam(':k', $key, PDO::PARAM_STR);
		$stmt->execute();
		$stmt->closeCursor();

		// add new value
		$stmt = $this->db->prepare('INSERT INTO store (userId, k, v) VALUES (:userId, :k, :v)');
		$stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
		$stmt->bindParam(':k', $key, PDO::PARAM_STR);
		$stmt->bindParam(':v', $tweetId, PDO::PARAM_STR);
		$stmt->execute();
		$stmt->closeCursor();
	}

}
