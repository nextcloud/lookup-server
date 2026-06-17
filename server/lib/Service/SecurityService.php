<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace LookupServer\Service;

use LookupServer\Tools\Traits\TArrayTools;
use IPLib\Address\IPv6;
use IPLib\Factory;
use IPLib\ParseStringFlag;
use LookupServer\Exceptions\InvalidHostException;
use Symfony\Component\HttpFoundation\IpUtils;

class SecurityService {
	use TArrayTools;

	private const LOCAL_TOPLEVEL_DOMAINS = [
		'local',
		'localhost',
		'intranet',
		'internal',
		'private',
		'corp',
		'home',
		'lan',
	];

	private const LOCAL_ADDRESS_RANGES = [
		'100.64.0.0/10', // See RFC 6598
		'192.0.0.0/24', // See RFC 6890
	];

	public function __construct(
		private array $settings,
		private readonly LoggerService $logger,
	) {
	}

	public function isGlobalScale(): bool {
		return ($this->getBool('settings.global_scale', $this->settings));
	}

	public function isValidAuth(string $auth): bool {
		$knownAuth = $this->get('settings.auth_key', $this->settings);
		return (!in_array($knownAuth, ['', 'secure key, same as the jwt key used on the global site selector and all clients']) && ($auth === $knownAuth));
	}

	public function isValidReplicationAuth(string $auth): bool {
		$knownAuth = $this->get('settings.replication_auth', $this->settings);
		return (!in_array($knownAuth, ['', 'foobar']) && ($auth === $knownAuth));
	}

	/**
	 * @throws InvalidHostException
	 * @return list<string>
	 */
	public function validateHost(string $host): array {
		$host = idn_to_utf8(strtolower(urldecode($host)));
		if ($host === false) {
			throw new InvalidHostException('Failed to convert domain name from IDNA ASCII to Unicode');
		}

		// Remove brackets from IPv6 addresses
		if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
			$host = substr($host, 1, -1);
		}

		$ip  = gethostbyname($host);
		if ($this->isLocalHostname($host) || $this->isLocalAddress($ip)) {
			throw new InvalidHostException('Invalid or private host: ' . $host);
		}

		return [$host, $ip];
	}

	/**
	 * Check host identifier for local hostname
	 */
	private function isLocalHostname(string $hostname): bool {
		// Disallow local network top-level domains from RFC 6762
		$topLevelDomain = substr((strrchr($hostname, '.') ?: ''), 1);
		if (in_array($topLevelDomain, self::LOCAL_TOPLEVEL_DOMAINS)) {
			return true;
		}

		// Disallow hostname only
		if (substr_count($hostname, '.') === 0 && !filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			return true;
		}

		return false;
	}

	/**
	 * Check host identifier for local IPv4 and IPv6 address ranges
	 */
	private function isLocalAddress(string $ip): bool {
		$parsedIp = Factory::parseAddressString(
			$ip,
			ParseStringFlag::IPV4_MAYBE_NON_DECIMAL | ParseStringFlag::IPV4ADDRESS_MAYBE_NON_QUAD_DOTTED | ParseStringFlag::MAY_INCLUDE_ZONEID
		);
		if ($parsedIp === null) {
			/* Not an IP */
			return false;
		}
		/* Replace by normalized form */
		if ($parsedIp instanceof IPv6) {
			$ip = (string)($parsedIp->toIPv4() ?? $parsedIp);
		} else {
			$ip = (string)$parsedIp;
		}

		if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			/* Range address */
			return true;
		}
		if (IpUtils::checkIp($ip, self::LOCAL_ADDRESS_RANGES)) {
			/* Within local range */
			return true;
		}

		return false;
	}
}
