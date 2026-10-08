<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class IncomePercentInputTest extends DatabaseTestCase {
	public function testCreateRejectsExponentNotation(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$result = createBankType($this->pdo, $accountId, [
			'label' => 'Holiday',
			'slug' => 'holiday',
			'color_hex' => '#123456',
			'balance_mode' => 'pot',
			'income_percent' => '1e-1',
		]);

		self::assertFalse($result['success']);
		self::assertSame('Income percent must be a number.', $result['error']);
	}

	public function testUpdateRejectsExponentNotation(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$expenses = getBankTypeBySlug($this->pdo, $accountId, 'expenses');

		$result = updateBankType($this->pdo, $accountId, $expenses['id'], ['income_percent' => '1e-1']);

		self::assertFalse($result['success']);
		self::assertSame('Income percent must be a number.', $result['error']);
	}

	public function testUpdateStillAcceptsPlainDecimalString(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$expenses = getBankTypeBySlug($this->pdo, $accountId, 'expenses');

		$result = updateBankType($this->pdo, $accountId, $expenses['id'], ['income_percent' => '0.6000']);

		self::assertTrue($result['success'] ?? false, json_encode($result));
		self::assertSame(0.6, getBankTypeBySlug($this->pdo, $accountId, 'expenses')['income_percent']);
	}
}
