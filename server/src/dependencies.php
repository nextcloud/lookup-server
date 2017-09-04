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
	return new \LookupServer\UserManager($c->db, $c->EmailValidator, $c->WebsiteValidator, $c->TwitterValidator, $c->SignatureHandler, $c['settings']['global_scale'], $c['settings']['auth_key']);
};
$container['SignatureHandler'] = function($c) {
	return new \LookupServer\SignatureHandler();
};
$container['TwitterOAuth'] = function($c) {
	$twitterConf = $c['settings']['twitter'];
	return new \Abraham\TwitterOAuth\TwitterOAuth(
		$twitterConf['consumer_key'],
		$twitterConf['consumer_secret'],
		$twitterConf['access_token'],
		$twitterConf['access_token_secret']
	);
};

$container['EmailValidator'] = function($c) {
	return new \LookupServer\Validator\Email(
		$c->db,
		$c->router,
		$c->settings['host'],
		$c->settings['emailfrom']
	);
};
$container['WebsiteValidator'] = function($c) {
	return new \LookupServer\Validator\Website($c->SignatureHandler);
};
$container['TwitterValidator'] = function($c) {
	return new \LookupServer\Validator\Twitter($c->TwitterOAuth, $c->SignatureHandler, $c->db);
};
$container['Status'] = function($c) {
	return new \LookupServer\Status();
};
$container['Replication'] = function ($c) {
	return new \LookupServer\Replication($c->db, $c->settings['replication_auth'], $c->settings['replication_hosts']);
};
