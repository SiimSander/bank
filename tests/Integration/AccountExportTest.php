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

		$this->pdo->prepare(
			'INSERT INTO win_cards (account_id, card_date) VALUES (?, ?)'
		)->execute([$accountId, '2026-09-01']);
		$cardId = (int) $this->pdo->lastInsertId();
		$this->pdo->prepare(
			'INSERT INTO win_card_items (card_id, title, status, position) VALUES (?, ?, ?, ?)'
		)->execute([$cardId, 'Test win', 'done', 0]);

		$export = buildAccountExport($this->pdo, $accountId);

		self::assertNotNull($export);
		self::assertSame('1.0', $export['export_version']);
		self::assertSame('Test User', $export['profile']['name']);
		self::assertCount(2, $export['bank_entries']);
		self::assertCount(1, $export['win_cards']);
		self::assertSame('Test win', $export['win_cards'][0]['items'][0]['title']);
		self::assertArrayNotHasKey('password', $export['profile']);
		self::assertArrayNotHasKey('session_id', $export['bank_connections'][0] ?? []);
	}

	public function testExportReturnsNullForMissingAccount(): void {
		self::assertNull(buildAccountExport($this->pdo, 99999));
	}
}
