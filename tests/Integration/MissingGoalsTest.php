<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class MissingGoalsTest extends DatabaseTestCase {
	protected function tearDown(): void {
		putenv('TEST_TODAY=2026-09-15');
		parent::tearDown();
	}

	public function testSurplusInLaterMonthOffsetsPriorMonthDeficit(): void {
		putenv('TEST_TODAY=2026-09-15');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 10000, '2026-08-01');
		$this->addEntry($accountId, 'savings', 1000, '2026-08-10');
		refreshMonthlyStats($this->pdo, $accountId, '2026-08-01');

		$this->addEntry($accountId, 'income', 10000, '2026-09-01');
		$this->addEntry($accountId, 'savings', 1900, '2026-09-05');
		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		$remaining = computeOverallMissingGoals($this->pdo, $accountId);

		self::assertSame(100.0, $remaining['savings'] ?? 0.0);
	}

	public function testThreeMonthChainWithMidSurplus(): void {
		putenv('TEST_TODAY=2026-09-15');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 10000, '2026-07-01');
		$this->addEntry($accountId, 'savings', 1600, '2026-07-10');
		refreshMonthlyStats($this->pdo, $accountId, '2026-07-01');

		$this->addEntry($accountId, 'income', 10000, '2026-08-01');
		$this->addEntry($accountId, 'savings', 1300, '2026-08-10');
		refreshMonthlyStats($this->pdo, $accountId, '2026-08-01');

		$this->addEntry($accountId, 'income', 10000, '2026-09-01');
		$this->addEntry($accountId, 'savings', 1200, '2026-09-10');
		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		$remaining = computeOverallMissingGoals($this->pdo, $accountId);

		self::assertSame(400.0, $remaining['savings'] ?? 0.0);
	}

	public function testLaterMonthSurplusOffsetsPriorDeficitForOverallSurplus(): void {
		putenv('TEST_TODAY=2026-09-15');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 10000, '2026-08-01');
		$this->addEntry($accountId, 'savings', 1100, '2026-08-10');
		refreshMonthlyStats($this->pdo, $accountId, '2026-08-01');

		$this->addEntry($accountId, 'income', 10000, '2026-09-01');
		$this->addEntry($accountId, 'savings', 2000, '2026-09-05');
		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		$missing = computeOverallMissingGoals($this->pdo, $accountId);
		$surplus = computeOverallSurplusGoals($this->pdo, $accountId);

		self::assertArrayNotHasKey('savings', $missing);
		self::assertSame(100.0, $surplus['savings'] ?? 0.0);
	}

	public function testSurplusInOneTypeDoesNotReduceAnother(): void {
		putenv('TEST_TODAY=2026-09-15');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 1000, '2026-08-01');
		$this->addEntry($accountId, 'savings', 200, '2026-08-05');
		$this->addEntry($accountId, 'investments', 50, '2026-08-05');
		refreshMonthlyStats($this->pdo, $accountId, '2026-08-01');

		$this->addEntry($accountId, 'income', 1000, '2026-09-01');
		$this->addEntry($accountId, 'savings', 300, '2026-09-05');
		$this->addEntry($accountId, 'investments', 150, '2026-09-05');
		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		$remaining = computeOverallMissingGoals($this->pdo, $accountId);

		self::assertSame(300.0, $remaining['investments'] ?? 0.0);
		self::assertArrayNotHasKey('savings', $remaining);

		$surplus = computeOverallSurplusGoals($this->pdo, $accountId);
		self::assertSame(200.0, $surplus['savings'] ?? 0.0);
		self::assertArrayNotHasKey('investments', $surplus);
	}

	public function testExpensesAreExcluded(): void {
		putenv('TEST_TODAY=2026-09-15');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 1000, '2026-09-01');
		$this->addEntry($accountId, 'expenses', 800, '2026-09-02');
		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		$remaining = computeOverallMissingGoals($this->pdo, $accountId);

		self::assertArrayNotHasKey('expenses', $remaining);
	}

	public function testMonthsBeforeTypeActivationAreIgnored(): void {
		putenv('TEST_TODAY=2026-09-15');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		updateBankType($this->pdo, $accountId, (int) getBankTypeBySlug($this->pdo, $accountId, 'savings')['id'], [
			'income_percent' => 0.05,
		]);

		$result = createBankType($this->pdo, $accountId, [
			'label' => 'Dept',
			'slug' => 'dept',
			'color_hex' => '#e9fa00',
			'income_percent' => 0.10,
			'balance_mode' => 'wallet_out',
		]);
		self::assertTrue($result['success'], $result['error'] ?? '');

		$this->pdo->prepare(
			'UPDATE bank_entry_types SET created_at = ?, goal_income_from = ? WHERE account_id = ? AND slug = ?'
		)->execute(['2026-09-01 08:00:00', '2026-09-01', $accountId, 'dept']);

		$deptType = getBankTypeBySlug($this->pdo, $accountId, 'dept');
		recordIncomePercentSegment($this->pdo, (int) $deptType['id'], '2026-09-01', 0.10);

		$this->addEntry($accountId, 'income', 1000, '2026-08-01');
		refreshMonthlyStats($this->pdo, $accountId, '2026-08-01');

		$this->addEntry($accountId, 'income', 1000, '2026-09-10');
		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		$remaining = computeOverallMissingGoals($this->pdo, $accountId);

		self::assertSame(100.0, $remaining['dept'] ?? 0.0);
	}
}
