<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace LookupServer\Service;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use LookupServer\Exceptions\LoggerException;
use LookupServer\Logger\LogFile;
use LookupServer\Tools\Traits\TArrayTools;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Factory\ServerRequestCreatorFactory;
use Stringable;
use Throwable;

class LoggerService implements LoggerInterface {
	use TArrayTools;

	private ?ServerRequestInterface $request = null;
	private ?string $requestId = null;

	public function __construct(
		private array $settings,
		private LogFile $logFile,
	) {
	}

	public function debug(string|Stringable $message, array $context = []): void {
		$this->log(0, $message, $context);
	}

	public function info(string|Stringable $message, array $context = array()): void {
		$this->log(1, $message, $context);
	}

	public function notice(string|Stringable $message, array $context = []): void {
		$this->log(2, $message, $context);
	}

	public function warning(string|Stringable $message, array $context = []): void {
		$this->log(3, $message, $context);
	}

	public function error(string|Stringable $message, array $context = []): void {
		$this->log(4, $message, $context);
	}

	public function critical(string|Stringable $message, array $context = []): void {
		$this->log(5, $message, $context);
	}

	public function alert(string|Stringable $message, array $context = []): void {
		$this->log(6, $message, $context);
	}

	public function emergency(string|Stringable $message, array $context = []): void {
		$this->log(7, $message, $context);
	}

	public function log($level, string|Stringable $message, array $context = []): void {
		if (($this->settings['settings']['log']['enabled'] ?? false) !== true) {
			return;
		}

		if (!$this->fitLogLevel($level)) {
			return;
		}

		$this->write($level, (string)$message, $context);
	}

	private function write(int $level, string $message, array $context = []): void {
		$this->logFile->write($this->generateCompleteData($level, $message, $context));
	}

	private function fitLogLevel(int $level): bool {
		try {
			$logLevel = $this->getLogLevel();
		} catch (LoggerException $e) {
			$this->write(6, 'cannot extract log level', ['exception' => $e]);
			$logLevel = 2;
		}
		return ($level >= $logLevel);
	}

	/**
	 * @throws LoggerException if level is set but not an integer
	 */
	private function getLogLevel(): int {
		$logLevel = $this->settings['settings']['log']['level'] ?? 2;
		if (is_int($logLevel)) {
			return $logLevel;
		}
		throw new LoggerException('misconfigured log_level');
	}

	private function generateCompleteData(int $level, string $message, array $context = []): string {
		$format = $this->settings['settings']['log']['date_format'] ?? DateTimeInterface::ATOM;
		$logTimeZone = $this->settings['settings']['log']['date_timezone'] ?? 'UTC';

		try {
			$timezone = new DateTimeZone($logTimeZone);
		} catch (\Exception) {
			$timezone = new DateTimeZone('UTC');
		}

		$time = DateTime::createFromFormat('U.u', number_format(microtime(true), 4, '.', ''));
		if ($time !== false) {
			$time->setTimezone($timezone);
		} else {
			$time = new DateTime('now', $timezone);
		}
		$time = $time->format($format);

		[$reqId, $method, $url, $remoteAddr, $userAgent] = $this->getRequestDetails();
		$version = VERSION;

		$entry = compact(
			'reqId',
			'level',
			'time',
			'remoteAddr',
			'method',
			'url',
			'message',
			'userAgent',
			'version'
		);

		return $this->convertToSafeJson($this->applyContext($entry, $context));
	}

	private function getRequestDetails(): array {
		if ($this->request === null) {
			$serverRequestCreator = ServerRequestCreatorFactory::create();
			$this->request = $serverRequestCreator->createServerRequestFromGlobals();
			$this->requestId = $this->generateUniqueId();
		}

		return [
			$this->requestId,
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['REQUEST_URI'],
			$_SERVER['REMOTE_ADDR'],
			$_SERVER['HTTP_USER_AGENT']
		];
	}

	private function generateUniqueId(): string {
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$length = 12;

		$charsLength = strlen($chars) - 1;
		$randomString = '';
		while ($length > 0) {
			$randomString .= $chars[random_int(0, $charsLength)];
			$length--;
		}
		return $randomString;
	}

	public function convertToSafeJson(array $data): string {
		// ensure data are UTF-8 compliant
		foreach ($data as $key => $value) {
			if (is_string($value)) {
				try {
					json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
				} catch (JsonException) {
					$data[$key] = mb_convert_encoding($value, 'UTF-8', mb_detect_encoding($value));
				}
			}
		}

		return json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES);
	}

	public function applyContext(array $entry, array $context): array {
		$exception = $context['exception'] ?? null;
		if ($exception instanceof Throwable) {
			$context['exception'] = [
				'exception' => get_class($exception),
				'file' => $exception->getFile(),
				'line' => $exception->getLine(),
				'message' => $exception->getMessage(),
				'code' => $exception->getCode(),
				'trace' => (($this->settings['settings']['log']['hide_backtrace'] ?? false) !== true) ? $exception->getTrace() : null,
			];
		}

		return array_merge($entry, $context);
	}
}
