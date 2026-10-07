<?php

/**
 * Per-request memoization for read-only pages (/bank, /bank/history).
 *
 * Disabled by default: a long-lived process (tests, CLI scripts) that writes between reads must never see stale
 * data. A page handler enables it after all of its writes are done and never has to disable it, because PHP drops
 * the static state at the end of the request.
 */
final class BankRequestCache {
	private static bool $enabled = false;

	/** @var array<string, mixed> */
	private static array $store = [];

	public static function enable(): void {
		self::$enabled = true;
	}

	public static function disable(): void {
		self::$enabled = false;
		self::$store = [];
	}

	public static function isEnabled(): bool {
		return self::$enabled;
	}

	/**
	 * @template T
	 * @param callable(): T $loader
	 * @return T
	 */
	public static function remember(string $key, callable $loader): mixed {
		if (!self::$enabled) {
			return $loader();
		}

		if (!array_key_exists($key, self::$store)) {
			self::$store[$key] = $loader();
		}

		return self::$store[$key];
	}
}
