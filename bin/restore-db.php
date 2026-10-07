#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$fileArg = null;
$confirmed = false;

foreach ($argv as $index => $arg) {
	if ($arg === '--file' && isset($argv[$index + 1])) {
		$fileArg = $argv[$index + 1];
	}
	if ($arg === '--yes') {
		$confirmed = true;
	}
}

if ($fileArg === null) {
	fwrite(STDERR, "Usage: php bin/restore-db.php --file storage/backups/php_learn_YYYY-MM-DD_HHMMSS.sql.gz --yes\n");

	exit(1);
}

$filePath = AppConfig::resolvePath($fileArg);

if (!is_file($filePath)) {
	fwrite(STDERR, "Backup file not found: {$filePath}\n");

	exit(1);
}

if (!$confirmed) {
	fwrite(STDERR, "Restore overwrites the live database. Re-run with --yes to confirm.\n");

	exit(1);
}

$config = AppConfig::databaseConfig();
$importFile = $filePath;

if (str_ends_with($filePath, '.gz')) {
	$importFile = sys_get_temp_dir() . '/php_learn_restore_' . bin2hex(random_bytes(4)) . '.sql';
	$gzipCommand = sprintf('gunzip -c %s > %s', escapeshellarg($filePath), escapeshellarg($importFile));
	exec($gzipCommand, $output, $exitCode);

	if ($exitCode !== 0 || !is_file($importFile)) {
		fwrite(STDERR, "Could not decompress backup file.\n");

		exit(1);
	}
}

$command = sprintf(
	'mysql --host=%s --port=%d --user=%s %s %s < %s',
	escapeshellarg($config['host']),
	$config['port'],
	escapeshellarg($config['user']),
	$config['password'] !== '' ? '--password=' . escapeshellarg($config['password']) : '',
	escapeshellarg($config['dbname']),
	escapeshellarg($importFile)
);

exec($command, $restoreOutput, $restoreExitCode);

if (str_ends_with($filePath, '.gz') && is_file($importFile)) {
	unlink($importFile);
}

if ($restoreExitCode !== 0) {
	fwrite(STDERR, "Restore failed.\n");
	logger()?->error('backup', 'Database restore failed', [
		'file' => $filePath,
		'exit_code' => $restoreExitCode,
	]);

	exit(1);
}

echo "Restore completed from {$filePath}\n";
logger()?->warning('backup', 'Database restored from backup', [
	'file' => $filePath,
]);
