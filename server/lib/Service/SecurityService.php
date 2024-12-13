<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace LookupServer\Service;

use LookupServer\Tools\Traits\TArrayTools;

class SecurityService {
	use TArrayTools;

	public function __construct(private array $settings = []) {
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
}
