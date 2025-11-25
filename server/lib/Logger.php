<?php

declare(strict_types=1);

/**
 * lookup-server - Standalone Lookup Server.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Nicolas Varlot <nicolas.varlot@ac-versailles.fr>
 * @copyright 2025
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

namespace LookupServer;

class Logger {
	private string $logFile;
	private bool $enabled;

	public function __construct(string $logFile, bool $enabled = true) {
		$this->logFile = $logFile;
		$this->enabled = $enabled;
	}

	/**
	 * Log a message with a specific level
	 *
	 * @param string $level
	 * @param string $message
	 * @param array $context
	 */
	private function log(string $level, string $message, array $context = []): void {
		if (!$this->enabled) {
			return;
		}

		$timestamp = date('Y-m-d H:i:s');
		$contextString = !empty($context) ? ' ' . json_encode($context) : '';
		$logMessage = "[{$timestamp}] [{$level}] {$message}{$contextString}\n";

		// Write to file
		@file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Log debug message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function debug(string $message, array $context = []): void {
		$this->log('DEBUG', $message, $context);
	}

	/**
	 * Log info message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function info(string $message, array $context = []): void {
		$this->log('INFO', $message, $context);
	}

	/**
	 * Log warning message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function warning(string $message, array $context = []): void {
		$this->log('WARNING', $message, $context);
	}

	/**
	 * Log error message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function error(string $message, array $context = []): void {
		$this->log('ERROR', $message, $context);
	}

	/**
	 * Log critical message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function critical(string $message, array $context = []): void {
		$this->log('CRITICAL', $message, $context);
	}
}

