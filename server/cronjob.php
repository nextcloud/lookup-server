<?php

require __DIR__ . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
	return;
}

$env = \Slim\Http\Environment::mock(['REQUEST_URI' => '/import']);

$settings = require __DIR__ . '/src/config.php';
$settings['environment'] = $env;
$container = new \Slim\Container($settings);
require __DIR__ . '/src/dependencies.php';

$app = new \Slim\App($container);

$app->map(['GET'], '/import', 'Replication:import');
$app->run();

