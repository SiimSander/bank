#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/AppConfig.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3308);
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: 'root';
$dbName = getenv('DB_NAME') ?: 'php_learn_test';

echo "Setting up test database `{$dbName}` on {$host}:{$port}...\n";

try {
	$serverPdo = new PDO(
		sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
		$user,
		$password,
		[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
	);
} catch (PDOException $e) {
	fwrite(STDERR, 'MySQL connection failed: ' . $e->getMessage() . PHP_EOL);
	exit(1);
}

$escapedDbName = str_replace('`', '``', $dbName);
$serverPdo->exec("DROP DATABASE IF EXISTS `{$escapedDbName}`");
$serverPdo->exec(
	sprintf(
		'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
		$escapedDbName
	)
);

$tablesSql = $root . '/db/tables.sql';
if (!is_file($tablesSql)) {
	fwrite(STDERR, "Missing schema file: {$tablesSql}\n");
	exit(1);
}

$pdo = new PDO(
	sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName),
	$user,
	$password,
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$schema = file_get_contents($tablesSql);
if ($schema === false) {
	fwrite(STDERR, "Could not read {$tablesSql}\n");
	exit(1);
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
	if ($statement !== '') {
		$pdo->exec($statement);
	}
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

putenv("DB_HOST={$host}");
putenv("DB_PORT={$port}");
putenv("DB_NAME={$dbName}");
putenv("DB_USER={$user}");
putenv("DB_PASSWORD={$password}");
putenv('APP_ENV=testing');
putenv('MAIL_FROM_EMAIL=test@localhost.dev');
putenv('APP_URL=http://localhost:8000');

require_once $root . '/src/bootstrap.php';
require_once $root . '/src/database.php';
require_once $root . '/src/migrations.php';

AppConfig::overrideForTesting([
	'DB_HOST' => $host,
	'DB_PORT' => (string) $port,
	'DB_NAME' => $dbName,
	'DB_USER' => $user,
	'DB_PASSWORD' => $password,
	'APP_ENV' => 'testing',
	'MAIL_FROM_EMAIL' => 'test@localhost.dev',
	'APP_URL' => 'http://localhost:8000',
]);
dbReset();

$marked = markAllMigrationsApplied(db());
$migrationExitCode = runMigrationsUp(db(), false);

echo "Schema imported from db/tables.sql\n";
echo "Baseline marked {$marked} migration(s).\n";
if ($migrationExitCode !== 0) {
	fwrite(STDERR, "Pending migrations failed to apply.\n");
	exit(1);
}
echo "Test database ready.\n";
