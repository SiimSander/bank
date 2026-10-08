<?php

const CONSENT_TYPE_TERMS = 'terms';
const CONSENT_TYPE_PRIVACY = 'privacy';
const CONSENT_TYPE_BANK_AIS = 'bank_ais';

function appDisplayName(): string {
	return AppConfig::get('APP_NAME', 'The Vault') ?? 'The Vault';
}

/** @var list<string> */
const LEGAL_PAGE_PATHS = ['/privacy', '/terms', '/about'];

function isLegalPagePath(string $path): bool {
	return in_array($path, LEGAL_PAGE_PATHS, true);
}

function sanitizeLegalReturnPath(mixed $path): ?string {
	if (!is_string($path) || $path === '') {
		return null;
	}

	if (preg_match('#^([a-z][a-z0-9+\-.]*:|//)#i', $path) || !str_starts_with($path, '/')) {
		return null;
	}

	$pathOnly = parse_url($path, PHP_URL_PATH);

	if (!is_string($pathOnly) || !str_starts_with($pathOnly, '/') || str_starts_with($pathOnly, '//')) {
		return null;
	}

	if (isLegalPagePath($pathOnly)) {
		return null;
	}

	return $pathOnly;
}

function legalReturnPath(?string $currentUri = null): string {
	$fromQuery = sanitizeLegalReturnPath($_GET['return'] ?? null);

	if ($fromQuery !== null) {
		return $fromQuery;
	}

	$currentUri = $currentUri ?? '/';

	if (isLegalPagePath($currentUri)) {
		return '/';
	}

	return $currentUri;
}

function legalReturnLabel(string $returnPath): string {
	return match ($returnPath) {
		'/' => 'Home',
		'/login' => 'Log in',
		'/signup' => 'Sign up',
		'/settings' => 'Settings',
		'/bank' => 'Bank',
		'/bank/history' => 'History',
		'/bank/lhv' => 'LHV',
		'/bank/lhv/connect' => 'Connect bank',
		'/forgot-password' => 'Forgot password',
		default => 'Back',
	};
}

/** @var list<string> */
const APP_RETURN_BLOCKED_PATHS = ['/settings', '/login', '/signup', '/logout'];

function sanitizeAppReturnPath(mixed $path): ?string {
	$sanitized = sanitizeLegalReturnPath($path);

	if ($sanitized === null) {
		return null;
	}

	if (in_array($sanitized, APP_RETURN_BLOCKED_PATHS, true)) {
		return null;
	}

	return $sanitized;
}

function settingsReturnPath(string $fallback = '/landing'): string {
	return sanitizeAppReturnPath($_POST['return'] ?? $_GET['return'] ?? null) ?? $fallback;
}

function settingsPageHref(?string $returnPath = null): string {
	$returnPath = sanitizeAppReturnPath($returnPath) ?? sanitizeAppReturnPath($GLOBALS['uri'] ?? null);

	if ($returnPath === null) {
		return '/settings';
	}

	return '/settings?return=' . rawurlencode($returnPath);
}

/** @param array<string, int|string> $queryParams */
function settingsRedirectUrl(array $queryParams = []): string {
	$return = sanitizeAppReturnPath($_POST['return'] ?? $_GET['return'] ?? null);

	if ($return !== null) {
		$queryParams['return'] = $return;
	}

	if ($queryParams === []) {
		return '/settings';
	}

	return '/settings?' . http_build_query($queryParams);
}

function settingsReturnLabel(string $returnPath): string {
	return match ($returnPath) {
		'/landing' => 'Back to Home',
		'/calculator' => 'Back to Calculator',
		'/bank' => 'Back to Bank',
		'/bank/history' => 'Back to History',
		'/bank/configure' => 'Back to Configure Types',
		'/bank/lhv' => 'Back to LHV',
		'/bank/lhv/connect' => 'Back to Connect Bank',
		'/habits' => 'Back to Habits',
		'/habits/history' => 'Back to Habits History',
		default => 'Back',
	};
}

function legalPageHref(string $legalPath, ?string $returnPath = null, string $fragment = ''): string {
	if (!isLegalPagePath($legalPath)) {
		return $legalPath;
	}

	$returnPath = sanitizeLegalReturnPath($returnPath) ?? legalReturnPath($GLOBALS['uri'] ?? '/');
	$href = $legalPath . '?return=' . rawurlencode($returnPath);

	if ($fragment !== '') {
		$href .= '#' . ltrim($fragment, '#');
	}

	return $href;
}

/** @return array<string, string> */
function legalConfig(): array {
	return AppConfig::legalConfig();
}

function legalPrivacyVersion(): string {
	return legalConfig()['privacy_version'];
}

function legalTermsVersion(): string {
	return legalConfig()['terms_version'];
}

