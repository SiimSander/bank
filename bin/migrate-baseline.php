#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/migrations.php';

echo "Marking all migration files as applied (baseline)...\n";

try {
	$pdo = db();
} catch (Throwable $e) {
	fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);

	exit(1);
}

$marked = markAllMigrationsApplied($pdo);

echo "Baseline complete. Marked {$marked} migration(s).\n";
