<?php

require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/Logger.php';

function dbReset(): void {
	$GLOBALS['__php_learn_pdo'] = null;
}

/** @return array<int, mixed> */
function dbSslOptions(): array {
	if (!filter_var(AppConfig::get('DB_SSL', 'false'), FILTER_VALIDATE_BOOLEAN)) {
		return [];
	}

	$caPem = str_replace('\n', "\n", AppConfig::get('DB_SSL_CA_PEM', '') ?? '');
	$caPath = AppConfig::get('DB_SSL_CA', '') ?? '';

	if ($caPem !== '') {
		$caPath = sys_get_temp_dir() . '/php-learn-db-ca-' . md5($caPem) . '.pem';

		if (!is_file($caPath)) {
			file_put_contents($caPath, $caPem);
		}
	} elseif ($caPath !== '') {
		$caPath = AppConfig::resolvePath($caPath);
	} else {
		foreach (['/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/cert.pem'] as $candidate) {
			if (is_file($candidate)) {
				$caPath = $candidate;
				break;
			}
		}
	}

	if ($caPath === '') {
		throw new RuntimeException('DB_SSL is enabled but no CA bundle was found. Set DB_SSL_CA or DB_SSL_CA_PEM.');
	}

	return [PDO::MYSQL_ATTR_SSL_CA => $caPath];
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
		] + dbSslOptions());
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
