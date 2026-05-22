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
use LookupServer\Exceptions\InvalidHostException;
use LookupServer\Exceptions\SignedRequestException;
use LookupServer\Service\LoggerService;
use LookupServer\Service\SecurityService;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UriInterface;

class SignatureHandler {

	public function __construct(
		private readonly SecurityService $securityService,
		private readonly LoggerService $logger,
	) {
	}

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
		list($user, $hostCloudId) = $this->splitCloudId($cloudId);

		$sanitizedHost = strtok(preg_replace('#^https?://#', '', $hostCloudId), '#?');
		$host = parse_url('http://' . $sanitizedHost, PHP_URL_HOST);
		$port = parse_url('http://' . $sanitizedHost, PHP_URL_PORT);
		$path = parse_url('http://' . $sanitizedHost, PHP_URL_PATH);

		try {
			list($host, $ip) = $this->securityService->validateHost($host);
		} catch (InvalidHostException $e) {
			$this->logger->error('Invalid host detected', ['cloudId' => $cloudId, 'host' => $host, 'exception' => $e]);
			throw new BadMethodCallException($e->getMessage());
		}

		// Retrieve public key && store
		$ocsreq = new \GuzzleHttp\Psr7\Request(
			'GET',
			'http://' . $host . ($port !== null ? ':' . $port : '') . ($path ?? '') . '/ocs/v2.php/identityproof/key/' . $user,
			[
				'OCS-APIREQUEST' => 'true',
				'Accept' => 'application/json',
			]
		);

		$client = new Client(
			[
				// ensure curl uses the already validated IP to avoid DNS rebinding attacks
				'curl' => [CURLOPT_RESOLVE => [$host . ':' . ($port ?? 80) . ':' . $ip,],],
				// validate host again upon redirection
				'allow_redirects' => [
					'on_redirect' => function (
						RequestInterface $request,
						ResponseInterface $response,
						UriInterface $uri,
					): void {
						$host = parse_url($uri->__toString(), PHP_URL_HOST);
						try {
							$this->securityService->validateHost($host);
						} catch (InvalidHostException $e) {
							$this->logger->error('Invalid host detected', ['host' => $host, 'exception' => $e]);
							throw new BadMethodCallException($e->getMessage());
						}
					},
				],
			],
		);

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
