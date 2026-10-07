<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class HistoryMonthVisibilityTest extends DatabaseTestCase {
	public function testDeactivatedTypeWithEntriesStillHasMonthlyStats(): void {
		putenv('TEST_TODAY=2026-09-21');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 1000, '2026-09-10');
		$this->addEntry($accountId, 'investments', 100, '2026-09-11');
		refreshMonthlyStats($this->pdo, $accountId, '2026-09-01');

		updateBankType($this->pdo, $accountId, (int) getBankTypeBySlug($this->pdo, $accountId, 'investments')['id'], [
			'is_active' => false,
		]);

		$monthRow = buildMonthlyStatsRow($this->pdo, $accountId, '2026-09-01');

		self::assertSame(100.0, $monthRow['type_stats']['investments']['actual']);
		self::assertSame(250.0, $monthRow['type_stats']['investments']['goal']);
	}

	public function testDeactivatedCustomTypeWithEntriesIsIncludedInHistoryGoalTypes(): void {
		putenv('TEST_TODAY=2026-09-21');
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

		$this->addEntry($accountId, 'dept', 50, '2026-09-12');
		updateBankType($this->pdo, $accountId, (int) getBankTypeBySlug($this->pdo, $accountId, 'dept')['id'], [
			'is_active' => false,
		]);

		$historyTypes = getHistoryCardTypes(getBankTypes($this->pdo, $accountId, false));
		$goalTypes = getHistoryGoalTrackingTypes($historyTypes);
		$slugs = array_column($goalTypes, 'slug');

		self::assertContains('dept', $slugs);
		self::assertNotContains('dept', array_column(getGoalTrackingTypes($historyTypes), 'slug'));
		self::assertTrue(typeHasEntriesInMonth($this->pdo, $accountId, 'dept', '2026-09-01'));
	}

	public function testMonthWithoutTypeEntriesHasNoGoalRowInTemplateLogic(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->addEntry($accountId, 'income', 1000, '2026-08-01');

		self::assertFalse(typeHasEntriesInMonth($this->pdo, $accountId, 'investments', '2026-08-01'));
	}
}
