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

define('VERSION', '1.1.3.1');

require __DIR__ . '/vendor/autoload.php';
$settings = require __DIR__ . '/src/config.php';

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

$container->set('Settings', function (Container $c) use ($settings) {
	return $settings;
});

$basePath = $settings['settings']['base_path'] ?? '';
$app->setBasePath($basePath);

$container->set('DependenciesService', function (Container $c) {
	return new DependenciesService($c->get('Settings'));
});
$container->get('DependenciesService')->initContainer($container, $app);
