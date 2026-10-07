<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class UpdateBankEntryTest extends DatabaseTestCase {
	public function testChangingMethodMovesAmountBetweenCardAndCashBalances(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'income', 40, '2026-09-10', note: 'Poker');
		$entryId = $this->lastEntryId($accountId);

		self::assertSame(40.0, getBankBalance($this->pdo, $accountId));
		self::assertSame(0.0, getCashBalance($this->pdo, $accountId));

		self::assertTrue(updateBankEntry($this->pdo, $entryId, $accountId, 'income', 'cash', 40.0, 'Poker'));

		self::assertSame(0.0, getBankBalance($this->pdo, $accountId));
		self::assertSame(40.0, getCashBalance($this->pdo, $accountId));
	}

	public function testChangingNoteRegroupsInvestmentBreakdown(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-10', note: 'Wise');
		$this->addEntry($accountId, 'investments', 50, '2026-09-11', note: 'Wise (£WISE)');
		$entryId = $this->lastEntryId($accountId);

		self::assertTrue(updateBankEntry($this->pdo, $entryId, $accountId, 'investments', 'card', 50.0, 'Wise'));

		self::assertSame(
			[['note' => 'Wise', 'amount' => 150.0]],
			getInvestmentBreakdownAllTime($this->pdo, $accountId)
		);
	}

	public function testChangingAmountRefreshesMonthlyIncome(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'income', 100, '2026-09-10');
		$entryId = $this->lastEntryId($accountId);

		self::assertTrue(updateBankEntry($this->pdo, $entryId, $accountId, 'income', 'card', 65.0, null));

		$stats = getMonthlyStats($this->pdo, $accountId, '2026-09-01');
		self::assertNotNull($stats);
		self::assertSame(65.0, $stats['income']);
	}

	public function testRejectsUpdatingAnotherAccountsEntry(): void {
		$accountId = $this->createTestAccount();
		$otherAccountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->seedTypes($otherAccountId);
		$this->addEntry($accountId, 'income', 40, '2026-09-10', note: 'Poker');
		$entryId = $this->lastEntryId($accountId);

		self::assertFalse(updateBankEntry($this->pdo, $entryId, $otherAccountId, 'income', 'cash', 99.0, 'Hacked'));

		self::assertSame(40.0, getBankBalance($this->pdo, $accountId));
	}

	private function lastEntryId(int $accountId): int {
		$statement = $this->pdo->prepare('SELECT MAX(id) FROM bank_entries WHERE account_id = ?');
		$statement->execute([$accountId]);

		return (int) $statement->fetchColumn();
	}
}
