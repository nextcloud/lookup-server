<?php

declare(strict_types=1);
/**
 * lookup-server - Standalone Lookup Server.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2024
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
