<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class AccountDeleteTest extends DatabaseTestCase {
	private const PASSWORD = 'test-password12';

	public function testDeleteAccountRemovesAllData(): void {
		$accountId = $this->createTestAccountWithPassword(self::PASSWORD);
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'income', 500, '2026-09-01');

		$account = getAccountForSettings($this->pdo, $accountId);
		self::assertNotNull($account);

		$result = deleteAccount($this->pdo, $accountId, self::PASSWORD, $account['username']);
		self::assertTrue($result);
		self::assertNull(getAccountForSettings($this->pdo, $accountId));

		$entries = $this->pdo->prepare('SELECT COUNT(*) FROM bank_entries WHERE account_id = ?');
		$entries->execute([$accountId]);
		self::assertSame(0, (int) $entries->fetchColumn());
	}

	public function testDeleteAccountRejectsWrongPassword(): void {
		$accountId = $this->createTestAccountWithPassword(self::PASSWORD);
		$account = getAccountForSettings($this->pdo, $accountId);

		$result = deleteAccount($this->pdo, $accountId, 'wrong-password', $account['username']);
		self::assertSame('Password is incorrect.', $result);
		self::assertNotNull(getAccountForSettings($this->pdo, $accountId));
	}

	public function testDeleteAccountRejectsWrongUsername(): void {
		$accountId = $this->createTestAccountWithPassword(self::PASSWORD);

		$result = deleteAccount($this->pdo, $accountId, self::PASSWORD, 'wrong-user');
		self::assertSame('Username confirmation does not match.', $result);
		self::assertNotNull(getAccountForSettings($this->pdo, $accountId));
	}

	public function testChangePasswordUpdatesHash(): void {
		$accountId = $this->createTestAccountWithPassword(self::PASSWORD);

		$result = changeAccountPassword(
			$this->pdo,
			$accountId,
			self::PASSWORD,
			'new-password99',
			'new-password99',
		);
		self::assertTrue($result);

		$hash = getAccountPasswordHash($this->pdo, $accountId);
		self::assertNotNull($hash);
		self::assertTrue(verifyAccountPassword($hash, 'new-password99'));
		self::assertFalse(verifyAccountPassword($hash, self::PASSWORD));
	}
}
