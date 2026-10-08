<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountInputValidationTest extends TestCase {
	#[DataProvider('validNames')]
	public function testAcceptsValidNames(string $name): void {
		self::assertNull(validateAccountName($name));
	}

	public static function validNames(): array {
		return [['Siim'], ['Siim Sander'], ['Mari-Liis Tamm'], ["O'Connor"], ['Jüri Õun']];
	}

	#[DataProvider('invalidNames')]
	public function testRejectsInvalidNames(string $name): void {
		self::assertNotNull(validateAccountName($name));
	}

	public static function invalidNames(): array {
		return [[''], ['Siim1'], ['/!"#,'], ['Siim&&'], ['¤Siim'], ['Siim  Sander'], ['-Siim'], [str_repeat('a', 101)]];
	}

	#[DataProvider('validUsernames')]
	public function testAcceptsValidUsernames(string $username): void {
		self::assertNull(validateAccountUsername($username));
	}

	public static function validUsernames(): array {
		return [['siim'], ['Siim123'], ['123']];
	}

	#[DataProvider('invalidUsernames')]
	public function testRejectsInvalidUsernames(string $username): void {
		self::assertNotNull(validateAccountUsername($username));
	}

	public static function invalidUsernames(): array {
		return [[''], ['siim sander'], ['siim!'], ['/siim'], ['si,im'], ['siim¤'], ['jüri'], [str_repeat('a', 51)]];
	}
}
