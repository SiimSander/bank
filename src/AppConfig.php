<?php

final class AppConfig {
	/** Keys that real environment variables (e.g. on Vercel) set or override on top of .env. */
	private const PROCESS_ENV_KEYS = [
		'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_NAME', 'TRUST_PROXY',
		'LEGAL_CONTROLLER_NAME', 'LEGAL_REGISTRY_CODE', 'LEGAL_ADDRESS', 'LEGAL_EMAIL', 'LEGAL_COUNTRY',
		'LEGAL_HOSTING_PROVIDER', 'LEGAL_SMTP_PROVIDER',
		'LEGAL_PRIVACY_VERSION', 'LEGAL_TERMS_VERSION', 'LEGAL_BANK_AIS_VERSION',
		'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
		'DB_SSL', 'DB_SSL_CA', 'DB_SSL_CA_PEM',
		'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_EMAIL', 'MAIL_FROM_NAME',
		'ENABLE_BANKING_ENV', 'ENABLE_BANKING_APP_ID', 'ENABLE_BANKING_PRIVATE_KEY_PATH',
		'ENABLE_BANKING_PRIVATE_KEY', 'ENABLE_BANKING_REDIRECT_URL',
		'LOG_PATH', 'LOG_LEVEL', 'LOG_TO_STDERR',
		'SESSION_DRIVER',
	];

	private static bool $loaded = false;
	private static string $rootPath = '';
	/** @var array<string, string> */
	private static array $values = [];

	public static function load(?string $rootPath = null): void {
		if (self::$loaded) {
			return;
		}

		self::$rootPath = $rootPath ?? dirname(__DIR__);
		$envPath = self::$rootPath . '/.env';

		if (is_file($envPath)) {
			self::$values = self::parseEnvFile($envPath);
		}

		self::loadProcessEnvironment();
		self::loadLegacyLocalPhp();

		if (!is_file($envPath) && self::$values === []) {
			static $legacyWarningShown = false;
			if (!$legacyWarningShown) {
				error_log('AppConfig: using legacy *.local.php files. Copy .env.example to .env for production.');
				$legacyWarningShown = true;
			}
		}

		self::applyDefaults();
		self::validateProduction();
		self::$loaded = true;
	}

	public static function rootPath(): string {
		self::ensureLoaded();

		return self::$rootPath;
	}

	public static function get(string $key, ?string $default = null): ?string {
		self::ensureLoaded();

		return self::$values[$key] ?? $default;
	}

	/** @param array<string, string> $values */
	public static function overrideForTesting(array $values): void {
		self::ensureLoaded();
		self::$values = array_merge(self::$values, $values);
	}

	public static function require(string $key): string {
		$value = self::get($key);

		if ($value === null || $value === '') {
			throw new RuntimeException("Missing required config key: {$key}");
		}

		return $value;
	}

	public static function env(): string {
		return self::get('APP_ENV', 'local') ?? 'local';
	}

	public static function isProduction(): bool {
		return self::env() === 'production';
	}

	public static function isDebug(): bool {
		$debug = self::get('APP_DEBUG');

		if ($debug === null) {
			return !self::isProduction();
		}

		return filter_var($debug, FILTER_VALIDATE_BOOLEAN);
	}

	public static function isRequestSecure(): bool {
		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
			return true;
		}

		if (filter_var(self::get('TRUST_PROXY', 'false'), FILTER_VALIDATE_BOOLEAN)) {
			$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

			return strtolower($forwardedProto) === 'https';
		}

