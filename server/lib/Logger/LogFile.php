<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace LookupServer\Logger;

use JsonException;
use LookupServer\Exceptions\LoggerException;
use LookupServer\Tools\Traits\TArrayTools;

class LogFile implements ILogWriter {
	use TArrayTools;

	private ?string $logFile = null;
	private ?int $logFileMode = null;

	public function __construct(
		private array $settings = [],
	) {
	}

	/**
	 * @throws LoggerException
	 */
	private function getLogFile(): string {
		if ($this->logFile === null) {
			$path = $this->settings['settings']['log']['file'] ?? '';
			if ($path === '') {
				throw new LoggerException('log disabled');
			}
			$this->logFile = $path;
		}
		return $this->logFile;
	}

	public function write(string $entry): void {
		try {
			$handle = @fopen($this->getLogFile(), 'a');
		} catch (LoggerException) {
			return;
		}

		if ($this->logFileMode === null) {
			$this->logFileMode = $this->settings['settings']['log']['file_mode'] ?? 0640;
		}

		if ($this->logFileMode > 0 && is_file($this->logFile) && (fileperms($this->logFile) & 0777) !== $this->logFileMode) {
			@chmod($this->logFile, $this->logFileMode);
		}

		if ($handle) {
			fwrite($handle, $entry . "\n");
			fclose($handle);
		} else {
			error_log($entry);
 		}
	}
}
