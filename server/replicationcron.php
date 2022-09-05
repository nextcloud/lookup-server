<?php

use LookupServer\Replication;

require __DIR__ . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
	return;
}


require __DIR__ . '/init.php';

if (!isset($app) || !isset($container)) {
	return;
}

/** @var Replication $replication */
$replication = $container->get('Replication');
$replication->import();
