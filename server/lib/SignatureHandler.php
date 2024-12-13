<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace LookupServer;

use BadMethodCallException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LookupServer\Exceptions\SignedRequestException;
use Psr\Http\Message\ServerRequestInterface as Request;

class SignatureHandler {

	/**
	 * check signature of incoming request
	 *
	 * @param string $cloudId
	 * @param string|array $message
	 * @param string $signature
	 *
	 * @return bool
	 * @throws GuzzleException
	 */
	public function verify(string $cloudId, string|array $message, string $signature): bool {
		// Get fed id
		list($user, $host) = $this->splitCloudId($cloudId);

		// Retrieve public key && store
		$ocsreq = new \GuzzleHttp\Psr7\Request(
			'GET',
			'http://' . $host . '/ocs/v2.php/identityproof/key/' . $user,
			[
				'OCS-APIREQUEST' => 'true',
				'Accept' => 'application/json',
			]
		);

		$client = new Client();
		$ocsresponse = $client->send($ocsreq, ['timeout' => 10]);

		$ocsresponse = json_decode($ocsresponse->getBody()->getContents(), true);

		if ($ocsresponse === null || !isset($ocsresponse['ocs'])
			|| !isset($ocsresponse['ocs']['data'])
			|| !isset($ocsresponse['ocs']['data']['public'])) {
			throw new BadMethodCallException();
		}

		$key = $ocsresponse['ocs']['data']['public'];

		// verify message
		$message = json_encode($message);
		$signature = base64_decode($signature);

		$res = openssl_verify($message, $signature, $key, OPENSSL_ALGO_SHA512);

		return $res === 1;
	}

	/**
	 * Split a cloud id in a user and host post
	 *
	 * @param string $cloudId
	 *
	 * @return string[]
	 */
	private function splitCloudId(string $cloudId): array {
		$loc = strrpos($cloudId, '@');

		$user = substr($cloudId, 0, $loc);
		$host = substr($cloudId, $loc + 1);

		return [$user, $host];
	}


	/**
	 * @param Request $request
	 *
	 * @throws SignedRequestException
	 */
	public function verifyRequest(Request $request): string {
		$body = json_decode((string)$request->getBody(), true);
		if ($body === null
			|| !isset($body['message']['data']['federationId'])
			|| !isset($body['signature'])
			|| !isset($body['message']['timestamp'])) {
			throw new SignedRequestException('malformed body');
		}

		$cloudId = $body['message']['data']['federationId'];

		try {
			$verified = $this->verify($cloudId, $body['message'], $body['signature']);
			if ($verified) {
				[, $host] = $this->splitCloudId($body['message']['data']['federationId']);

				return $host;
			}
		} catch (\Exception $e) {
			throw new SignedRequestException($e->getMessage(), 0, $e);
		}

		throw new SignedRequestException('not verified');
	}
}
