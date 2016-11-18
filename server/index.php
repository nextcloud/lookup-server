<?php

require 'vendor/autoload.php';

$settings = require('config.php');

$container = new \Slim\Container($settings);

$container['db'] = function($c) {
	$db = $c['settings']['db'];
	$pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
		$db['user'], $db['pass']);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	return $pdo;
};
$container['UserManager'] = function($c) {
	return new \LookupServer\UserManager($c->db);
};
$container['BruteForceMiddleware'] = function ($c) {
	return new \LookupServer\BruteForceMiddleware($c->db);
};

$app = new \Slim\App($container);
$app->add($container->get('BruteForceMiddleware'));


$app->get('/users', 'UserManager:search');
$app->post('/users', 'UserManager:register');

$app->run();
