<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class IncomePercentHistoryTest extends DatabaseTestCase {
	protected function tearDown(): void {
		putenv('TEST_TODAY=2026-09-15');
		parent::tearDown();
	}

	public function testDailyGoalUsesPercentEffectiveOnIncomeDate(): void {
		putenv('TEST_TODAY=2026-09-22');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 100, '2026-09-19');
		$this->addEntry($accountId, 'income', 200, '2026-09-20');

		$investmentsType = getBankTypeBySlug($this->pdo, $accountId, 'investments');

		putenv('TEST_TODAY=2026-09-21');
		updateBankType($this->pdo, $accountId, (int) getBankTypeBySlug($this->pdo, $accountId, 'savings')['id'], [
			'income_percent' => 0.10,
		]);
		updateBankType($this->pdo, $accountId, (int) $investmentsType['id'], [
			'income_percent' => 0.30,
		]);

		$this->addEntry($accountId, 'income', 100, '2026-09-21');

		$investmentsType = getBankTypeBySlug($this->pdo, $accountId, 'investments');

		self::assertSame(25.0, computeTypeDailyGoalAmount($this->pdo, $accountId, $investmentsType, '2026-09-19'));
		self::assertSame(50.0, computeTypeDailyGoalAmount($this->pdo, $accountId, $investmentsType, '2026-09-20'));
		self::assertSame(30.0, computeTypeDailyGoalAmount($this->pdo, $accountId, $investmentsType, '2026-09-21'));

		putenv('TEST_TODAY=2026-09-22');
		updateBankType($this->pdo, $accountId, (int) getBankTypeBySlug($this->pdo, $accountId, 'savings')['id'], [
			'income_percent' => 0.15,
		]);
		updateBankType($this->pdo, $accountId, (int) $investmentsType['id'], [
			'income_percent' => 0.25,
		]);

		$investmentsType = getBankTypeBySlug($this->pdo, $accountId, 'investments');
		self::assertSame(30.0, computeTypeDailyGoalAmount($this->pdo, $accountId, $investmentsType, '2026-09-21'));
	}

	public function testPercentChangeCreatesHistorySegment(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$investmentsType = getBankTypeBySlug($this->pdo, $accountId, 'investments');

		updateBankType($this->pdo, $accountId, (int) getBankTypeBySlug($this->pdo, $accountId, 'savings')['id'], [
			'income_percent' => 0.10,
		]);
		$result = updateBankType($this->pdo, $accountId, (int) $investmentsType['id'], [
			'income_percent' => 0.30,
		]);
		self::assertTrue($result['success'], $result['error'] ?? '');

		$statement = $this->pdo->prepare(
			'SELECT COUNT(*) FROM bank_entry_type_income_percent_history WHERE bank_entry_type_id = ?'
		);
		$statement->execute([(int) $investmentsType['id']]);

		self::assertSame(2, (int) $statement->fetchColumn());
	}
}