function legalBankAisVersion(): string {
	return legalConfig()['bank_ais_version'];
}

function requestIpAddress(): ?string {
	$ip = $_SERVER['REMOTE_ADDR'] ?? null;

	return is_string($ip) && $ip !== '' ? substr($ip, 0, 45) : null;
}

function requestUserAgent(): ?string {
	$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

	return is_string($userAgent) && $userAgent !== '' ? substr($userAgent, 0, 255) : null;
}

function recordAccountConsent(
	PDO $pdo,
	int $accountId,
	string $consentType,
	string $version,
): void {
	$insert = $pdo->prepare(
		'INSERT INTO account_consents (account_id, consent_type, version, ip_address, user_agent)
		VALUES (?, ?, ?, ?, ?)'
	);
	$insert->execute([
		$accountId,
		$consentType,
		$version,
		requestIpAddress(),
		requestUserAgent(),
	]);
}

function recordSignupConsents(PDO $pdo, int $accountId): void {
	recordAccountConsent($pdo, $accountId, CONSENT_TYPE_TERMS, legalTermsVersion());
	recordAccountConsent($pdo, $accountId, CONSENT_TYPE_PRIVACY, legalPrivacyVersion());
}

function recordBankAisConsent(PDO $pdo, int $accountId): void {
	recordAccountConsent($pdo, $accountId, CONSENT_TYPE_BANK_AIS, legalBankAisVersion());
}

function getLatestConsentVersion(PDO $pdo, int $accountId, string $consentType): ?string {
	$statement = $pdo->prepare(
		'SELECT version
		FROM account_consents
		WHERE account_id = ? AND consent_type = ?
		ORDER BY accepted_at DESC, id DESC
		LIMIT 1'
	);
	$statement->execute([$accountId, $consentType]);
	$version = $statement->fetchColumn();

	return $version !== false ? (string) $version : null;
}

function hasAcceptedConsent(PDO $pdo, int $accountId, string $consentType, string $requiredVersion): bool {
	$acceptedVersion = getLatestConsentVersion($pdo, $accountId, $consentType);

	return $acceptedVersion !== null && version_compare($acceptedVersion, $requiredVersion, '>=');
}

function needsLegalReconsent(PDO $pdo, int $accountId): bool {
	return !hasAcceptedConsent($pdo, $accountId, CONSENT_TYPE_TERMS, legalTermsVersion())
		|| !hasAcceptedConsent($pdo, $accountId, CONSENT_TYPE_PRIVACY, legalPrivacyVersion());
}

function acceptUpdatedLegalDocuments(PDO $pdo, int $accountId): void {
	recordSignupConsents($pdo, $accountId);
}

/** @return array<int, array<string, mixed>> */
function getAccountConsents(PDO $pdo, int $accountId): array {
	$statement = $pdo->prepare(
		'SELECT consent_type, version, accepted_at, ip_address, user_agent
		FROM account_consents
		WHERE account_id = ?
		ORDER BY accepted_at ASC, id ASC'
	);
	$statement->execute([$accountId]);

	return $statement->fetchAll();
}

function disconnectBankConnection(
	PDO $pdo,
	int $accountId,
	string $bankAccountUid,
	bool $removeImportedEntries,
): bool|string {
	$connection = $pdo->prepare(
		'SELECT bank_account_uid FROM bank_connections WHERE account_id = ? AND bank_account_uid = ?'
	);
	$connection->execute([$accountId, $bankAccountUid]);

	if ($connection->fetchColumn() === false) {
		return 'Bank connection not found.';
	}

	$pdo->beginTransaction();

	try {
		if ($removeImportedEntries) {
			$pdo->prepare(
				'DELETE FROM bank_entries WHERE account_id = ? AND entry_reference IS NOT NULL'
			)->execute([$accountId]);
		}

		$pdo->prepare(
			'DELETE FROM bank_connections WHERE account_id = ? AND bank_account_uid = ?'
		)->execute([$accountId, $bankAccountUid]);

		$pdo->commit();
	} catch (Throwable $e) {
		$pdo->rollBack();
		logger()?->error('legal', 'Bank disconnect failed', [
			'account_id' => $accountId,
			'bank_account_uid' => $bankAccountUid,
			'message' => $e->getMessage(),
		]);

		return 'Could not disconnect bank. Try again.';
	}

	logger()?->info('legal', 'Bank connection disconnected', [
		'account_id' => $accountId,
		'bank_account_uid' => $bankAccountUid,
		'remove_imported_entries' => $removeImportedEntries,
	]);

	refreshMonthlyStats($pdo, $accountId, date('Y-m-01'));

	return true;
}

function isBankConnectionExpired(array $connection): bool {
	return strtotime((string) $connection['valid_until']) < time();
}
