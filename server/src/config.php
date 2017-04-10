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
	]
];
