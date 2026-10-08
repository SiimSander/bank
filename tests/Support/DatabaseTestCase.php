<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase {
	protected \PDO $pdo;

	protected function setUp(): void {
		parent::setUp();
		putenv('TEST_TODAY=2026-09-15');
		$this->pdo = db();
		$this->truncateTables();
	}

	protected function truncateTables(): void {
		$this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
		foreach ([
			'habit_logs',
			'habits',
			'bank_entry_type_income_percent_history',
			'bank_entries',
			'monthly_stat_by_type',
			'monthly_stats',
			'net_worth_snapshots',
			'stock_goals',
			'stock_goal_colors',
			'goal_miss_carryover',
			'goal_miss_processed_days',
			'bank_connections',
			'bank_entry_types',
			'password_reset_tokens',
			'email_verification_tokens',
			'account_consents',
			'accounts',
			'users',
		] as $table) {
			$this->pdo->exec("TRUNCATE TABLE `{$table}`");
		}
		$this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
	}

	protected function createTestAccount(): int {
		return $this->createTestAccountWithPassword('hashed-password');
	}

	protected function createTestAccountWithPassword(string $password = 'test-password12'): int {
		$suffix = bin2hex(random_bytes(4));
		$this->pdo->prepare(
			'INSERT INTO accounts (name, username, email, password) VALUES (?, ?, ?, ?)'
		)->execute([
			'Test User',
			"test_{$suffix}",
			"test_{$suffix}@example.com",
			hashAccountPassword($password),
		]);

		return (int) $this->pdo->lastInsertId();
	}

	protected function seedTypes(int $accountId): void {
		$existing = $this->pdo->prepare('SELECT COUNT(*) FROM bank_entry_types WHERE account_id = ?');
		$existing->execute([$accountId]);

		if ((int) $existing->fetchColumn() === 0) {
			seedBankTypesFromDefinitions($this->pdo, $accountId, TypeFixtures::classicDefinitions());
		}

		getBankTypes($this->pdo, $accountId);

		$this->pdo->prepare(
			'UPDATE bank_entry_types SET created_at = ? WHERE account_id = ?'
		)->execute([\Clock::monthStart() . ' 08:00:00', $accountId]);

		foreach (getBankTypes($this->pdo, $accountId, false) as $type) {
			if ($type['income_percent'] === null) {
				continue;
			}

			$this->pdo->prepare(
				'DELETE FROM bank_entry_type_income_percent_history WHERE bank_entry_type_id = ?'
			)->execute([(int) $type['id']]);

			recordIncomePercentSegment(
				$this->pdo,
				(int) $type['id'],
				'1970-01-01',
				(float) $type['income_percent']
			);
		}
	}

	protected function addEntry(
		int $accountId,
		string $type,
		float $amount,
		string $entryDate,
		string $method = 'card',
		?string $note = null,
	): void {
		$success = createBankEntry($this->pdo, $accountId, $type, $method, $amount, $note, $entryDate);
		self::assertTrue($success, "Failed to create {$type} entry on {$entryDate}");
	}

	protected function goalTypes(int $accountId): array {
		return getGoalTrackingTypes(getBankTypes($this->pdo, $accountId));
	}
}
