<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class InvestmentNotesTest extends DatabaseTestCase {
	public function testReturnsDistinctNotesOrderedByTotalWithTickerLabels(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 100, '2026-01-05', note: 'Wise (£WISE)');
		$this->addEntry($accountId, 'investments', 300, '2026-01-10', note: 'Vanguard S&P 500 (€VUAA)');
		$this->addEntry($accountId, 'investments', 50, '2026-02-10', note: 'Wise (£WISE)');
		$this->addEntry($accountId, 'investments', 20, '2026-02-15', note: 'Bitcoin');

		self::assertSame([
			['note' => 'Vanguard S&P 500 (€VUAA)', 'label' => '€VUAA'],
			['note' => 'Wise (£WISE)', 'label' => '£WISE'],
			['note' => 'Bitcoin', 'label' => 'Bitcoin'],
		], getInvestmentNotes($this->pdo, $accountId));
	}

	public function testIgnoresEmptyNotesWithdrawalsAndOtherTypes(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 10, '2026-01-01', note: '');
		createBankEntry($this->pdo, $accountId, 'investments', 'card', -50, 'Withdrawn only', '2026-01-02');
		$this->addEntry($accountId, 'savings', 500, '2026-01-03', note: 'Savings note');

		self::assertSame([], getInvestmentNotes($this->pdo, $accountId));
	}

	public function testPlannedStockWithoutEntriesIsListedAfterInvestedStocks(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 100, '2026-01-05', note: 'Wise (£WISE)');
		self::assertTrue(setStockGoal($this->pdo, $accountId, 'Bitcoin (₿BTC)', 50, true));

		self::assertSame([
			['note' => 'Wise (£WISE)', 'label' => '£WISE'],
			['note' => 'Bitcoin (₿BTC)', 'label' => '₿BTC'],
		], getInvestmentNotes($this->pdo, $accountId));
	}

	public function testPlannedStockAlreadyInvestedInIsNotListedTwice(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 100, '2026-01-05', note: 'Wise (£WISE)');
		self::assertTrue(setStockGoal($this->pdo, $accountId, 'wise (£wise)', 50, true));

		self::assertSame([['note' => 'Wise (£WISE)', 'label' => '£WISE']], getInvestmentNotes($this->pdo, $accountId));
	}

	public function testStockWhoseGoalWasRemovedIsNoLongerListed(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		setStockGoal($this->pdo, $accountId, 'Bitcoin (₿BTC)', 50, true);
		setStockGoal($this->pdo, $accountId, 'Bitcoin (₿BTC)', 0);

		self::assertSame([], getInvestmentNotes($this->pdo, $accountId));
	}

	public function testBreakdownIgnoresPlannedStockWithoutEntries(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 100, '2026-09-05', note: 'Wise (£WISE)');
		setStockGoal($this->pdo, $accountId, 'Bitcoin (₿BTC)', 50, true);

		self::assertSame(
			[['note' => 'Wise (£WISE)', 'amount' => 100.0]],
			getInvestmentBreakdownByNote($this->pdo, $accountId, '2026-09-01', '2026-09-30')
		);
	}

	public function testAddingAStockThatAlreadyHasAGoalIsRejected(): void {
		$accountId = $this->createTestAccount();

		setStockGoal($this->pdo, $accountId, 'Bitcoin (₿BTC)', 50, true);

		self::assertIsString(setStockGoal($this->pdo, $accountId, 'bitcoin (₿btc)', 80, true));
		self::assertIsString(setStockGoal($this->pdo, $accountId, 'Wise (£WISE)', 0, true));
	}

	public function testOnlyReturnsNotesOfRequestedAccount(): void {
		$accountId = $this->createTestAccount();
		$otherAccountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->seedTypes($otherAccountId);

		$this->addEntry($otherAccountId, 'investments', 100, '2026-01-05', note: 'Wise (£WISE)');

		self::assertSame([], getInvestmentNotes($this->pdo, $accountId));
	}
}
