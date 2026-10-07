<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class TypeActivationTest extends DatabaseTestCase {
	public function testReactivatingTypeCreatedThisMonthUsesCreationDate(): void {
		putenv('TEST_TODAY=2026-09-21');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$savingsType = getBankTypeBySlug($this->pdo, $accountId, 'savings');
		$this->pdo->prepare(
			'UPDATE bank_entry_types
			SET created_at = ?, goal_income_from = ?
			WHERE account_id = ? AND id = ?'
		)->execute(['2026-09-05 10:00:00', '2026-09-05', $accountId, $savingsType['id']]);

		updateBankType($this->pdo, $accountId, (int) $savingsType['id'], ['is_active' => false]);
		updateBankType($this->pdo, $accountId, (int) $savingsType['id'], ['is_active' => true]);

		$reactivated = getBankTypeBySlug($this->pdo, $accountId, 'savings');
		self::assertSame('2026-09-05', $reactivated['goal_income_from']);

		$this->addEntry($accountId, 'income', 1000, '2026-09-04');
		$this->addEntry($accountId, 'income', 1000, '2026-09-21');
		$goalTypes = getGoalTrackingTypes(getBankTypes($this->pdo, $accountId));

		self::assertSame(0.0, computeTypeDailyGoalAmount($this->pdo, $accountId, $reactivated, '2026-09-04'));
		self::assertSame(150.0, computeTypeDailyGoalAmount($this->pdo, $accountId, $reactivated, '2026-09-21'));

		$shortfalls = computeDailyGoalShortfalls($this->pdo, $accountId, '2026-09-04', $goalTypes);
		self::assertSame(0.0, $shortfalls['savings'] ?? 0.0);
	}

	public function testReactivatingTypeFromEarlierMonthStartsFromToday(): void {
		putenv('TEST_TODAY=2026-09-21');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->pdo->prepare(
			'UPDATE bank_entry_types
			SET created_at = ?, goal_income_from = ?
			WHERE account_id = ? AND slug = ?'
		)->execute(['2026-08-01 10:00:00', '2026-08-01', $accountId, 'savings']);

		updateBankType($this->pdo, $accountId, (int) getBankTypeBySlug($this->pdo, $accountId, 'savings')['id'], [
			'is_active' => false,
		]);
		updateBankType($this->pdo, $accountId, (int) getBankTypeBySlug($this->pdo, $accountId, 'savings')['id'], [
			'is_active' => true,
		]);

		$reactivated = getBankTypeBySlug($this->pdo, $accountId, 'savings');
		self::assertSame('2026-09-21', $reactivated['goal_income_from']);
	}

	public function testSameMonthReactivationUsesCreationDateForMonthlyGoal(): void {
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

		$deptType = getBankTypeBySlug($this->pdo, $accountId, 'dept');
		$this->pdo->prepare(
			'UPDATE bank_entry_types SET created_at = ? WHERE account_id = ? AND id = ?'
		)->execute(['2026-09-16 10:00:00', $accountId, $deptType['id']]);

		updateBankType($this->pdo, $accountId, (int) $deptType['id'], ['is_active' => false]);
		updateBankType($this->pdo, $accountId, (int) $deptType['id'], ['is_active' => true]);

		$reactivated = getBankTypeBySlug($this->pdo, $accountId, 'dept');
		self::assertSame('2026-09-16', $reactivated['goal_income_from']);

		$this->addEntry($accountId, 'income', 5000, '2026-09-01');
		$this->addEntry($accountId, 'income', 1000, '2026-09-20');

		$monthEnd = '2026-09-30';
		$fullMonthGoal = computeTypeDisplayGoalAmount($this->pdo, $accountId, $reactivated, '2026-09-01', $monthEnd);
		$trackedGoal = computeTypeMonthlyGoalAmount($this->pdo, $accountId, $reactivated, '2026-09-01', $monthEnd);

		self::assertSame(600.0, $fullMonthGoal);
		self::assertSame(100.0, $trackedGoal);
	}

	public function testNextMonthReactivationIgnoresEarlierIncome(): void {
		putenv('TEST_TODAY=2026-10-10');
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

		$deptType = getBankTypeBySlug($this->pdo, $accountId, 'dept');
		$this->pdo->prepare(
			'UPDATE bank_entry_types SET created_at = ?, goal_income_from = ?
			WHERE account_id = ? AND id = ?'
		)->execute(['2026-09-16 10:00:00', '2026-09-16', $accountId, $deptType['id']]);

		updateBankType($this->pdo, $accountId, (int) $deptType['id'], ['is_active' => false]);
		updateBankType($this->pdo, $accountId, (int) $deptType['id'], ['is_active' => true]);

		$reactivated = getBankTypeBySlug($this->pdo, $accountId, 'dept');
		self::assertSame('2026-10-10', $reactivated['goal_income_from']);

		$this->addEntry($accountId, 'income', 1000, '2026-10-01');
		$this->addEntry($accountId, 'income', 500, '2026-10-10');

		$trackedGoal = computeTypeMonthlyGoalAmount($this->pdo, $accountId, $reactivated, '2026-10-01', '2026-10-31');
		self::assertSame(50.0, $trackedGoal);
	}

	public function testNewTypeWithPercentStartsFromCreationDay(): void {
		putenv('TEST_TODAY=2026-09-21');
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		updateBankType($this->pdo, $accountId, (int) getBankTypeBySlug($this->pdo, $accountId, 'savings')['id'], [
			'income_percent' => 0.10,
		]);

		$result = createBankType($this->pdo, $accountId, [
			'label' => 'Debt',
			'slug' => 'debt',
			'color_hex' => '#60a5fa',
			'income_percent' => 0.05,
			'balance_mode' => 'pot',
		]);
		self::assertTrue($result['success'], $result['error'] ?? '');

		$type = getBankTypeBySlug($this->pdo, $accountId, 'debt');
		self::assertSame('2026-09-21', $type['goal_income_from']);
	}
}
