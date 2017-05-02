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


use LookupServer\SignatureHandler;

class Website {

	/** @var  SignatureHandler */
	private $signatureHandler;

	public function __construct(SignatureHandler $signatureHandler) {
		$this->signatureHandler = $signatureHandler;
	}

	/**
	 * verify website proof
	 *
	 * @param array $verificationData from toVerify table
	 * @param array $userData stored user data
	 * @return bool
	 */
	public function verify($verificationData, $userData) {
		$url = $this->getValidUrl($verificationData['location']);
		$proof = @file_get_contents($url);
		$result = false;
		try {
			if ($proof) {
				$cloudId = $userData['federationId'];
				$proofSanitized = trim(preg_replace('/\s\s+/', ' ', $proof));
				list($message, $signature) = $this->splitMessageSignature($proofSanitized);
				$result = $this->signatureHandler->verify($cloudId, $message, $signature);
			}
		} catch (\Exception $e) {
			// do nothing, just return false
		}

		return $result;
	}

	/**
	 * construct valid URL to proof
	 *
	 * @param string $url
	 * @return string
	 */
	private function getValidUrl($url) {
		$url = trim($url);
		$url = rtrim($url, '/');
		if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
			$url = 'http://' . $url;
		}

		return $url . '/.well-known/CloudIdVerificationCode.txt';
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

}
