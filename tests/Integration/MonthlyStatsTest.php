<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class MonthlyStatsTest extends DatabaseTestCase {
	public function testMonthlyActualIncludesWithdrawals(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 1000, '2026-09-01');
		$this->addEntry($accountId, 'savings', 200, '2026-09-02');
		$this->addEntry($accountId, 'savings', -50, '2026-09-03');

		$stats = getMonthlyStatByType($this->pdo, $accountId, '2026-09-01');

		self::assertSame(150.0, $stats['savings']['actual']);
	}

	public function testMonthlyGoalMatchesIncomePercent(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 1000, '2026-09-01');
		$this->addEntry($accountId, 'income', 500, '2026-09-10');
		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		$stats = getMonthlyStatByType($this->pdo, $accountId, '2026-09-01');
		$types = getBankTypes($this->pdo, $accountId);
		$targets = computeGoalTargets($this->pdo, $accountId, $types, '2026-09-01', '2026-09-30');

		self::assertSame($targets['savings'], $stats['savings']['goal']);
		self::assertSame($targets['investments'], $stats['investments']['goal']);
	}
}
