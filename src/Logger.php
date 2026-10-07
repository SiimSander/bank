<?php

final class Logger {
	private const LEVELS = [
		'debug' => 0,
		'info' => 1,
		'warning' => 2,
		'error' => 3,
	];

	private string $logPath;
	private int $minLevel;

	private bool $toStderr;

	public function __construct(string $logPath, string $minLevel = 'warning', bool $toStderr = false) {
		$this->logPath = $logPath;
		$this->toStderr = $toStderr;
		$this->minLevel = self::LEVELS[$minLevel] ?? self::LEVELS['warning'];
	}

	public function debug(string $channel, string $message, array $context = []): void {
		$this->log('debug', $channel, $message, $context);
	}

	public function info(string $channel, string $message, array $context = []): void {
		$this->log('info', $channel, $message, $context);
	}

	public function warning(string $channel, string $message, array $context = []): void {
		$this->log('warning', $channel, $message, $context);
	}

	public function error(string $channel, string $message, array $context = []): void {
		$this->log('error', $channel, $message, $context);
	}

	public function log(string $level, string $channel, string $message, array $context = []): void {
		$levelValue = self::LEVELS[$level] ?? self::LEVELS['warning'];

		if ($levelValue < $this->minLevel) {
			return;
		}

		$entry = json_encode([
			'time' => gmdate('c'),
			'level' => $level,
			'channel' => $channel,
			'message' => $message,
			'context' => self::redactContext($context),
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if ($entry === false) {
			error_log("Logger: failed to encode log entry for channel {$channel}");

			return;
		}

		if ($this->toStderr) {
			error_log($entry);

			return;
		}

		$dir = dirname($this->logPath);
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			error_log("Logger: could not create log directory {$dir}");

			return;
		}

		file_put_contents($this->logPath, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
	}

	/** @param array<string, mixed> $context */
	private static function redactContext(array $context): array {
		$redacted = [];

		foreach ($context as $key => $value) {
			$keyLower = strtolower((string) $key);

			if (
				str_contains($keyLower, 'password')
				|| str_contains($keyLower, 'token')
				|| str_contains($keyLower, 'secret')
				|| str_contains($keyLower, 'authorization')
			) {
				$redacted[$key] = '[redacted]';
				continue;
			}

			if (is_array($value)) {
				$redacted[$key] = self::redactContext($value);
				continue;
			}

			$redacted[$key] = $value;
		}

		return $redacted;
	}
}

function logger(): ?Logger {
	static $instance = null;

	if ($instance !== null) {
		return $instance;
	}

	if (!class_exists(AppConfig::class, false)) {
		return null;
	}

	try {
		AppConfig::load();
		$logPath = AppConfig::resolvePath(AppConfig::get('LOG_PATH', 'storage/logs/app.log') ?? 'storage/logs/app.log');
		$logLevel = AppConfig::get('LOG_LEVEL', 'warning') ?? 'warning';
		$toStderr = filter_var(AppConfig::get('LOG_TO_STDERR', 'false'), FILTER_VALIDATE_BOOLEAN);
		$instance = new Logger($logPath, $logLevel, $toStderr);
	} catch (Throwable $e) {
		error_log('Logger bootstrap failed: ' . $e->getMessage());

		return null;
	}

	return $instance;
}

function registerProductionErrorHandlers(): void {
	if (AppConfig::isDebug()) {
		return;
	}

	set_exception_handler(static function (Throwable $e): void {
		logger()?->error('app', 'Uncaught exception', [
			'exception' => $e::class,
			'message' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
		]);

		if (!headers_sent()) {
			http_response_code(500);
			header('Content-Type: text/plain; charset=UTF-8');
		}

		echo 'Something went wrong. Please try again later.';
	});

	set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
		if (!(error_reporting() & $severity)) {
			return false;
		}

		logger()?->error('app', 'PHP error', [
			'severity' => $severity,
			'message' => $message,
			'file' => $file,
			'line' => $line,
		]);

		return true;
	});
}
