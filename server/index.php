<?php

require 'vendor/autoload.php';

$settings = require __DIR__ . '/src/config.php';

$container = new \Slim\Container($settings);

require __DIR__ . '/src/dependencies.php';

$container['BruteForceMiddleware'] = function ($c) {
	return new \LookupServer\BruteForceMiddleware($c->db);
};

$app = new \Slim\App($container);
$app->add($container->get('BruteForceMiddleware'));


$app->get('/users', 'UserManager:search');
$app->post('/users', 'UserManager:register');
$app->get('/validate/email/{token}', 'EmailValidator:validate');

$app->run();
