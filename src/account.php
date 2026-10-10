<?php

const ACCOUNT_EXPORT_VERSION = '1.0';

function getAccountForSettings(PDO $pdo, int $accountId): ?array {
	$statement = $pdo->prepare(
		'SELECT id, name, username, email, email_verified_at, guaranteed_monthly_income, created_at
		FROM accounts
		WHERE id = ?'
	);
	$statement->execute([$accountId]);
	$row = $statement->fetch();

	return $row !== false ? $row : null;
}

function getAccountPasswordHash(PDO $pdo, int $accountId): ?string {
	$statement = $pdo->prepare('SELECT password FROM accounts WHERE id = ?');
	$statement->execute([$accountId]);
	$row = $statement->fetchColumn();

	return $row !== false ? (string) $row : null;
}

function changeAccountPassword(
	PDO $pdo,
	int $accountId,
	string $currentPassword,
	string $newPassword,
	string $newPasswordConfirm,
): bool|string {
	if ($newPassword !== $newPasswordConfirm) {
		return 'New passwords do not match.';
	}

	$passwordError = validateAccountPassword($newPassword);
	if ($passwordError !== null) {
		return $passwordError;
	}

	$storedHash = getAccountPasswordHash($pdo, $accountId);
	if ($storedHash === null || !verifyAccountPassword($storedHash, $currentPassword)) {
		return 'Current password is incorrect.';
	}

	$update = $pdo->prepare('UPDATE accounts SET password = ? WHERE id = ?');
	$update->execute([hashAccountPassword($newPassword), $accountId]);

	logger()?->info('account', 'Password changed', ['account_id' => $accountId]);

	return true;
}

function buildAccountExport(PDO $pdo, int $accountId): ?array {
	$account = getAccountForSettings($pdo, $accountId);
	if ($account === null) {
		return null;
	}

	$types = $pdo->prepare(
		'SELECT slug, label, color_hex, income_percent, goal_income_from, balance_mode, is_active, is_system, show_in_pills, sort_order, created_at, updated_at
		FROM bank_entry_types
		WHERE account_id = ?
		ORDER BY sort_order ASC, label ASC'
	);
	$types->execute([$accountId]);

	$entries = $pdo->prepare(
		'SELECT entry_date, type, method, amount, note, entry_reference, is_pending, created_at, updated_at
		FROM bank_entries
		WHERE account_id = ?
		ORDER BY entry_date ASC, id ASC'
	);
	$entries->execute([$accountId]);

	$habits = $pdo->prepare(
		'SELECT id, title, position, created_date, archived_from_month, created_at FROM habits WHERE account_id = ? ORDER BY position ASC, id ASC'
	);
	$habits->execute([$accountId]);
	$habitsData = [];

	foreach ($habits->fetchAll() as $habit) {
		$logs = $pdo->prepare(
			'SELECT log_date, status, updated_at FROM habit_logs WHERE habit_id = ? ORDER BY log_date ASC'
		);
		$logs->execute([(int) $habit['id']]);
		$habitsData[] = [
			'title' => $habit['title'],
			'position' => (int) $habit['position'],
			'created_date' => $habit['created_date'],
			'archived_from_month' => $habit['archived_from_month'],
			'created_at' => $habit['created_at'],
			'logs' => $logs->fetchAll(),
		];
	}

	$monthlyStats = $pdo->prepare(
		'SELECT stat_month, income, wallet_adjustment, created_at, updated_at FROM monthly_stats WHERE account_id = ? ORDER BY stat_month ASC'
	);
	$monthlyStats->execute([$accountId]);

	$monthlyStatByType = $pdo->prepare(
		'SELECT stat_month, type_slug, goal_amount, actual_amount FROM monthly_stat_by_type WHERE account_id = ? ORDER BY stat_month ASC, type_slug ASC'
	);
	$monthlyStatByType->execute([$accountId]);

	$netWorth = $pdo->prepare(
		'SELECT snapshot_date, card, cash, savings, investments, pension, total, created_at
		FROM net_worth_snapshots
		WHERE account_id = ?
		ORDER BY snapshot_date ASC'
	);
	$netWorth->execute([$accountId]);

	$stockGoals = $pdo->prepare(
		'SELECT stock_note, monthly_amount, effective_from, created_at, updated_at
		FROM stock_goals
		WHERE account_id = ?
		ORDER BY stock_note ASC, effective_from ASC'
	);
	$stockGoals->execute([$accountId]);

	$stockGoalColors = $pdo->prepare(
		'SELECT stock_key, color, created_at, updated_at
		FROM stock_goal_colors
		WHERE account_id = ?
		ORDER BY stock_key ASC'
	);
	$stockGoalColors->execute([$accountId]);

	$stockGoalLogos = $pdo->prepare(
		'SELECT stock_key, logo_slug, created_at, updated_at
		FROM stock_goal_logos
		WHERE account_id = ?
		ORDER BY stock_key ASC'
	);
	$stockGoalLogos->execute([$accountId]);

	$connections = $pdo->prepare(
		'SELECT aspsp_name, aspsp_country, bank_account_uid, iban, valid_until, is_main_account, last_synced_at, last_sync_attempt_at, created_at
		FROM bank_connections
		WHERE account_id = ?
		ORDER BY created_at ASC'
	);
	$connections->execute([$accountId]);

	$consents = getAccountConsents($pdo, $accountId);

	return [
		'export_version' => ACCOUNT_EXPORT_VERSION,
		'exported_at' => date('c'),
		'profile' => [
			'name' => $account['name'],
			'username' => $account['username'],
			'email' => $account['email'],
			'email_verified_at' => $account['email_verified_at'],
			'guaranteed_monthly_income' => $account['guaranteed_monthly_income'] !== null
				? (float) $account['guaranteed_monthly_income']
				: null,
			'created_at' => $account['created_at'],
		],
		'bank_entry_types' => $types->fetchAll(),
		'bank_entries' => $entries->fetchAll(),
		'habits' => $habitsData,
		'monthly_stats' => $monthlyStats->fetchAll(),
		'monthly_stat_by_type' => $monthlyStatByType->fetchAll(),
		'net_worth_snapshots' => $netWorth->fetchAll(),
		'stock_goals' => $stockGoals->fetchAll(),
		'stock_goal_colors' => $stockGoalColors->fetchAll(),
		'stock_goal_logos' => $stockGoalLogos->fetchAll(),
		'bank_connections' => $connections->fetchAll(),
		'consents' => $consents,
	];
}

