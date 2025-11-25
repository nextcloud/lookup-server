<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace LookupServer;


use DI\Container;
use LookupServer\Service\DependenciesService;
use Slim\Factory\AppFactory;


define('VERSION', '1.1.2');


require __DIR__ . '/vendor/autoload.php';

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath('');

$settings = require __DIR__ . '/src/config.php';
$container->set('Settings', function (Container $c) use ($settings) {
	return $settings;
});

$container->set('DependenciesService', function (Container $c) {
	return new DependenciesService($c->get('Settings'));
});
$container->get('DependenciesService')->initContainer($container, $app);

// Add error middleware for logging
$errorMiddleware = $app->addErrorMiddleware(
	$settings['settings']['displayErrorDetails'] ?? true,
	true,  // logErrors
	true   // logErrorDetails
);

// Set custom error handler that logs to our Logger
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->registerErrorRenderer('text/html', function ($exception, $displayErrorDetails) use ($container) {
	/** @var \LookupServer\Logger $logger */
	$logger = $container->get('Logger');
	$logger->error('Exception: ' . $exception->getMessage(), [
		'file' => $exception->getFile(),
		'line' => $exception->getLine(),
		'trace' => $exception->getTraceAsString()
	]);

	$message = $displayErrorDetails
		? sprintf('Error: %s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine())
		: 'An error occurred';

	return "<html><body><h1>Error</h1><p>{$message}</p></body></html>";
});

