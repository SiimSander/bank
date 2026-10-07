#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$config = AppConfig::databaseConfig();
$backupDir = AppConfig::resolvePath('storage/backups');

if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
	fwrite(STDERR, "Could not create backup directory: {$backupDir}\n");

	exit(1);
}

$timestamp = gmdate('Y-m-d_His');
$outputFile = $backupDir . '/php_learn_' . $timestamp . '.sql';

$command = sprintf(
	'mysqldump --host=%s --port=%d --user=%s %s %s > %s',
	escapeshellarg($config['host']),
	$config['port'],
	escapeshellarg($config['user']),
	$config['password'] !== '' ? '--password=' . escapeshellarg($config['password']) : '',
	escapeshellarg($config['dbname']),
	escapeshellarg($outputFile)
);

exec($command, $output, $exitCode);

if ($exitCode !== 0 || !is_file($outputFile) || filesize($outputFile) === 0) {
	fwrite(STDERR, "Backup failed.\n");
	logger()?->error('backup', 'Database backup failed', [
		'exit_code' => $exitCode,
		'output_file' => $outputFile,
	]);

	exit(1);
}

$gzipCommand = sprintf('gzip -f %s', escapeshellarg($outputFile));
exec($gzipCommand, $gzipOutput, $gzipExitCode);

if ($gzipExitCode !== 0) {
	fwrite(STDERR, "Backup created but gzip failed: {$outputFile}\n");

	exit(1);
}

$gzipFile = $outputFile . '.gz';
echo "Backup written to {$gzipFile}\n";
logger()?->info('backup', 'Database backup created', [
	'file' => $gzipFile,
	'size_bytes' => filesize($gzipFile),
]);
