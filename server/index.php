<?php

require __DIR__ . '/vendor/autoload.php';

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
$app->post('/gs/users', 'UserManager:batchRegister');
$app->delete('/gs/users', 'UserManager:batchDelete');
$app->delete('/users', 'UserManager:delete');
$app->get('/validate/email/{token}', 'EmailValidator:validate')->setName('validateEmail');
$app->get('/status', 'Status:status');

$app->get('/replication', 'Replication:export');

$app->run();
