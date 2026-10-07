<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class GoalTargetsTest extends DatabaseTestCase {
	public function testBasicIncomeAllocation(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$types = getBankTypes($this->pdo, $accountId);

		$this->addEntry($accountId, 'income', 1000, '2026-09-01');

		$targets = computeGoalTargets($this->pdo, $accountId, $types, '2026-09-01', '2026-09-01');

		self::assertSame(600.0, $targets['expenses']);
		self::assertSame(150.0, $targets['savings']);
		self::assertSame(250.0, $targets['investments']);
	}

	public function testDailyTargetUsesOnlyThatDaysIncome(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$types = getBankTypes($this->pdo, $accountId);

		$this->addEntry($accountId, 'income', 1000, '2026-09-10');

		$targets = computeGoalTargets($this->pdo, $accountId, $types, '2026-09-10', '2026-09-10');

		self::assertSame(150.0, $targets['savings']);
	}

	public function testGoalIncomeFromIgnoresEarlierDailyIncome(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->pdo->prepare(
			'UPDATE bank_entry_types SET goal_income_from = ? WHERE account_id = ? AND slug = ?'
		)->execute(['2026-09-10', $accountId, 'savings']);

		$types = getBankTypes($this->pdo, $accountId);
		$this->addEntry($accountId, 'income', 1000, '2026-09-01');
		$this->addEntry($accountId, 'income', 500, '2026-09-15');

		$targets = computeGoalTargets($this->pdo, $accountId, $types, '2026-09-01', '2026-09-15');
		self::assertSame(75.0, $targets['savings']);

		$dailyBefore = computeGoalTargets($this->pdo, $accountId, $types, '2026-09-01', '2026-09-01');
		$dailyAfter = computeGoalTargets($this->pdo, $accountId, $types, '2026-09-15', '2026-09-15');

		self::assertSame(0.0, $dailyBefore['savings']);
		self::assertSame(75.0, $dailyAfter['savings']);
	}

	public function testWithdrawalsNetIntoDailyActuals(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$goalTypes = $this->goalTypes($accountId);

		$this->addEntry($accountId, 'income', 1000, '2026-09-10');
		$this->addEntry($accountId, 'savings', 120, '2026-09-10');
		$this->addEntry($accountId, 'savings', -30, '2026-09-10');

		$actuals = getDayGoalActuals($this->pdo, $accountId, '2026-09-10', $goalTypes);

		self::assertSame(90.0, $actuals['savings']);
	}

	public function testNoIncomeDayProducesNoDailyShortfall(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$goalTypes = $this->goalTypes($accountId);

		$this->addEntry($accountId, 'savings', 100, '2026-09-08');

		self::assertSame([], computeDailyGoalShortfalls($this->pdo, $accountId, '2026-09-08', $goalTypes));
	}
}
