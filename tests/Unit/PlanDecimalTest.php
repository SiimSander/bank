<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlainDecimalTest extends TestCase {
	/**
	 * @return array<string, array{mixed}>
	 */
	public static function validValues(): array {
		return [
			'integer string' => ['12'],
			'decimal string' => ['12.50'],
			'zero' => ['0'],
			'leading zero decimal' => ['0.05'],
			'int' => [7],
			'float' => [0.25],
		];
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function invalidValues(): array {
		return [
			'exponent' => ['12e12'],
			'uppercase exponent' => ['1E5'],
			'negative exponent' => ['1e-3'],
			'plus sign' => ['+5'],
			'minus sign' => ['-5'],
			'hex' => ['0x1A'],
			'empty' => [''],
			'letters' => ['abc'],
			'comma decimal' => ['12,5'],
			'trailing dot' => ['12.'],
			'leading dot' => ['.5'],
			'whitespace inside' => ['1 2'],
			'null' => [null],
			'array' => [[1]],
			'infinite float' => [INF],
			'not a number float' => [NAN],
		];
	}

	#[DataProvider('validValues')]
	public function testAcceptsPlainDecimals(mixed $value): void {
		self::assertTrue(isPlainDecimal($value));
	}

	#[DataProvider('invalidValues')]
	public function testRejectsEverythingElse(mixed $value): void {
		self::assertFalse(isPlainDecimal($value));
		self::assertNull(parsePlainDecimal($value));
	}

	public function testParseTrimsSurroundingWhitespace(): void {
		self::assertSame(12.5, parsePlainDecimal(' 12.5 '));
	}

	public function testParseReturnsFloat(): void {
		self::assertSame(12.0, parsePlainDecimal('12'));
	}

	public function testMoneyAmountAcceptsTheMaximum(): void {
		self::assertSame(1000000000.0, parseMoneyAmount('1000000000'));
		self::assertSame(0.0, parseMoneyAmount('0'));
	}

	public function testMoneyAmountRejectsValuesAboveTheMaximum(): void {
		self::assertNull(parseMoneyAmount('1000000000.01'));
		self::assertNull(parseMoneyAmount('120000000000000000000000000000'));
	}

	public function testMoneyAmountRejectsNonPlainDecimals(): void {
		self::assertNull(parseMoneyAmount('12e12'));
		self::assertNull(parseMoneyAmount(''));
		self::assertNull(parseMoneyAmount(null));
	}
}
