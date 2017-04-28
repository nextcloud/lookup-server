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

	/**
	 * Twitter constructor.
	 *
	 * @param TwitterOAuth $twitterOAuth
	 * @param SignatureHandler $signatureHandler
	 */
	public function __construct(TwitterOAuth $twitterOAuth, SignatureHandler $signatureHandler) {
		$this->twitterOAuth = $twitterOAuth;
		$this->signatureHandler = $signatureHandler;
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
			list($id, $text) = $this->getTweet($userName);
			if ($text !== null) {
				$cloudId = $userData['federationId'];
				list($message, $signature) = $this->splitMessageSignature($text);
				$result = $this->signatureHandler->verify($cloudId, $message, $signature);
			}
		} catch (\Exception $e) {
			// do nothing, just return false;
		}

		if ($result === true) {
			$this->storeReference($userData, $id);
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
		$search = 'from:' . $userName . ' What I am searching for';
		$statuses = $this->twitterOAuth->get('"search/tweets', ['q' => $search]);
		if (isset($statuses[0])) {
			$id = $statuses[0]->id;
			$text = $statuses[0]->text;
		} else {
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
		$signature = substr($proof, -344);
		$message = substr($proof, 0, -344);

		return [trim($message), trim($signature)];
	}

	/**
	 * store reference to tweet
	 *
	 * @param $userData
	 * @param $tweetId
	 */
	private function storeReference($userData, $tweetId) {

	}

}
