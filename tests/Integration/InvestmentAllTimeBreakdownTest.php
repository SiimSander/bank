<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class InvestmentAllTimeBreakdownTest extends DatabaseTestCase {
	public function testAggregatesInvestmentsByNoteAcrossMonthsOrderedByAmount(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 100, '2026-01-05', note: 'S&P 500');
		$this->addEntry($accountId, 'investments', 50, '2026-01-10', note: 'Gold');
		$this->addEntry($accountId, 'investments', 75, '2026-02-10', note: 'S&P 500');
		$this->addEntry($accountId, 'investments', 25, '2026-02-15', note: 'Gold');
		$this->addEntry($accountId, 'investments', 10, '2026-02-20', note: '');

		$breakdown = getInvestmentBreakdownAllTime($this->pdo, $accountId);

		self::assertSame([
			['note' => 'S&P 500', 'amount' => 175.0],
			['note' => 'Gold', 'amount' => 75.0],
			['note' => 'Other', 'amount' => 10.0],
		], $breakdown);
	}

	public function testLimitsBreakdownToSelectedDateRange(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 100, '2026-01-05', note: 'S&P 500');
		$this->addEntry($accountId, 'investments', 50, '2026-02-10', note: 'Gold');

		$breakdown = getInvestmentBreakdownByNote($this->pdo, $accountId, '2026-02-01', '2026-02-28');

		self::assertSame([
			['note' => 'Gold', 'amount' => 50.0],
		], $breakdown);
	}

	public function testIgnoresWithdrawalsAndOtherTypes(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 200, '2026-01-01', note: 'S&P 500');
		createBankEntry($this->pdo, $accountId, 'investments', 'card', -50, 'S&P 500', '2026-01-02');
		$this->addEntry($accountId, 'savings', 500, '2026-01-03', note: 'S&P 500');

		$breakdown = getInvestmentBreakdownAllTime($this->pdo, $accountId);

		self::assertSame([
			['note' => 'S&P 500', 'amount' => 200.0],
		], $breakdown);
	}
}
