<?php

declare(strict_types=1);

namespace Tests\Integration;

use BankRequestCache;
use Tests\Support\DatabaseTestCase;

final class BankRequestCacheTest extends DatabaseTestCase {
	private const MAX_QUERIES_WITH_CACHE = 80;

	protected function tearDown(): void {
		BankRequestCache::disable();
		parent::tearDown();
	}

	public function testCachedPageCalculationsMatchUncachedOnes(): void {
		$accountId = $this->createManyMonthAccount();

		$uncached = $this->collectPageCalculations($accountId);

		BankRequestCache::enable();
		$cached = $this->collectPageCalculations($accountId);

		self::assertEquals($uncached, $cached);
	}

	public function testCachedPageUsesFewQueriesWhenHistoryGrows(): void {
		$accountId = $this->createManyMonthAccount();

		BankRequestCache::enable();
		$before = $this->questionCount();
		$this->collectPageCalculations($accountId);
		$queries = $this->questionCount() - $before - 1;

		self::assertLessThan(self::MAX_QUERIES_WITH_CACHE, $queries);
	}

	public function testCacheIsOffByDefaultSoWritesAreVisibleImmediately(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		self::assertFalse(BankRequestCache::isEnabled());

		$balanceBefore = getBankBalance($this->pdo, $accountId);
		$this->addEntry($accountId, 'income', 100.0, '2026-09-10');

		self::assertSame($balanceBefore + 100.0, getBankBalance($this->pdo, $accountId));
	}

	public function testDisableClearsStoredValues(): void {
		$accountId = $this->createAccountWithGoalTypes();

		BankRequestCache::enable();
		self::assertContains('savings', array_column(getBankTypes($this->pdo, $accountId, true), 'slug'));

		$this->pdo->prepare(
			'UPDATE bank_entry_types SET is_active = 0 WHERE account_id = ? AND slug = ?'
		)->execute([$accountId, 'savings']);

		self::assertContains('savings', array_column(getBankTypes($this->pdo, $accountId, true), 'slug'));

		BankRequestCache::disable();
		BankRequestCache::enable();

		self::assertNotContains('savings', array_column(getBankTypes($this->pdo, $accountId, true), 'slug'));
	}

	private function createAccountWithGoalTypes(): int {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$expenses = getBankTypeBySlug($this->pdo, $accountId, 'expenses');
		updateBankType($this->pdo, $accountId, $expenses['id'], ['income_percent' => 0.6]);

		foreach (['savings' => 'Savings', 'investments' => 'Investments'] as $slug => $label) {
			if (getBankTypeBySlug($this->pdo, $accountId, $slug) === null) {
				$result = createBankType($this->pdo, $accountId, [
					'label' => $label,
					'slug' => $slug,
					'color_hex' => '#60a5fa',
					'balance_mode' => 'pot',
					'income_percent' => 0.2,
				]);
				self::assertTrue($result['success'], json_encode($result));
			}
		}

		return $accountId;
	}

	private function createManyMonthAccount(): int {
		$accountId = $this->createAccountWithGoalTypes();

		foreach (['2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09'] as $month) {
			$this->addEntry($accountId, 'income', 1000.0, $month . '-03');
			$this->addEntry($accountId, 'income', 500.0, $month . '-11');
			$this->addEntry($accountId, 'savings', 120.0, $month . '-04');
			$this->addEntry($accountId, 'investments', 80.0, $month . '-12');
			$this->addEntry($accountId, 'expenses', -200.0, $month . '-13');
		}

		return $accountId;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function collectPageCalculations(int $accountId): array {
		$types = getActiveBankTypes($this->pdo, $accountId);
		$week = parseBankDateRange('week');
		$today = date('Y-m-d');

		return [
			'balance' => getBankBalance($this->pdo, $accountId),
			'cash' => getCashBalance($this->pdo, $accountId),
			'pots' => getPotBalances($this->pdo, $accountId),
			'week_targets' => computeGoalTargets($this->pdo, $accountId, $types, $week['start'], $week['end']),
			'today_targets' => computeGoalTargets($this->pdo, $accountId, $types, $today, $today),
			'monthly_current' => getMonthlyStats($this->pdo, $accountId, date('Y-m-01')),
			'missing' => computeOverallMissingGoals($this->pdo, $accountId),
			'surplus' => computeOverallSurplusGoals($this->pdo, $accountId),
			'monthly_history' => getMonthlyStatsHistory($this->pdo, $accountId),
		];
	}

	private function questionCount(): int {
		return (int) $this->pdo->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(\PDO::FETCH_NUM)[1];
	}
}
