<?php

final class Clock {
	public static function today(): string {
		$testToday = getenv('TEST_TODAY');

		if ($testToday !== false && $testToday !== '') {
			return $testToday;
		}

		return date('Y-m-d');
	}

	public static function monthStart(?string $date = null): string {
		return date('Y-m-01', strtotime($date ?? self::today()));
	}
}
