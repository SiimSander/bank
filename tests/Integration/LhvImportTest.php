<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class LhvImportTest extends DatabaseTestCase {
	public function testBookedTransactionsAreDeduplicatedByReference(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$payload = $this->fixtureTransactions();

		$firstImport = importLhvTransactionPayload($this->pdo, $accountId, $payload);
		$secondImport = importLhvTransactionPayload($this->pdo, $accountId, $payload);

		self::assertSame(4, $firstImport);
		self::assertSame(1, $secondImport);

		$count = $this->pdo->prepare('SELECT COUNT(*) FROM bank_entries WHERE account_id = ?');
		$count->execute([$accountId]);

		self::assertSame(4, (int) $count->fetchColumn());
	}

	public function testPendingTransactionsAreReplacedOnReimport(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$payload = $this->fixtureTransactions();

		importLhvTransactionPayload($this->pdo, $accountId, $payload);

		$updatedPayload = $payload;
		$updatedPayload[3]['transaction_amount']['amount'] = '20.00';
		importLhvTransactionPayload($this->pdo, $accountId, $updatedPayload);

		$pending = $this->pdo->prepare(
			'SELECT amount FROM bank_entries WHERE account_id = ? AND is_pending = 1 LIMIT 1'
		);
		$pending->execute([$accountId]);

		self::assertSame(20.0, (float) $pending->fetchColumn());
	}

	public function testValjamakseImportsAsNegativeSavings(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$withdrawal = $this->fixtureTransactions()[2];
		$withdrawal['remittance_information'] = ['Savings väljamakse'];
		importLhvTransactionPayload($this->pdo, $accountId, [$withdrawal]);

		$row = $this->pdo->prepare(
			"SELECT amount FROM bank_entries WHERE account_id = ? AND type = 'savings'"
		);
		$row->execute([$accountId]);

		self::assertSame(-80.0, (float) $row->fetchColumn());
	}

	/** @return list<array<string, mixed>> */
	private function fixtureTransactions(): array {
		$json = file_get_contents(dirname(__DIR__) . '/fixtures/lhv_transactions.json');
		self::assertNotFalse($json);

		$data = json_decode($json, true);
		self::assertIsArray($data);

		return $data;
	}
}
