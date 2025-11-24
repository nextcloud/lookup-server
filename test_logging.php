<?php
/**
 * Test script to verify logging functionality
 */

require __DIR__ . '/init.php';

if (!isset($container)) {
	die("Container not initialized\n");
}

echo "Testing logging system...\n\n";

/** @var \LookupServer\Logger $logger */
$logger = $container->get('Logger');

// Test different log levels
$logger->debug('This is a debug message', ['test' => 'value']);
$logger->info('This is an info message', ['user' => 'test@example.com']);
$logger->warning('This is a warning message');
$logger->error('This is an error message', ['code' => 500]);
$logger->critical('This is a critical message');

echo "✓ Log messages written successfully!\n";
echo "Check the log file at: /var/log/lookup/lookup.log\n\n";

// Display log content
if (file_exists('/var/log/lookup/lookup.log')) {
	echo "Current log content:\n";
	echo "-------------------\n";
	echo file_get_contents('/var/log/lookup/lookup.log');
} else {
	echo "⚠ Log file not found - check permissions!\n";
}

