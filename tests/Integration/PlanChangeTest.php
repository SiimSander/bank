<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class PlanChangeTest extends DatabaseTestCase {
	public function testIncomePercentChangeDoesNotMoveGoalIncomeFrom(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$types = getBankTypes($this->pdo, $accountId);
		$savingsType = null;

		foreach ($types as $type) {
			if ($type['slug'] === 'savings') {
				$savingsType = $type;
				break;
			}
		}

		self::assertNotNull($savingsType);

		$result = updateBankType($this->pdo, $accountId, (int) $savingsType['id'], [
			'income_percent' => 0.10,
		]);
		self::assertTrue($result['success'], $result['error'] ?? '');

		$updated = getBankTypeBySlug($this->pdo, $accountId, 'savings');
		self::assertNull($updated['goal_income_from']);
		self::assertSame(0.1, $updated['income_percent']);
	}

	public function testPercentChangeDoesNotRecalculatePastIncomeDays(): void {
		putenv('TEST_TODAY=2026-09-21');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 1000, '2026-09-01');
		$this->addEntry($accountId, 'income', 500, '2026-09-15');

		$investmentsType = getBankTypeBySlug($this->pdo, $accountId, 'investments');

		updateBankType($this->pdo, $accountId, (int) $investmentsType['id'], [
			'income_percent' => 0.20,
		]);

		$types = getBankTypes($this->pdo, $accountId);
		$targets = computeGoalTargets($this->pdo, $accountId, $types, '2026-09-01', '2026-09-30');

		self::assertSame(375.0, $targets['investments']);
	}

	public function testMidMonthPercentChangeAppliesFromChangeDateForward(): void {
		putenv('TEST_TODAY=2026-09-21');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 5387.99, '2026-09-10');
		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		$statsBefore = getMonthlyStatByType($this->pdo, $accountId, '2026-09-01');
		self::assertSame(1347.0, $statsBefore['investments']['goal']);

		$savingsType = getBankTypeBySlug($this->pdo, $accountId, 'savings');
		$investmentsType = getBankTypeBySlug($this->pdo, $accountId, 'investments');

		$savingsResult = updateBankType($this->pdo, $accountId, (int) $savingsType['id'], [
			'income_percent' => 0.10,
		]);
		self::assertTrue($savingsResult['success'], $savingsResult['error'] ?? '');

		$investmentsResult = updateBankType($this->pdo, $accountId, (int) $investmentsType['id'], [
			'income_percent' => 0.30,
		]);
		self::assertTrue($investmentsResult['success'], $investmentsResult['error'] ?? '');

		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		$statsAfter = getMonthlyStatByType($this->pdo, $accountId, '2026-09-01');
		self::assertSame(1347.0, $statsAfter['investments']['goal']);

		$this->addEntry($accountId, 'income', 1000, '2026-09-21');
		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		$statsWithNewIncome = getMonthlyStatByType($this->pdo, $accountId, '2026-09-01');
		self::assertSame(1647.0, $statsWithNewIncome['investments']['goal']);
	}
}
