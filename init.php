<?php

declare(strict_types=1);


/**
 * lookup-server - Standalone Lookup Server.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2022
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


