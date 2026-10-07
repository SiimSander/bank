<?php

require_once __DIR__ . '/Clock.php';

function saveBankConnection(PDO $pdo, int $userId, array $session): void {
	$rawSql = $pdo->prepare(
		'INSERT INTO bank_connections (account_id, aspsp_name, aspsp_country, session_id, bank_account_uid, iban, valid_until)
		VALUES (?, ?, ?, ?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE
			session_id = VALUES(session_id),
			valid_until = VALUES(valid_until)'
	);

	$validUntil = (new DateTime($session['access']['valid_until']))->format('Y-m-d H:i:s');

	foreach ($session['accounts'] as $account) {
		$rawSql->execute([
			$userId,
			$session['aspsp']['name'],
			$session['aspsp']['country'],
			$session['session_id'],
			$account['uid'],
			$account['account_id']['iban'],
			$validUntil,
		]);
	}
}

// PSD2 (EBA RTS Article 36(5)(b)) caps unattended/background account data access at 4 pulls
// per 24 hours per connection. Spacing successful syncs 6 hours apart keeps us at or under
// that cap even with frequent page visits throughout the day.
const LHV_SYNC_SUCCESS_THROTTLE_MINUTES = 360;
// A much shorter minimum gap between *attempts* (successful or not), so a rate-limit error
// or outage doesn't get hammered again on every single page load.
const LHV_SYNC_RETRY_THROTTLE_MINUTES = 15;

function getBankConnections(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare(
		'SELECT bank_account_uid, iban, session_id, valid_until, is_main_account, last_synced_at, last_sync_attempt_at
		FROM bank_connections
		WHERE account_id = ?'
	);
	$rawSql->execute([$userId]);

	return $rawSql->fetchAll();
}

function getMainBankConnection(PDO $pdo, int $userId): ?array {
	$rawSql = $pdo->prepare(
		'SELECT bank_account_uid, iban, session_id, valid_until, last_synced_at, last_sync_attempt_at
		FROM bank_connections
		WHERE account_id = ? AND is_main_account = 1'
	);
	$rawSql->execute([$userId]);

	$row = $rawSql->fetch();

	return $row !== false ? $row : null;
}

function setMainBankConnection(PDO $pdo, int $userId, string $bankAccountUid): void {
	$pdo->prepare('UPDATE bank_connections SET is_main_account = 0 WHERE account_id = ?')
		->execute([$userId]);
	$pdo->prepare('UPDATE bank_connections SET is_main_account = 1 WHERE account_id = ? AND bank_account_uid = ?')
		->execute([$userId, $bankAccountUid]);
}

function markBankConnectionSynced(PDO $pdo, int $userId, string $bankAccountUid): void {
	$pdo->prepare('UPDATE bank_connections SET last_synced_at = NOW() WHERE account_id = ? AND bank_account_uid = ?')
		->execute([$userId, $bankAccountUid]);
}

function markBankConnectionSyncAttempt(PDO $pdo, int $userId, string $bankAccountUid): void {
	$pdo->prepare('UPDATE bank_connections SET last_sync_attempt_at = NOW() WHERE account_id = ? AND bank_account_uid = ?')
		->execute([$userId, $bankAccountUid]);
}

function autoSyncMainLhvAccount(PDO $pdo, int $userId): ?int {
	$mainConnection = getMainBankConnection($pdo, $userId);

	if ($mainConnection === null) {
		return null;
	}

	$lastSyncedAt = $mainConnection['last_synced_at'];
	$lastAttemptAt = $mainConnection['last_sync_attempt_at'];

	if ($lastSyncedAt !== null && strtotime($lastSyncedAt) > time() - LHV_SYNC_SUCCESS_THROTTLE_MINUTES * 60) {
		return null;
	}

	if ($lastAttemptAt !== null && strtotime($lastAttemptAt) > time() - LHV_SYNC_RETRY_THROTTLE_MINUTES * 60) {
		return null;
	}

	markBankConnectionSyncAttempt($pdo, $userId, $mainConnection['bank_account_uid']);

	try {
		$imported = importLhvTransactions($pdo, $userId, $mainConnection['bank_account_uid']);
		markBankConnectionSynced($pdo, $userId, $mainConnection['bank_account_uid']);

		return $imported;
	} catch (Throwable $e) {
		logger()?->warning('sync', 'LHV auto-sync failed', [
			'account_id' => $userId,
			'bank_account_uid' => $mainConnection['bank_account_uid'],
			'error' => $e->getMessage(),
		]);

		return null;
	}
}

function lhvClassifyTransactionType(string $note, string $creditDebitIndicator): string {
	if (stripos($note, 'kogumiskonto') !== false) {
		return 'kogumiskonto';
	}

	if (stripos($note, 'savings') !== false) {
		return 'savings';
	}

	return $creditDebitIndicator === 'CRDT' ? 'income' : 'expenses';
}

function lhvTransactionAmount(string $type, string $note, float $amount): float {
	// A "väljamakse" is money moving out of a savings pot back to the real account,
	// so it must reduce the pot's total instead of adding to it.
	if (in_array($type, ['savings', 'kogumiskonto'], true) && stripos($note, 'väljamakse') !== false) {
		return -$amount;
	}

	return $amount;
}

function importLhvTransactionPayload(PDO $pdo, int $userId, array $transactions): int {
	$imported = 0;
	$affectedMonths = [];

	$oldPending = $pdo->prepare('SELECT entry_date FROM bank_entries WHERE account_id = ? AND is_pending = 1');
	$oldPending->execute([$userId]);
	foreach ($oldPending->fetchAll(PDO::FETCH_COLUMN) as $oldEntryDate) {
		$affectedMonths[date('Y-m-01', strtotime($oldEntryDate))] = true;
	}
	$pdo->prepare('DELETE FROM bank_entries WHERE account_id = ? AND is_pending = 1')->execute([$userId]);

	$insertBooked = $pdo->prepare(
		'INSERT IGNORE INTO bank_entries (account_id, entry_date, type, method, amount, note, entry_reference, is_pending)
		VALUES (?, ?, ?, ?, ?, ?, ?, 0)'
	);
	$insertPending = $pdo->prepare(
		'INSERT INTO bank_entries (account_id, entry_date, type, method, amount, note, entry_reference, is_pending)
		VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
	);

	foreach ($transactions as $transaction) {
		if (!in_array($transaction['status'], ['BOOK', 'PDNG'], true)) {
			continue;
		}

		$entryDate = $transaction['booking_date'] ?: ($transaction['value_date'] ?: ($transaction['transaction_date'] ?: Clock::today()));
		$note = trim(implode(' ', $transaction['remittance_information'] ?? []));
		$type = lhvClassifyTransactionType($note, $transaction['credit_debit_indicator']);
		$amount = lhvTransactionAmount($type, $note, (float) $transaction['transaction_amount']['amount']);
		$params = [
			$userId,
			$entryDate,
			$type,
			'card',
			$amount,
			$note !== '' ? substr($note, 0, 150) : null,
			$transaction['entry_reference'],
		];

		if ($transaction['status'] === 'PDNG') {
			$insertPending->execute($params);
			$imported++;
			$affectedMonths[date('Y-m-01', strtotime($entryDate))] = true;
			continue;
		}

		$insertBooked->execute($params);

		if ($insertBooked->rowCount() > 0) {
			$imported++;
			$affectedMonths[date('Y-m-01', strtotime($entryDate))] = true;
		}
	}

	foreach (array_keys($affectedMonths) as $statMonth) {
		refreshMonthlyStats($pdo, $userId, $statMonth);
	}

	return $imported;
}

function importLhvTransactions(PDO $pdo, int $userId, string $bankAccountUid): int {
	$result = enableBankingGetAccountTransactions($bankAccountUid);

	if ($result['status'] !== 200) {
		$error = $result['body']['error'] ?? $result['status'];
		throw new RuntimeException("Enable Banking transactions request failed: $error");
	}

	return importLhvTransactionPayload($pdo, $userId, $result['body']['transactions'] ?? []);
}
