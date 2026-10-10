#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/bank.php';

try {
	$pdo = db();
} catch (Throwable $e) {
	fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);

	exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);
$report = backfillStockTickers($pdo, $dryRun);

foreach ($report as $line) {
	echo ($dryRun ? '[dry-run] ' : '') . $line . PHP_EOL;
}

echo count($report) === 0 ? "Nothing to change.\n" : sprintf("%d stock note(s) %s.\n", count($report), $dryRun ? 'would be rewritten' : 'rewritten');
