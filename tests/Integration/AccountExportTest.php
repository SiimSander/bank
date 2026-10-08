<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class AccountExportTest extends DatabaseTestCase {
	public function testExportIncludesUserDataAndExcludesSecrets(): void {
		$accountId = $this->createTestAccountWithPassword();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'income', 1000, '2026-09-01');
		$this->addEntry($accountId, 'savings', 150, '2026-09-01');

		putenv('TEST_TODAY=2026-09-01');
		$created = createHabit($this->pdo, $accountId, 'Test habit');
		self::assertTrue($created['success']);
		setHabitStatus($this->pdo, $accountId, $created['id'], '2026-09-01', 'done');

		$export = buildAccountExport($this->pdo, $accountId);

		self::assertNotNull($export);
		self::assertSame('1.0', $export['export_version']);
		self::assertSame('Test User', $export['profile']['name']);
		self::assertCount(2, $export['bank_entries']);
		self::assertCount(1, $export['habits']);
		self::assertSame('Test habit', $export['habits'][0]['title']);
		self::assertSame('done', $export['habits'][0]['logs'][0]['status']);
		self::assertArrayNotHasKey('password', $export['profile']);
		self::assertArrayNotHasKey('session_id', $export['bank_connections'][0] ?? []);
	}

	public function testExportReturnsNullForMissingAccount(): void {
		self::assertNull(buildAccountExport($this->pdo, 99999));
	}
}
