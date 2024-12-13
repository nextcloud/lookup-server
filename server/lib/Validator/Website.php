<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace LookupServer\Validator;


use Exception;
use LookupServer\SignatureHandler;

class Website {

	private SignatureHandler $signatureHandler;

	public function __construct(SignatureHandler $signatureHandler) {
		$this->signatureHandler = $signatureHandler;
	}

	/**
	 * verify website proof
	 *
	 * @param array $verificationData from toVerify table
	 * @param array $userData stored user data
	 *
	 * @return bool
	 */
	public function verify(array $verificationData, array $userData): bool {
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
		} catch (Exception $e) {
			// do nothing, just return false
		}

		return $result;
	}

	/**
	 * construct valid URL to proof
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	private function getValidUrl(string $url): string {
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
	 *
	 * @return array
	 */
	private function splitMessageSignature(string $proof): array {
		$signature = substr($proof, -344);
		$message = substr($proof, 0, -344);

		return [trim($message), trim($signature)];
	}

}
