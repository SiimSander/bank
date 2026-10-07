<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class HistoryMonthTypesTest extends DatabaseTestCase {
	protected function tearDown(): void {
		putenv('TEST_TODAY=2026-09-15');
		parent::tearDown();
	}

	public function testDefaultTypeShowsOnEarlierMonthDespiteLaterCreatedAt(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->pdo->prepare(
			'UPDATE bank_entry_types SET created_at = ? WHERE account_id = ? AND slug = ?'
		)->execute(['2026-09-16 10:00:00', $accountId, 'savings']);

		$this->addEntry($accountId, 'savings', 100, '2026-08-10');
		$savingsType = getBankTypeBySlug($this->pdo, $accountId, 'savings');

		self::assertTrue(typeAppliesToHistoryMonth($this->pdo, $accountId, $savingsType, '2026-08-01'));
	}

	public function testCustomTypeWithoutEntriesIsHiddenBeforeCreationMonth(): void {
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
			'UPDATE bank_entry_types SET created_at = ? WHERE account_id = ? AND slug = ?'
		)->execute(['2026-09-16 10:00:00', $accountId, 'dept']);

		$deptType = getBankTypeBySlug($this->pdo, $accountId, 'dept');

		self::assertFalse(typeAppliesToHistoryMonth($this->pdo, $accountId, $deptType, '2026-08-01'));
		self::assertTrue(typeAppliesToHistoryMonth($this->pdo, $accountId, $deptType, '2026-09-01'));
	}

	public function testTypeWithEntriesShowsEvenWhenCreatedLater(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->pdo->prepare(
			'UPDATE bank_entry_types SET created_at = ? WHERE account_id = ? AND slug = ?'
		)->execute(['2026-09-16 10:00:00', $accountId, 'pension']);

		$this->addEntry($accountId, 'pension', 50, '2026-08-12');
		$pensionType = getBankTypeBySlug($this->pdo, $accountId, 'pension');

		self::assertTrue(typeAppliesToHistoryMonth($this->pdo, $accountId, $pensionType, '2026-08-01'));
	}

	public function testReactivationInLaterMonthHidesInactiveMonthsWithoutEntries(): void {
		putenv('TEST_TODAY=2026-10-15');
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
			'UPDATE bank_entry_types SET created_at = ?, goal_income_from = ?
			WHERE account_id = ? AND slug = ?'
		)->execute(['2026-09-16 10:00:00', '2026-09-16', $accountId, 'dept']);

		updateBankType($this->pdo, $accountId, (int) getBankTypeBySlug($this->pdo, $accountId, 'dept')['id'], [
			'is_active' => false,
		]);
		updateBankType($this->pdo, $accountId, (int) getBankTypeBySlug($this->pdo, $accountId, 'dept')['id'], [
			'is_active' => true,
		]);

		$deptType = getBankTypeBySlug($this->pdo, $accountId, 'dept');
		self::assertSame('2026-10-15', $deptType['goal_income_from']);

		self::assertFalse(typeAppliesToHistoryMonth($this->pdo, $accountId, $deptType, '2026-09-01'));
	}
}
