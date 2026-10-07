<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class NetWorthTest extends DatabaseTestCase {
	public function testPotDepositMovesMoneyFromCardToPot(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'wallet_adjustment', 1000, '2026-09-01');
		$this->addEntry($accountId, 'savings', 200, '2026-09-02');

		$netWorth = getNetWorthAsOf($this->pdo, $accountId, '2026-09-02');

		self::assertSame(800.0, $netWorth['card']);
		self::assertSame(200.0, $netWorth['pots']['savings']);
		self::assertSame(1000.0, $netWorth['total']);
	}

	public function testPotWithdrawalReturnsMoneyToCard(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'wallet_adjustment', 1000, '2026-09-01');
		$this->addEntry($accountId, 'savings', 200, '2026-09-02');
		$this->addEntry($accountId, 'savings', -50, '2026-09-03');

		$netWorth = getNetWorthAsOf($this->pdo, $accountId, '2026-09-03');

		self::assertSame(850.0, $netWorth['card']);
		self::assertSame(150.0, $netWorth['pots']['savings']);
		self::assertSame(1000.0, $netWorth['total']);
	}

	public function testSnapshotMatchesLiveNetWorthCalculation(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'wallet_adjustment', 500, '2026-08-31');
		$this->addEntry($accountId, 'savings', 100, '2026-08-31');

		saveNetWorthSnapshot($this->pdo, $accountId, '2026-08-31');

		$live = getNetWorthAsOf($this->pdo, $accountId, '2026-08-31');
		$snapshot = getNetWorthSnapshot($this->pdo, $accountId, '2026-08-31');

		self::assertNotNull($snapshot);
		self::assertSame($live['total'], $snapshot['total']);
		self::assertSame($live['pots']['savings'], $snapshot['pots']['savings']);
	}

	public function testPensionDoesNotReduceCardBalance(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'wallet_adjustment', 1000, '2026-09-01');
		$this->addEntry($accountId, 'pension', 200, '2026-09-02');

		$netWorth = getNetWorthAsOf($this->pdo, $accountId, '2026-09-02');

		self::assertSame(1000.0, $netWorth['card']);
		self::assertSame(200.0, $netWorth['pots']['pension']);
		self::assertSame(1200.0, $netWorth['total']);
	}

	public function testGrowthBreakdownExcludesWalletOutTypes(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$result = createBankType($this->pdo, $accountId, [
			'slug' => 'dept',
			'label' => 'Dept',
			'color_hex' => '#888888',
			'income_percent' => null,
			'balance_mode' => 'wallet_out',
		]);
		self::assertTrue($result['success'], $result['error'] ?? 'createBankType failed');

		$this->addEntry($accountId, 'wallet_adjustment', 1000, '2026-08-15');
		$this->addEntry($accountId, 'income', 1000, '2026-08-15');
		$this->addEntry($accountId, 'dept', 50, '2026-08-20');
		refreshMonthlyStats($this->pdo, $accountId, '2026-08-01');

		$this->addEntry($accountId, 'income', 1000, '2026-09-10');
		$this->addEntry($accountId, 'savings', 100, '2026-09-10');
		$this->addEntry($accountId, 'dept', 25, '2026-09-11');
		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		$history = getNetWorthHistory($this->pdo, $accountId);
		$september = $history['2026-09-01'] ?? null;

		self::assertNotNull($september);
		self::assertArrayHasKey('savings', $september['growth']['types']);
		self::assertArrayNotHasKey('dept', $september['growth']['types']);
	}
}
