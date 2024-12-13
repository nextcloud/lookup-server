<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require __DIR__ . '/../config/config.php';

return [
	'settings' => [
		'displayErrorDetails' => true,
		'addContentLengthHeader' => true,
		'db' => [
			'host' => $CONFIG['DB']['host'] ?? 'localhost',
			'user' => $CONFIG['DB']['user'] ?? 'lookup',
			'pass' => $CONFIG['DB']['pass'] ?? 'lookup',
			'dbname' => $CONFIG['DB']['db'] ?? 'lookup',
		],
		'host' => $CONFIG['PUBLIC_URL'] ?? 'http://dev/nextcloud/lookup-server',
		'emailfrom' => $CONFIG['EMAIL_SENDER'] ?? 'admin@example.com',
		'replication_auth' => $CONFIG['REPLICATION_AUTH'] ?? '',
		'replication_hosts' => $CONFIG['REPLICATION_HOSTS'] ?? '',
		'global_scale' => $CONFIG['GLOBAL_SCALE'] ?? false,
		'auth_key' => $CONFIG['AUTH_KEY'] ?? '',
		'twitter' => [
			'consumer_key' => $CONFIG['TWITTER']['CONSUMER_KEY'] ?? '',
			'consumer_secret' => $CONFIG['TWITTER']['CONSUMER_SECRET'] ?? '',
			'access_token' => $CONFIG['TWITTER']['ACCESS_TOKEN'] ?? '',
			'access_token_secret' => $CONFIG['TWITTER']['ACCESS_TOKEN_SECRET'] ?? '',
		],
		'instances' => $CONFIG['INSTANCES'] ?? []
	]
];
