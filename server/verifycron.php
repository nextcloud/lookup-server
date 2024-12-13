<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use LookupServer\UserManager;

require __DIR__ . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
	return;
}


require __DIR__ . '/init.php';

if (!isset($app) || !isset($container)) {
	return;
}

/** @var UserManager $userManager */
$userManager = $container->get('UserManager');
$userManager->verify();


$app->map(['GET'], '/verify', 'UserManager:verify');
$app->run();