function deleteAccount(PDO $pdo, int $accountId, string $password, string $usernameConfirm): bool|string {
	$account = getAccountForSettings($pdo, $accountId);
	if ($account === null) {
		return 'Account not found.';
	}

	if (!hash_equals(strtolower($account['username']), strtolower(trim($usernameConfirm)))) {
		return 'Username confirmation does not match.';
	}

	$storedHash = getAccountPasswordHash($pdo, $accountId);
	if ($storedHash === null || !verifyAccountPassword($storedHash, $password)) {
		return 'Password is incorrect.';
	}

	$pdo->beginTransaction();

	try {
		$pdo->prepare(
			'DELETE hl FROM habit_logs hl
			INNER JOIN habits h ON h.id = hl.habit_id
			WHERE h.account_id = ?'
		)->execute([$accountId]);

		$pdo->prepare('DELETE FROM habits WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM bank_entries WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM monthly_stat_by_type WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM monthly_stats WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM goal_miss_carryover WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM goal_miss_processed_days WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM net_worth_snapshots WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM stock_goals WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM stock_goal_colors WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM stock_goal_logos WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM bank_connections WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM bank_entry_types WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM password_reset_tokens WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM email_verification_tokens WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM account_consents WHERE account_id = ?')->execute([$accountId]);
		$pdo->prepare('DELETE FROM accounts WHERE id = ?')->execute([$accountId]);

		$pdo->commit();
	} catch (Throwable $e) {
		$pdo->rollBack();
		logger()?->error('account', 'Account deletion failed', [
			'account_id' => $accountId,
			'message' => $e->getMessage(),
		]);

		return 'Could not delete account. Try again.';
	}

	logger()?->info('account', 'Account deleted', [
		'account_id' => $accountId,
		'username' => $account['username'],
	]);

	return true;
}
