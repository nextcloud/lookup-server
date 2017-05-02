<?php
/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
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


namespace LookupServer\Validator;


use Abraham\TwitterOAuth\TwitterOAuth;
use LookupServer\SignatureHandler;

class Twitter {

	/** @var TwitterOAuth */
	private $twitterOAuth;

	/** @var SignatureHandler */
	private $signatureHandler;

	/** @var \PDO */
	private $db;

	/**
	 * Twitter constructor.
	 *
	 * @param TwitterOAuth $twitterOAuth
	 * @param SignatureHandler $signatureHandler
	 * @param \PDO $db
	 */
	public function __construct(TwitterOAuth $twitterOAuth, SignatureHandler $signatureHandler, \PDO $db) {
		$this->twitterOAuth = $twitterOAuth;
		$this->signatureHandler = $signatureHandler;
		$this->db = $db;
	}

	/**
	 * verify Twitter proof
	 *
	 * @param array $verificationData from toVerify table
	 * @param array $userData stored user data
	 * @return bool
	 */
	public function verify(array $verificationData, array $userData) {
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
		} catch (\Exception $e) {
			// do nothing, just return false;
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
	 * @return array
	 */
	private function getTweet($userName) {

		try {
			$search = 'from:' . $userName . ' Use my Federated Cloud ID to share with me';
			$statuses = $this->twitterOAuth->get('search/tweets', ['q' => $search]);

			$id = $statuses->statuses[0]->id;
			$text = $statuses->statuses[0]->text;
		} catch (\Exception $e) {
			return [null, null];
		}

		return [$id, $text];
	}

	/**
	 * check if we have a correct twitter Handle
	 *
	 * @param $twitterHandle
	 * @return bool
	 */
	private function isValidTwitterHandle($twitterHandle) {
		$result = preg_match('/^@[A-Za-z0-9_]+$/', $twitterHandle);
		return $result === 1;
	}

	/**
	 * split message and signature
	 *
	 * @param string $proof
	 * @return array
	 */
	private function splitMessageSignature($proof) {
		$signature = substr($proof, -32);
		$message = substr($proof, 0, -32);

		return [trim($message), trim($signature)];
	}

	/**
	 * store reference to tweet
	 *
	 * @param $userId
	 * @param $tweetId
	 */
	private function storeReference($userId, $tweetId) {

		$key = 'tweet_id';

		// delete old value, if exists
		$stmt = $this->db->prepare('DELETE FROM store WHERE userId = :userId AND k = :k');
		$stmt->bindParam(':userId', $userId, \PDO::PARAM_INT);
		$stmt->bindParam(':k', $key, \PDO::PARAM_STR);
		$stmt->execute();
		$stmt->closeCursor();

		// add new value
		$stmt = $this->db->prepare('INSERT INTO store (userId, k, v) VALUES (:userId, :k, :v)');
		$stmt->bindParam(':userId', $userId, \PDO::PARAM_INT);
		$stmt->bindParam(':k', $key, \PDO::PARAM_STR);
		$stmt->bindParam(':v', $tweetId, \PDO::PARAM_STR);
		$stmt->execute();
		$stmt->closeCursor();
	}

}
