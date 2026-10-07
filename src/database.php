<?php

require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/Logger.php';

function dbReset(): void {
	$GLOBALS['__php_learn_pdo'] = null;
}

function db(): PDO {
	if (($GLOBALS['__php_learn_pdo'] ?? null) instanceof PDO) {
		return $GLOBALS['__php_learn_pdo'];
	}

	AppConfig::load();
	$config = AppConfig::databaseConfig();

	$dsn = sprintf(
		'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
		$config['host'],
		$config['port'],
		$config['dbname']
	);

	try {
		$GLOBALS['__php_learn_pdo'] = new PDO($dsn, $config['user'], $config['password'], [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
	} catch (PDOException $e) {
		logger()?->error('db', 'Database connection failed', [
			'host' => $config['host'],
			'port' => $config['port'],
			'dbname' => $config['dbname'],
			'message' => $e->getMessage(),
		]);

		throw $e;
	}

	return $GLOBALS['__php_learn_pdo'];
}