		return false;
	}

	public static function resolvePath(string $path): string {
		if ($path === '') {
			return self::rootPath();
		}

		if ($path[0] === '/') {
			return $path;
		}

		return self::rootPath() . '/' . $path;
	}

	/** @return array<string, string> */
	public static function databaseConfig(): array {
		return [
			'host' => self::require('DB_HOST'),
			'port' => (int) (self::get('DB_PORT', '3306') ?? '3306'),
			'dbname' => self::require('DB_NAME'),
			'user' => self::require('DB_USER'),
			'password' => self::get('DB_PASSWORD', '') ?? '',
		];
	}

	/** @return array<string, mixed> */
	public static function mailConfig(): array {
		return [
			'host' => self::get('MAIL_HOST', '') ?? '',
			'port' => (int) (self::get('MAIL_PORT', '587') ?? '587'),
			'username' => self::get('MAIL_USERNAME', '') ?? '',
			'password' => self::get('MAIL_PASSWORD', '') ?? '',
			'from_email' => self::require('MAIL_FROM_EMAIL'),
			'from_name' => self::get('MAIL_FROM_NAME', 'PhpLearn') ?? 'PhpLearn',
			'base_url' => rtrim(self::require('APP_URL'), '/'),
		];
	}

	/** @return array<string, string> */
	public static function legalConfig(): array {
		return [
			'controller_name' => self::get('LEGAL_CONTROLLER_NAME', '[Data controller name not configured]') ?? '[Data controller name not configured]',
			'registry_code' => self::get('LEGAL_REGISTRY_CODE', '') ?? '',
			'address' => self::get('LEGAL_ADDRESS', '[Contact address not configured]') ?? '[Contact address not configured]',
			'email' => self::get('LEGAL_EMAIL', self::get('MAIL_FROM_EMAIL', 'privacy@example.com') ?? 'privacy@example.com') ?? 'privacy@example.com',
			'country' => self::get('LEGAL_COUNTRY', 'EE') ?? 'EE',
			'hosting_provider' => self::get('LEGAL_HOSTING_PROVIDER', '[Hosting provider not configured]') ?? '[Hosting provider not configured]',
			'smtp_provider' => self::get('LEGAL_SMTP_PROVIDER', '[Email provider not configured]') ?? '[Email provider not configured]',
			'privacy_version' => self::get('LEGAL_PRIVACY_VERSION', '2026-09-19') ?? '2026-09-19',
			'terms_version' => self::get('LEGAL_TERMS_VERSION', '2026-09-19') ?? '2026-09-19',
			'bank_ais_version' => self::get('LEGAL_BANK_AIS_VERSION', '2026-09-19') ?? '2026-09-19',
		];
	}

	/** @return array<string, string> `private_key` holds the inline PEM, `private_key_path` the file fallback. */
	public static function enableBankingConfig(): array {
		$inlineKey = str_replace('\n', "\n", self::get('ENABLE_BANKING_PRIVATE_KEY', '') ?? '');
		$keyPath = $inlineKey === '' ? self::require('ENABLE_BANKING_PRIVATE_KEY_PATH') : (self::get('ENABLE_BANKING_PRIVATE_KEY_PATH', '') ?? '');

		return [
			'application_id' => self::require('ENABLE_BANKING_APP_ID'),
			'private_key_path' => $keyPath === '' ? '' : self::resolvePath($keyPath),
			'private_key' => $inlineKey,
			'redirect_url' => self::require('ENABLE_BANKING_REDIRECT_URL'),
		];
	}

	/** @return array<string, string> */
	private static function parseEnvFile(string $path): array {
		$vars = [];

		foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
			$line = trim($line);

			if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
				continue;
			}

			[$key, $value] = explode('=', $line, 2);
			$key = trim($key);
			$value = trim($value);

			if (
				(str_starts_with($value, '"') && str_ends_with($value, '"'))
				|| (str_starts_with($value, "'") && str_ends_with($value, "'"))
			) {
				$value = substr($value, 1, -1);
			}

			$vars[$key] = $value;
		}

		return $vars;
	}

	private static function loadProcessEnvironment(): void {
		foreach (self::PROCESS_ENV_KEYS as $key) {
			$value = getenv($key);

			if ($value !== false && $value !== '') {
				self::$values[$key] = $value;
			}
		}
	}

	private static function setLegacyValue(string $key, string $value): void {
		if (!isset(self::$values[$key]) || self::$values[$key] === '') {
			self::$values[$key] = $value;
		}
	}

	private static function loadLegacyLocalPhp(): void {
		$dbPath = __DIR__ . '/database.local.php';
		if (is_file($dbPath)) {
			$db = require $dbPath;
			self::setLegacyValue('DB_HOST', (string) ($db['host'] ?? '127.0.0.1'));
			self::setLegacyValue('DB_PORT', (string) ($db['port'] ?? '3306'));
			self::setLegacyValue('DB_NAME', (string) ($db['dbname'] ?? 'php_learn'));
			self::setLegacyValue('DB_USER', (string) ($db['user'] ?? 'root'));
			self::setLegacyValue('DB_PASSWORD', (string) ($db['password'] ?? ''));
		}

		$mailPath = __DIR__ . '/mail.local.php';
		if (is_file($mailPath)) {
			$mail = require $mailPath;
			self::setLegacyValue('MAIL_HOST', (string) ($mail['host'] ?? ''));
			self::setLegacyValue('MAIL_PORT', (string) ($mail['port'] ?? '587'));
			self::setLegacyValue('MAIL_USERNAME', (string) ($mail['username'] ?? ''));
			self::setLegacyValue('MAIL_PASSWORD', (string) ($mail['password'] ?? ''));
			self::setLegacyValue('MAIL_FROM_EMAIL', (string) ($mail['from_email'] ?? 'noreply@localhost.dev'));
			self::setLegacyValue('MAIL_FROM_NAME', (string) ($mail['from_name'] ?? 'PhpLearn'));
			self::setLegacyValue('APP_URL', rtrim((string) ($mail['base_url'] ?? 'http://localhost:8000'), '/'));
		}

		$ebPath = __DIR__ . '/enableBanking.local.php';
		if (is_file($ebPath)) {
			$eb = require $ebPath;
			$environment = (string) ($eb['environment'] ?? 'sandbox');
			$profile = $eb[$environment] ?? $eb['sandbox'] ?? [];
			self::setLegacyValue('ENABLE_BANKING_ENV', $environment);
			self::setLegacyValue('ENABLE_BANKING_APP_ID', (string) ($profile['application_id'] ?? ''));
			self::setLegacyValue('ENABLE_BANKING_PRIVATE_KEY_PATH', (string) ($profile['private_key_path'] ?? ''));
			self::setLegacyValue('ENABLE_BANKING_REDIRECT_URL', (string) ($profile['redirect_url'] ?? ''));
		}
	}

	private static function applyDefaults(): void {
		self::$values['APP_ENV'] ??= 'local';
		$isProduction = self::$values['APP_ENV'] === 'production';
		self::$values['APP_DEBUG'] ??= $isProduction ? 'false' : 'true';
		self::$values['APP_URL'] ??= 'http://localhost:8000';
		self::$values['LOG_PATH'] ??= 'storage/logs/app.log';
		self::$values['LOG_LEVEL'] ??= $isProduction ? 'warning' : 'debug';
		self::$values['TRUST_PROXY'] ??= 'false';
		self::$values['MAIL_FROM_EMAIL'] ??= 'noreply@localhost.dev';
		self::$values['MAIL_FROM_NAME'] ??= 'PhpLearn';
		self::$values['APP_NAME'] ??= 'Wins & Bank';
		self::$values['LEGAL_COUNTRY'] ??= 'EE';
		self::$values['LEGAL_PRIVACY_VERSION'] ??= '2026-09-19';
		self::$values['LEGAL_TERMS_VERSION'] ??= '2026-09-19';
		self::$values['LEGAL_BANK_AIS_VERSION'] ??= '2026-09-19';
		self::$values['MAIL_PORT'] ??= '587';
		self::$values['DB_PORT'] ??= '3306';
	}

	private static function validateProduction(): void {
		if ((self::$values['APP_ENV'] ?? 'local') !== 'production') {
			return;
		}

		foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'MAIL_FROM_EMAIL', 'APP_URL'] as $key) {
			if (!isset(self::$values[$key]) || self::$values[$key] === '') {
				throw new RuntimeException("Missing required production config key: {$key}");
			}
		}
	}

	private static function ensureLoaded(): void {
		if (!self::$loaded) {
			self::load();
		}
	}
}
