<?php
/**
 * Simple test endpoint to verify HTTP logging
 */

require __DIR__ . '/init.php';

header('Content-Type: text/plain');

if (!isset($container)) {
	die("ERROR: Container not initialized\n");
}

/** @var \LookupServer\Logger $logger */
try {
	$logger = $container->get('Logger');
	$logger->info('HTTP Test endpoint accessed', [
		'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
		'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
		'timestamp' => date('Y-m-d H:i:s')
	]);
	echo "✓ Log written successfully!\n";
	echo "Check: /var/log/lookup/lookup.log\n";
} catch (Exception $e) {
	echo "✗ ERROR: " . $e->getMessage() . "\n";
}
