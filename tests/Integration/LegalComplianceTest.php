<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class LegalComplianceTest extends DatabaseTestCase {
	protected function setUp(): void {
		parent::setUp();
		\AppConfig::overrideForTesting([
			'LEGAL_TERMS_VERSION' => '2026-09-19',
			'LEGAL_PRIVACY_VERSION' => '2026-09-19',
			'LEGAL_BANK_AIS_VERSION' => '2026-09-19',
		]);
	}

	public function testRecordSignupConsentsStoresTermsAndPrivacy(): void {
		$accountId = $this->createTestAccount();

		recordSignupConsents($this->pdo, $accountId);

		self::assertTrue(hasAcceptedConsent($this->pdo, $accountId, CONSENT_TYPE_TERMS, legalTermsVersion()));
		self::assertTrue(hasAcceptedConsent($this->pdo, $accountId, CONSENT_TYPE_PRIVACY, legalPrivacyVersion()));
	}

	public function testNeedsLegalReconsentWhenVersionChanges(): void {
		$accountId = $this->createTestAccount();
		recordSignupConsents($this->pdo, $accountId);

		self::assertFalse(needsLegalReconsent($this->pdo, $accountId));

		\AppConfig::overrideForTesting(['LEGAL_TERMS_VERSION' => '2026-10-01']);

		self::assertTrue(needsLegalReconsent($this->pdo, $accountId));
	}

	public function testAcceptUpdatedLegalDocumentsRecordsNewVersions(): void {
		$accountId = $this->createTestAccount();
		recordSignupConsents($this->pdo, $accountId);

		\AppConfig::overrideForTesting([
			'LEGAL_TERMS_VERSION' => '2026-10-01',
			'LEGAL_PRIVACY_VERSION' => '2026-10-01',
		]);

		acceptUpdatedLegalDocuments($this->pdo, $accountId);

		self::assertFalse(needsLegalReconsent($this->pdo, $accountId));
	}

	public function testDisconnectBankKeepsImportedEntriesByDefault(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->pdo->prepare(
			'INSERT INTO bank_connections (account_id, aspsp_name, aspsp_country, session_id, bank_account_uid, iban, valid_until, is_main_account)
			VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
		)->execute([
			$accountId,
			'LHV Pank',
			'EE',
			'session-1',
			'uid-1',
			'EE123',
			date('Y-m-d H:i:s', time() + 86400),
		]);

		$this->pdo->prepare(
			'INSERT INTO bank_entries (account_id, entry_date, type, method, amount, entry_reference)
			VALUES (?, ?, ?, ?, ?, ?)'
		)->execute([$accountId, '2026-09-01', 'income', 'card', 100, 'ref-imported']);

		$result = disconnectBankConnection($this->pdo, $accountId, 'uid-1', false);

		self::assertSame(true, $result);
		self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM bank_connections')->fetchColumn());
		self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM bank_entries')->fetchColumn());
	}

	public function testDisconnectBankCanRemoveImportedEntries(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);

		$this->pdo->prepare(
			'INSERT INTO bank_connections (account_id, aspsp_name, aspsp_country, session_id, bank_account_uid, iban, valid_until, is_main_account)
			VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
		)->execute([
			$accountId,
			'LHV Pank',
			'EE',
			'session-1',
			'uid-1',
			'EE123',
			date('Y-m-d H:i:s', time() + 86400),
		]);

		$this->pdo->prepare(
			'INSERT INTO bank_entries (account_id, entry_date, type, method, amount, entry_reference)
			VALUES (?, ?, ?, ?, ?, ?), (?, ?, ?, ?, ?, NULL)'
		)->execute([
			$accountId, '2026-09-01', 'income', 'card', 100, 'ref-imported',
			$accountId, '2026-09-02', 'income', 'card', 50,
		]);

		$result = disconnectBankConnection($this->pdo, $accountId, 'uid-1', true);

		self::assertSame(true, $result);
		self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM bank_entries')->fetchColumn());
	}

	public function testExportIncludesConsents(): void {
		$accountId = $this->createTestAccount();
		recordSignupConsents($this->pdo, $accountId);

		$export = buildAccountExport($this->pdo, $accountId);

		self::assertNotNull($export);
		self::assertCount(2, $export['consents']);
	}

	public function testLegalContentHelpersReturnHtml(): void {
		self::assertStringContainsString('Data controller', renderPrivacyPolicyHtml());
		self::assertStringContainsString('not a bank', strtolower(renderTermsOfUseHtml()));
	}

	public function testSanitizeLegalReturnPathRejectsExternalAndLegalPages(): void {
		self::assertSame('/login', sanitizeLegalReturnPath('/login'));
		self::assertSame('/settings', sanitizeLegalReturnPath('/settings?tab=legal'));
		self::assertNull(sanitizeLegalReturnPath('https://evil.test/login'));
		self::assertNull(sanitizeLegalReturnPath('//evil.test/login'));
		self::assertNull(sanitizeLegalReturnPath('/privacy'));
	}

	public function testLegalReturnPathUsesQueryThenCurrentPage(): void {
		$originalGet = $_GET;
		$_GET['return'] = '/login';

		try {
			self::assertSame('/login', legalReturnPath('/privacy'));
			unset($_GET['return']);
			self::assertSame('/', legalReturnPath('/privacy'));
			self::assertSame('/settings', legalReturnPath('/settings'));
		} finally {
			$_GET = $originalGet;
		}
	}

	public function testLegalPageHrefPreservesReturnTarget(): void {
		$originalGet = $_GET;
		$originalUri = $GLOBALS['uri'] ?? null;
		$_GET['return'] = '/login';
		$GLOBALS['uri'] = '/privacy';

		try {
			self::assertSame('/terms?return=%2Flogin', legalPageHref('/terms'));
			self::assertSame('/privacy?return=%2Fsettings', legalPageHref('/privacy', '/settings'));
			self::assertSame('/privacy?return=%2Fbank%2Flhv%2Fconnect#bank-data', legalPageHref('/privacy', '/bank/lhv/connect', 'bank-data'));
		} finally {
			$_GET = $originalGet;

			if ($originalUri === null) {
				unset($GLOBALS['uri']);
			} else {
				$GLOBALS['uri'] = $originalUri;
			}
		}
	}
}
