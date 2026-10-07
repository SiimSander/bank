<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class CustomDateRangeQueriesTest extends DatabaseTestCase {
	public function testEntriesQueryIncludesBothBoundaryDaysOnly(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 10, '2026-07-31', note: 'July');
		$this->addEntry($accountId, 'income', 20, '2026-08-01', note: 'August first');
		$this->addEntry($accountId, 'income', 30, '2026-08-15', note: 'August mid');
		$this->addEntry($accountId, 'income', 40, '2026-08-31', note: 'August last');
		$this->addEntry($accountId, 'income', 50, '2026-09-01', note: 'September');

		$range = parseBankDateRange('custom', '2026-08-01', '2026-08-31');
		$entries = getBankEntriesByTypeBetween($this->pdo, $accountId, 'income', $range['start'], $range['end']);

		self::assertEqualsCanonicalizing(
			['August first', 'August mid', 'August last'],
			array_column($entries, 'note')
		);
	}

	public function testInvestmentBreakdownIsLimitedToCustomRange(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 100, '2026-07-20', note: 'Wise (£WISE)');
		$this->addEntry($accountId, 'investments', 60, '2026-08-05', note: 'Wise (£WISE)');
		$this->addEntry($accountId, 'investments', 40, '2026-08-20', note: 'Bitcoin (€BTC)');
		$this->addEntry($accountId, 'investments', 25, '2026-09-02', note: 'Bitcoin (€BTC)');

		$range = parseBankDateRange('custom', '2026-08-01', '2026-08-31');

		self::assertSame([
			['note' => 'Wise (£WISE)', 'amount' => 60.0],
			['note' => 'Bitcoin (€BTC)', 'amount' => 40.0],
		], getInvestmentBreakdownByNote($this->pdo, $accountId, $range['start'], $range['end']));
	}

	public function testHistoryQueryHonoursCustomRange(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 70, '2026-07-31');
		$this->addEntry($accountId, 'income', 80, '2026-08-10');

		$range = parseBankDateRange('custom', '2026-08-01', '2026-08-31');
		$history = getBankHistoryBetween($this->pdo, $accountId, $range['start'], $range['end']);
		$types = getActiveBankTypes($this->pdo, $accountId);

		self::assertSame(80.0, sumBankHistoryTotals($history, $types)['income']);
	}
}
