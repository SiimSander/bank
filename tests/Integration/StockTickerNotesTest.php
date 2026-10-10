<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class StockTickerNotesTest extends DatabaseTestCase {
	public function testInvestmentEntryTypedAsNameGetsTheTicker(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: 'vanguard ftse all-world');

		self::assertSame(['vanguard ftse all-world (€VWCE)'], $this->entryNotes($accountId));
	}

	public function testTickerTheUserTypedIsKept(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: 'Vanguard S&P 500 (£VUAA)');

		self::assertSame(['Vanguard S&P 500 (£VUAA)'], $this->entryNotes($accountId));
	}

	public function testUnknownNamesAndOtherTypesAreLeftAlone(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: 'My private fund');
		$this->addEntry($accountId, 'savings', 100, '2026-09-04', note: 'Bitcoin');

		self::assertSame(['My private fund', 'Bitcoin'], $this->entryNotes($accountId));
	}

	public function testStockTheUserAlreadyHasKeepsItsStoredNote(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: 'Wise (£WISE)');

		$this->addEntry($accountId, 'investments', 50, '2026-09-04', note: ' wise ');

		self::assertSame(['Wise (£WISE)', 'Wise (£WISE)'], $this->entryNotes($accountId));
	}

	public function testEditingAnEntryToAKnownNameAddsTheTicker(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: 'Something');
		$entryId = (int) $this->pdo->query('SELECT id FROM bank_entries')->fetchColumn();

		self::assertTrue(updateBankEntry($this->pdo, $entryId, $accountId, 'investments', 'card', 100, 'Tesla'));

		self::assertSame(['Tesla (€TSLA)'], $this->entryNotes($accountId));
	}

	public function testNewStockGoalGetsTheTickerLogoColourAndMatchesLaterEntries(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		self::assertTrue(setStockGoal($this->pdo, $accountId, 'Tesla', 100, true));
		$this->addEntry($accountId, 'investments', 40, '2026-09-03', note: 'tesla');

		$rows = getStockGoalRows($this->pdo, $accountId, '2026-09-01');

		self::assertCount(1, $rows);
		self::assertSame('Tesla (€TSLA)', $rows[0]['note']);
		self::assertSame('(€TSLA)', $rows[0]['ticker']);
		self::assertSame('tsla', $rows[0]['logo_slug']);
		self::assertSame('#e30526', $rows[0]['color']);
		self::assertSame(40.0, $rows[0]['invested']);
	}

	public function testAliasedNameGetsTheSharedLogoAndColour(): void {
		$accountId = $this->createTestAccount();

		self::assertTrue(setStockGoal($this->pdo, $accountId, 'Vanguard FTSE All-World', 100, true));

		$row = getStockGoalRows($this->pdo, $accountId, '2026-09-01')[0];

		self::assertSame('Vanguard FTSE All-World (€VWCE)', $row['note']);
		self::assertSame('vuaa', $row['logo_slug']);
		self::assertSame('#9a0718', $row['color']);
	}

	public function testBackfillRewritesBareNamesAndMovesTheStockData(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertBareNoteEntry($accountId, 'Bitcoin', 80);
		$this->pdo->prepare('INSERT INTO stock_goals (account_id, stock_note, monthly_amount, effective_from) VALUES (?, ?, ?, ?)')
			->execute([$accountId, 'Bitcoin', 300, '2026-08-01']);
		setStockColor($this->pdo, $accountId, 'Bitcoin', '#123456');

		$dryRun = backfillStockTickers($this->pdo, true);

		self::assertSame(['account ' . $accountId . ': "Bitcoin" -> "Bitcoin (€BTC)"'], $dryRun);
		self::assertSame(['Bitcoin'], $this->entryNotes($accountId));

		backfillStockTickers($this->pdo);

		self::assertSame(['Bitcoin (€BTC)'], $this->entryNotes($accountId));
		self::assertSame(['bitcoin (€btc)'], array_keys(getStockGoalHistory($this->pdo, $accountId)));
		self::assertSame(['bitcoin (€btc)' => '#123456'], getStoredStockColors($this->pdo, $accountId));
		self::assertSame([], backfillStockTickers($this->pdo, true));
	}

	public function testBackfillMergesABareNameIntoTheStockThatAlreadyHasATicker(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: 'Wise (£WISE)');
		$this->insertBareNoteEntry($accountId, 'wise', 25);
		$insertGoal = $this->pdo->prepare('INSERT INTO stock_goals (account_id, stock_note, monthly_amount, effective_from) VALUES (?, ?, ?, ?)');
		$insertGoal->execute([$accountId, 'Wise (£WISE)', 400, '2026-09-01']);
		$insertGoal->execute([$accountId, 'wise', 999, '2026-09-01']);
		$insertGoal->execute([$accountId, 'wise', 200, '2026-08-01']);

		backfillStockTickers($this->pdo);

		self::assertSame(['Wise (£WISE)', 'Wise (£WISE)'], $this->entryNotes($accountId));

		$history = getStockGoalHistory($this->pdo, $accountId);

		self::assertSame(['wise (£wise)'], array_keys($history));
		self::assertSame(
			[['effective_from' => '2026-08-01', 'amount' => 200.0], ['effective_from' => '2026-09-01', 'amount' => 400.0]],
			$history['wise (£wise)']['history']
		);
	}

	public function testBackfillLeavesUnknownNamesAlone(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertBareNoteEntry($accountId, 'My private fund', 10);

		self::assertSame([], backfillStockTickers($this->pdo));
		self::assertSame(['My private fund'], $this->entryNotes($accountId));
	}

	/**
	 * @return array<int, string>
	 */
	private function entryNotes(int $accountId): array {
		$rawSql = $this->pdo->prepare('SELECT note FROM bank_entries WHERE account_id = ? ORDER BY id ASC');
		$rawSql->execute([$accountId]);

		return $rawSql->fetchAll(\PDO::FETCH_COLUMN);
	}

	private function insertBareNoteEntry(int $accountId, string $note, float $amount): void {
		$this->pdo->prepare(
			"INSERT INTO bank_entries (account_id, entry_date, type, method, amount, note) VALUES (?, '2026-09-02', 'investments', 'card', ?, ?)"
		)->execute([$accountId, $amount, $note]);
	}
}
