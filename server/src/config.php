<?php

require __DIR__ . '/../config/config.php';

return [
	'settings' => [
		'displayErrorDetails' => true,
		'addContentLengthHeader' => true,
		'db' => [
			'host' => $CONFIG['DB']['host'],
			'user' => $CONFIG['DB']['user'],
			'pass' => $CONFIG['DB']['pass'],
			'dbname' => $CONFIG['DB']['db'],
		],
		'host' => $CONFIG['PUBLIC_URL'],
		'emailfrom' => $CONFIG['EMAIL_SENDER'],
		'replication_auth' => $CONFIG['REPLICATION_AUTH'],
		'replication_hosts' => $CONFIG['REPLICATION_HOSTS'],
		'global_scale' => $CONFIG['GLOBAL_SCALE'],
		'auth_key' => $CONFIG['AUTH_KEY'],
		'twitter' => [
			'consumer_key' => $CONFIG['TWITTER']['CONSUMER_KEY'],
			'consumer_secret' => $CONFIG['TWITTER']['CONSUMER_SECRET'],
			'access_token' => $CONFIG['TWITTER']['ACCESS_TOKEN'],
			'access_token_secret' => $CONFIG['TWITTER']['ACCESS_TOKEN_SECRET'],
		],
		'instances' => $CONFIG['INSTANCES']
	]
];
