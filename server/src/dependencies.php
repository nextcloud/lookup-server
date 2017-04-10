<?php

$container['db'] = function($c) {
	$db = $c['settings']['db'];
	$pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
		$db['user'], $db['pass']);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	return $pdo;
};
$container['UserManager'] = function($c) {
	return new \LookupServer\UserManager($c->db, $c->EmailValidator);
};
$container['EmailValidator'] = function($c) {
	return new \LookupServer\Validator\Email(
		$c->db,
		$c->router,
		$c->settings['host'],
		$c->settings['emailfrom']
	);
};
$container['Status'] = function($c) {
	return new \LookupServer\Status();
};
$container['Replication'] = function ($c) {
	return new \LookupServer\Replication($c->db, $c->settings['replication_auth']);
};
