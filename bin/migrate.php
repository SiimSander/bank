#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/migrations.php';

try {
	$pdo = db();
} catch (Throwable $e) {
	fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);

	exit(1);
}

$command = $argv[1] ?? '';
$dryRun = in_array('--dry-run', $argv, true);

switch ($command) {
	case 'status':
		exit(runMigrationStatus($pdo));
	case 'up':
		exit(runMigrationsUp($pdo, $dryRun));
	default:
		printMigrationUsage();
		exit(1);
}
