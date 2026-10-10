<?php

require_once __DIR__ . '/BankRequestCache.php';
require_once __DIR__ . '/Clock.php';
require_once __DIR__ . '/bankTypes.php';
require_once __DIR__ . '/goalCalculations.php';
require_once __DIR__ . '/stockTickers.php';

const BANK_ENTRY_METHODS = ['card', 'cash'];
const BANK_DATE_RANGES = ['all', 'week', 'month', 'custom'];

function isValidIsoDate(?string $value): bool {
	if ($value === null) {
		return false;
	}

	$parsed = DateTime::createFromFormat('Y-m-d', $value);

	return $parsed !== false && $parsed->format('Y-m-d') === $value;
}

function parseBankDateRange(string $range, ?string $from = null, ?string $to = null): array {
	$today = date('Y-m-d');
	$range = in_array($range, BANK_DATE_RANGES, true) ? $range : 'all';

	if ($range === 'custom' && (!isValidIsoDate($from) || !isValidIsoDate($to))) {
		$range = 'all';
	}

	$boundaries = match ($range) {
		'week' => [
			'start' => date('Y-m-d', strtotime('Monday this week')),
			'end' => $today,
		],
		'month' => [
			'start' => date('Y-m-01'),
			'end' => $today,
		],
		'custom' => [
			'start' => min($from, $to),
			'end' => min(max($from, $to), $today),
		],
		default => [
			'start' => null,
			'end' => $today,
		],
	};

	if ($range === 'custom' && $boundaries['start'] > $boundaries['end']) {
		$boundaries['start'] = $boundaries['end'];
	}

	return [
		'range' => $range,
		'start' => $boundaries['start'],
		'end' => $boundaries['end'],
	];
}

function formatBankDateRangeShort(string $start, string $end): string {
	return date('d.m.y', strtotime($start)) . ' - ' . date('d.m.y', strtotime($end));
}

function bankDateRangeNoteBreakdownLabel(string $range, ?array $dateRange = null): string {
	if ($range === 'custom' && $dateRange !== null && $dateRange['start'] !== null) {
		return formatBankDateRangeShort($dateRange['start'], $dateRange['end']) . ' by note';
	}

	return match ($range) {
		'week' => 'This week by note',
		'month' => 'This month by note',
		default => 'All-time by note',
	};
}

function bankEntryMethodLabel(string $method): string {
	return match ($method) {
		'card' => 'Card',
		'cash' => 'Cash',
		default => $method,
	};
}

function isValidBankEntryType(PDO $pdo, int $userId, string $type, bool $forNewEntry = false): bool {
	$bankType = getBankTypeBySlug($pdo, $userId, $type);

	if ($bankType === null) {
		return false;
	}

	if ($forNewEntry && !$bankType['is_active']) {
		return false;
	}

	return true;
}

function normalizeBankEntryAmount(array $type, string $direction, float $amount): ?float {
	$amount = abs($amount);

	if ($amount <= 0) {
		return null;
	}

	$direction = $direction === 'out' ? 'out' : 'in';

	if ($direction === 'out' && !bankTypeSupportsWithdraw($type)) {
		return null;
	}

	return $direction === 'out' ? -$amount : $amount;
}

function createBankEntry(PDO $pdo, int $userId, string $type, string $method, float $amount, ?string $note, ?string $entryDate = null): bool {
	if (!isValidBankEntryType($pdo, $userId, $type, true) || !in_array($method, BANK_ENTRY_METHODS, true)) {
		return false;
	}

	$entryDate ??= Clock::today();
	$parsedDate = DateTime::createFromFormat('Y-m-d', $entryDate);

	if (!$parsedDate || $parsedDate->format('Y-m-d') !== $entryDate || $entryDate > Clock::today()) {
		return false;
	}

	if ($type === 'investments' && $note !== null) {
		$note = resolveStockNote($pdo, $userId, $note);
	}

	$rawSql = $pdo->prepare(
		'INSERT INTO bank_entries (account_id, entry_date, type, method, amount, note) VALUES (?, ?, ?, ?, ?, ?)'
	);
	$success = $rawSql->execute([$userId, $entryDate, $type, $method, $amount, $note]);

	if ($success) {
		refreshMonthlyStats($pdo, $userId, date('Y-m-01', strtotime($entryDate)));
		refreshNetWorthSnapshotIfCompleted($pdo, $userId, $entryDate);
	}

	return $success;
}

function applyOpeningBalances(PDO $pdo, int $accountId, float $bankBalance, float $cashBalance): bool {
	$pdo->beginTransaction();

	$today = date('Y-m-d');
	$note = 'Opening balance';

	if ($bankBalance > 0 && !createBankEntry($pdo, $accountId, 'wallet_adjustment', 'card', $bankBalance, $note, $today)) {
		$pdo->rollBack();

		return false;
	}

	if ($cashBalance > 0 && !createBankEntry($pdo, $accountId, 'wallet_adjustment', 'cash', $cashBalance, $note, $today)) {
		$pdo->rollBack();

		return false;
	}

	$pdo->commit();

	return true;
}

function getTodayBankEntries(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare(
		'SELECT be.id, be.type, be.method, be.amount, be.note, be.is_pending
		FROM bank_entries be
		INNER JOIN bank_entry_types bet
			ON bet.account_id = be.account_id
			AND bet.slug = be.type
			AND bet.is_active = 1
		WHERE be.account_id = ? AND be.entry_date = CURDATE()
		ORDER BY be.created_at DESC, be.id DESC'
	);
	$rawSql->execute([$userId]);

	return $rawSql->fetchAll();
}

function updateBankEntry(PDO $pdo, int $entryId, int $userId, string $type, string $method, float $amount, ?string $note): bool {
	if (!isValidBankEntryType($pdo, $userId, $type) || !in_array($method, BANK_ENTRY_METHODS, true)) {
		return false;
	}

	if ($type === 'investments' && $note !== null) {
		$note = resolveStockNote($pdo, $userId, $note);
	}

	$beforeSql = $pdo->prepare('SELECT type, amount FROM bank_entries WHERE id = ? AND account_id = ?');
	$beforeSql->execute([$entryId, $userId]);
	$before = $beforeSql->fetch();

	$rawSql = $pdo->prepare(
		'UPDATE bank_entries
		SET type = ?, method = ?, amount = ?, note = ?
		WHERE id = ? AND account_id = ?'
	);
	$rawSql->execute([$type, $method, $amount, $note, $entryId, $userId]);

	$updated = $rawSql->rowCount() > 0;
	if ($updated) {
		$dateSql = $pdo->prepare('SELECT entry_date FROM bank_entries WHERE id = ? AND account_id = ?');
		$dateSql->execute([$entryId, $userId]);
		$entryDate = $dateSql->fetchColumn();
		refreshMonthlyStats($pdo, $userId, date('Y-m-01', strtotime($entryDate)));
		refreshNetWorthSnapshotIfCompleted($pdo, $userId, $entryDate);
	}

	return $updated;
}

function deleteBankEntry(PDO $pdo, int $entryId, int $userId): bool {
	$beforeSql = $pdo->prepare('SELECT entry_date, type, amount FROM bank_entries WHERE id = ? AND account_id = ?');
	$beforeSql->execute([$entryId, $userId]);
	$before = $beforeSql->fetch();

	$rawSql = $pdo->prepare('DELETE FROM bank_entries WHERE id = ? AND account_id = ?');
	$rawSql->execute([$entryId, $userId]);
	$deleted = $rawSql->rowCount() > 0;

	if ($deleted && $before !== false) {
		$entryDate = $before['entry_date'];
		refreshMonthlyStats($pdo, $userId, date('Y-m-01', strtotime($entryDate)));
		refreshNetWorthSnapshotIfCompleted($pdo, $userId, $entryDate);
	}

	return $deleted;
}

function buildWalletBalanceExpression(array $types): string {
	$parts = ['0'];

	foreach ($types as $type) {
		$slug = $type['slug'];

		if ($type['balance_mode'] === 'wallet_in' || $type['balance_mode'] === 'adjustment') {
			$parts[] = "CASE WHEN type = '{$slug}' THEN amount ELSE 0 END";
		} elseif ($type['balance_mode'] === 'wallet_out') {
			$parts[] = "CASE WHEN type = '{$slug}' THEN -amount ELSE 0 END";
		} elseif ($type['balance_mode'] === 'pot' && $slug !== 'pension') {
			// Money moved to a savings/investment pot leaves the card/cash wallet.
			// Pension is tracked separately and does not reduce wallet (legacy behaviour).
			$parts[] = "CASE WHEN type = '{$slug}' THEN -amount ELSE 0 END";
		}
	}

	return implode(' + ', $parts);
}

function getBalanceByMethod(PDO $pdo, int $userId, string $method): float {
	$types = getBankTypes($pdo, $userId);
	$expression = buildWalletBalanceExpression($types);

	$rawSql = $pdo->prepare(
		"SELECT COALESCE(SUM({$expression}), 0) AS balance
		FROM bank_entries
		WHERE account_id = ? AND method = ?"
	);
	$rawSql->execute([$userId, $method]);

	return (float) $rawSql->fetchColumn();
}

function getBankBalance(PDO $pdo, int $userId): float {
	return getBalanceByMethod($pdo, $userId, 'card');
}

function getPotBalance(PDO $pdo, int $userId, string $type): float {
	$rawSql = $pdo->prepare(
		'SELECT COALESCE(SUM(amount), 0) FROM bank_entries WHERE account_id = ? AND type = ?'
	);
	$rawSql->execute([$userId, $type]);

	return (float) $rawSql->fetchColumn();
}

function getPotBalances(PDO $pdo, int $userId, bool $activeOnly = true): array {
	$types = getBankTypes($pdo, $userId, $activeOnly);
	$balances = [];

	$sumsByType = $pdo->prepare(
		'SELECT type, COALESCE(SUM(amount), 0) FROM bank_entries WHERE account_id = ? GROUP BY type'
	);
	$sumsByType->execute([$userId]);
	$sumsBySlug = $sumsByType->fetchAll(PDO::FETCH_KEY_PAIR);

	foreach ($types as $type) {
		if ($type['balance_mode'] !== 'pot') {
			continue;
		}

		$balances[$type['slug']] = (float) ($sumsBySlug[$type['slug']] ?? 0);
	}

	return $balances;
}

function getCashBalance(PDO $pdo, int $userId): float {
	return getBalanceByMethod($pdo, $userId, 'cash');
}

function buildHistorySelectSql(array $types): string {
	$parts = [];

	foreach ($types as $type) {
		$slug = $type['slug'];
		$parts[] = "COALESCE(SUM(CASE WHEN type = '{$slug}' THEN amount END), 0) AS `{$slug}`";
	}

	return implode(",\n\t\t\t", $parts);
}

function normalizeHistoryRow(array $row, array $types): array {
	$normalized = ['entry_date' => $row['entry_date']];

	foreach ($types as $type) {
		$slug = $type['slug'];
		$normalized[$slug] = (float) ($row[$slug] ?? 0);
	}

	return $normalized;
}

function getBankHistoryBetween(PDO $pdo, int $userId, ?string $startDate, string $endDate, bool $activeTypesOnly = true): array {
	$types = getBankTypes($pdo, $userId, $activeTypesOnly);
	$selectSql = buildHistorySelectSql($types);

	$sql = "SELECT entry_date,
			{$selectSql}
		FROM bank_entries
		WHERE account_id = ?";
	$params = [$userId];

	if ($startDate !== null) {
		$sql .= ' AND entry_date >= ?';
		$params[] = $startDate;
	}

	$sql .= ' AND entry_date <= ?
		GROUP BY entry_date
		ORDER BY entry_date DESC';
	$params[] = $endDate;

	$rawSql = $pdo->prepare($sql);
	$rawSql->execute($params);

	return array_map(
		fn(array $row) => normalizeHistoryRow($row, $types),
		$rawSql->fetchAll()
	);
}

function sumBankHistoryTotals(array $history, array $types): array {
	$totals = [];

	foreach ($types as $type) {
		$slug = $type['slug'];
		$totals[$slug] = array_sum(array_column($history, $slug));
	}

	return $totals;
}

function getBankEntriesByTypeBetween(PDO $pdo, int $userId, string $type, ?string $startDate, string $endDate, ?string $search = null): array {
	$sql = 'SELECT id, entry_date, method, amount, note, is_pending
		FROM bank_entries
		WHERE account_id = ? AND type = ?';
	$params = [$userId, $type];

	if ($startDate !== null) {
		$sql .= ' AND entry_date >= ?';
		$params[] = $startDate;
	}

	$sql .= ' AND entry_date <= ?';
	$params[] = $endDate;

	if ($search !== null && $search !== '') {
		$sql .= ' AND note LIKE ?';
		$params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search) . '%';
	}

	$sql .= ' ORDER BY entry_date DESC, id DESC';

	$rawSql = $pdo->prepare($sql);
	$rawSql->execute($params);

	return $rawSql->fetchAll();
}

function getMonthlyStats(PDO $pdo, int $userId, string $statMonth): ?array {
	return buildMonthlyStatsRow($pdo, $userId, $statMonth);
}

function buildMonthlyStatsRow(PDO $pdo, int $userId, string $statMonth): array {
	$rawSql = $pdo->prepare(
		'SELECT income, wallet_adjustment
		FROM monthly_stats
		WHERE account_id = ? AND stat_month = ?'
	);
	$rawSql->execute([$userId, $statMonth]);
	$row = $rawSql->fetch();

	$typeStats = getMonthlyStatByType($pdo, $userId, $statMonth);
	$types = getBankTypes($pdo, $userId);
	$typeStats = applyDisplayGoalsToMonthlyTypeStats($pdo, $userId, $statMonth, $types, $typeStats);

	return [
		'stat_month' => $statMonth,
		'income' => $row !== false ? (float) $row['income'] : 0.0,
		'wallet_adjustment' => $row !== false ? (float) $row['wallet_adjustment'] : 0.0,
		'type_stats' => $typeStats,
		'types' => $types,
	];
}

function getMonthlyStatByType(PDO $pdo, int $userId, string $statMonth): array {
	$rawSql = $pdo->prepare(
		'SELECT type_slug, goal_amount, actual_amount
		FROM monthly_stat_by_type
		WHERE account_id = ? AND stat_month = ?'
	);
	$rawSql->execute([$userId, $statMonth]);

	$stats = [];
	foreach ($rawSql->fetchAll() as $row) {
		$stats[$row['type_slug']] = [
			'goal' => (float) $row['goal_amount'],
			'actual' => (float) $row['actual_amount'],
		];
	}

	return $stats;
}

function defaultMonthlyStatsRow(string $statMonth): array {
	return [
		'stat_month' => $statMonth,
		'income' => 0.0,
		'wallet_adjustment' => 0.0,
		'type_stats' => [],
		'types' => [],
	];
}

function getMonthlyStatsHistory(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare(
		'SELECT stat_month, income, wallet_adjustment
		FROM monthly_stats
		WHERE account_id = ?
		ORDER BY stat_month DESC'
	);
	$rawSql->execute([$userId]);
	$history = [];

	foreach ($rawSql->fetchAll() as $row) {
		$history[] = buildMonthlyStatsRow($pdo, $userId, $row['stat_month']);
	}

	$currentStatMonth = date('Y-m-01');
	$hasCurrentMonth = false;
	foreach ($history as $month) {
		if ($month['stat_month'] === $currentStatMonth) {
			$hasCurrentMonth = true;
			break;
		}
	}

	if (!$hasCurrentMonth) {
		array_unshift($history, buildMonthlyStatsRow($pdo, $userId, $currentStatMonth));
	}

	return $history;
}

function refreshMonthlyStats(PDO $pdo, int $userId, string $statMonth): void {
	$types = getBankTypes($pdo, $userId);
	$slugs = array_map(fn(array $type) => $type['slug'], $types);
	$selectParts = ["COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income"];

	foreach ($types as $type) {
		if ($type['slug'] === 'income') {
			continue;
		}

		$slug = $type['slug'];
		$selectParts[] = "COALESCE(SUM(CASE WHEN type = '{$slug}' THEN amount END), 0) AS `{$slug}`";
	}

	$stats = $pdo->prepare(
		'SELECT ' . implode(', ', $selectParts) . '
		FROM bank_entries
		WHERE account_id = ?
			AND entry_date >= ?
			AND entry_date < ? + INTERVAL 1 MONTH'
	);
	$stats->execute([$userId, $statMonth, $statMonth]);
	$row = $stats->fetch();

	$income = (float) $row['income'];
	$walletAdjustment = (float) ($row['wallet_adjustment'] ?? 0);

	$upsert = $pdo->prepare(
		"INSERT INTO monthly_stats (account_id, stat_month, income, wallet_adjustment)
		VALUES (?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE
			income = VALUES(income),
			wallet_adjustment = VALUES(wallet_adjustment)"
	);
	$upsert->execute([$userId, $statMonth, $income, $walletAdjustment]);

	$deleteTypeStats = $pdo->prepare(
		'DELETE FROM monthly_stat_by_type WHERE account_id = ? AND stat_month = ?'
	);
	$deleteTypeStats->execute([$userId, $statMonth]);

	$insertTypeStat = $pdo->prepare(
		'INSERT INTO monthly_stat_by_type (account_id, stat_month, type_slug, goal_amount, actual_amount)
		VALUES (?, ?, ?, ?, ?)'
	);

	$monthEnd = date('Y-m-t', strtotime($statMonth));

	foreach ($types as $type) {
		if ($type['slug'] === 'income' || $type['slug'] === 'wallet_adjustment') {
			continue;
		}

		$actual = (float) ($row[$type['slug']] ?? 0);
		$goal = 0.0;

		if (typeShouldRecomputeMonthlyDisplayGoal($pdo, $userId, $type, $statMonth)) {
			$goal = computeTypeMonthlyGoalAmount($pdo, $userId, $type, $statMonth, $monthEnd, false);
		}

		$insertTypeStat->execute([$userId, $statMonth, $type['slug'], $goal, $actual]);
	}
}

function getTypeBreakdownByMonth(PDO $pdo, int $userId, string $typeSlug): array {
	$rawSql = $pdo->prepare(
		"SELECT DATE_FORMAT(entry_date, '%Y-%m-01') AS stat_month,
			COALESCE(NULLIF(note, ''), 'Other') AS note,
			SUM(amount) AS amount
		FROM bank_entries
		WHERE account_id = ? AND type = ? AND amount > 0
		GROUP BY stat_month, note
		ORDER BY stat_month DESC, amount DESC"
	);
	$rawSql->execute([$userId, $typeSlug]);

	$breakdown = [];
	foreach ($rawSql->fetchAll() as $row) {
		$breakdown[$row['stat_month']][] = ['note' => $row['note'], 'amount' => (float) $row['amount']];
	}

	foreach ($breakdown as &$monthItems) {
		usort($monthItems, fn(array $a, array $b) => $b['amount'] <=> $a['amount']);
	}
	unset($monthItems);

	return $breakdown;
}

function getInvestmentBreakdownByMonth(PDO $pdo, int $userId): array {
	return getTypeBreakdownByMonth($pdo, $userId, 'investments');
}

function getTypeBreakdownByNote(PDO $pdo, int $userId, string $typeSlug, ?string $startDate, string $endDate): array {
	$sql = "SELECT COALESCE(NULLIF(note, ''), 'Other') AS note,
			SUM(amount) AS amount
		FROM bank_entries
		WHERE account_id = ? AND type = ? AND amount > 0";
	$params = [$userId, $typeSlug];

	if ($startDate !== null) {
		$sql .= ' AND entry_date >= ?';
		$params[] = $startDate;
	}

	$sql .= ' AND entry_date <= ? GROUP BY note ORDER BY amount DESC';
	$params[] = $endDate;

	$rawSql = $pdo->prepare($sql);
	$rawSql->execute($params);

	$breakdown = [];
	foreach ($rawSql->fetchAll() as $row) {
		$breakdown[] = ['note' => $row['note'], 'amount' => (float) $row['amount']];
	}

	return $breakdown;
}

function getTypeBreakdownAllTime(PDO $pdo, int $userId, string $typeSlug): array {
	return getTypeBreakdownByNote($pdo, $userId, $typeSlug, null, Clock::today());
}

/**
 * Stocks on the user's stock goal plan, so they can be picked in the stock dropdowns before any money is invested in them.
 *
 * @return array<int, string> stock notes whose latest goal is above zero
 */
function getPlannedStockNotes(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare(
		'SELECT goal.stock_note
		FROM stock_goals goal
		WHERE goal.account_id = ? AND goal.monthly_amount > 0
			AND goal.effective_from = (
				SELECT MAX(latest.effective_from)
				FROM stock_goals latest
				WHERE latest.account_id = goal.account_id
					AND latest.stock_note = goal.stock_note
					AND latest.effective_from <= ?
			)
		ORDER BY goal.stock_note ASC'
	);
	$rawSql->execute([$userId, Clock::today()]);

	return $rawSql->fetchAll(PDO::FETCH_COLUMN);
}

function getInvestmentBreakdownByNote(PDO $pdo, int $userId, ?string $startDate, string $endDate): array {
	return getTypeBreakdownByNote($pdo, $userId, 'investments', $startDate, $endDate);
}

function getInvestmentBreakdownAllTime(PDO $pdo, int $userId): array {
	return getInvestmentBreakdownByNote($pdo, $userId, null, Clock::today());
}

function investmentNoteTickerLabel(string $note): string {
	if (preg_match('/\(([^()]+)\)\s*$/', $note, $matches) === 1) {
		return trim($matches[1]);
	}

	return $note;
}

/**
 * @return array{name: string, ticker: string}
 */
function splitInvestmentNote(string $note): array {
	if (preg_match('/^(.*?)\s*(\([^()]+\))\s*$/su', $note, $matches) === 1 && trim($matches[1]) !== '') {
		return ['name' => trim($matches[1]), 'ticker' => $matches[2]];
	}

	return ['name' => $note, 'ticker' => ''];
}

/**
 * @return array<int, array{note: string, label: string}>
 */
function getInvestmentNotes(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare(
		"SELECT note, SUM(amount) AS total
		FROM bank_entries
		WHERE account_id = ? AND type = 'investments' AND amount > 0
			AND note IS NOT NULL AND note <> ''
		GROUP BY note
		ORDER BY total DESC, note ASC"
	);
	$rawSql->execute([$userId]);

	$notes = [];
	$knownKeys = [];
	foreach ($rawSql->fetchAll() as $row) {
		$knownKeys[] = mb_strtolower(trim($row['note']));
		$notes[] = [
			'note' => $row['note'],
			'label' => investmentNoteTickerLabel($row['note']),
		];
	}

	foreach (getPlannedStockNotes($pdo, $userId) as $plannedNote) {
		if (!in_array(mb_strtolower(trim($plannedNote)), $knownKeys, true)) {
			$notes[] = [
				'note' => $plannedNote,
				'label' => investmentNoteTickerLabel($plannedNote),
			];
		}
	}

	return $notes;
}

function formatSignedAmount(float $value): string {
	return ($value >= 0 ? '+' : '') . number_format($value, 2);
}

function bankEntryDisplayAmount(float $amount, array $type): float {
	if ($type['balance_mode'] === 'wallet_out') {
		return -$amount;
	}

	return $amount;
}

function formatBankEntryDisplayAmount(float $amount, array $type): string {
	return formatSignedAmount(bankEntryDisplayAmount($amount, $type));
}

function bankEntryDisplayAmountIsNegative(float $amount, array $type): bool {
	return bankEntryDisplayAmount($amount, $type) < 0;
}

function bankEntryDisplayAmountClass(float $amount, array $type): string {
	$displayAmount = bankEntryDisplayAmount($amount, $type);

	if ($displayAmount < 0) {
		return ' bank-entry-item__amount--negative';
	}

	if ($displayAmount > 0) {
		return ' bank-entry-item__amount--positive';
	}

	return '';
}

function buildNetWorthPotSelectSql(array $types): string {
	$parts = [];

	foreach ($types as $type) {
		if ($type['balance_mode'] !== 'pot') {
			continue;
		}

		$slug = $type['slug'];
		$parts[] = "COALESCE(SUM(CASE WHEN type = '{$slug}' THEN amount END), 0) AS `{$slug}`";
	}

	return implode(', ', $parts);
}

function getNetWorthAsOf(PDO $pdo, int $userId, string $asOfDate): array {
	$types = getBankTypes($pdo, $userId);
	$walletExpression = buildWalletBalanceExpression($types);
	$potSelect = buildNetWorthPotSelectSql($types);

	$sql = "SELECT
			COALESCE(SUM(CASE WHEN method = 'card' THEN ({$walletExpression}) ELSE 0 END), 0) AS card,
			COALESCE(SUM(CASE WHEN method = 'cash' THEN ({$walletExpression}) ELSE 0 END), 0) AS cash";

	if ($potSelect !== '') {
		$sql .= ", {$potSelect}";
	}

	$sql .= '
		FROM bank_entries
		WHERE account_id = ? AND entry_date <= ?';

	$rawSql = $pdo->prepare($sql);
	$rawSql->execute([$userId, $asOfDate]);
	$row = $rawSql->fetch();

	$result = [
		'card' => (float) $row['card'],
		'cash' => (float) $row['cash'],
		'pots' => [],
	];

	$potTotal = 0.0;
	foreach ($types as $type) {
		if ($type['balance_mode'] !== 'pot') {
			continue;
		}

		$value = (float) ($row[$type['slug']] ?? 0);
		$result['pots'][$type['slug']] = $value;
		$potTotal += $value;
	}

	$result['total'] = $result['card'] + $result['cash'] + $potTotal;

	return $result;
}

function saveNetWorthSnapshot(PDO $pdo, int $userId, string $snapshotDate): void {
	$netWorth = getNetWorthAsOf($pdo, $userId, $snapshotDate);

	$upsert = $pdo->prepare(
		'INSERT INTO net_worth_snapshots (account_id, snapshot_date, card, cash, savings, investments, pension, total)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE
			card = VALUES(card),
			cash = VALUES(cash),
			savings = VALUES(savings),
			investments = VALUES(investments),
			pension = VALUES(pension),
			total = VALUES(total)'
	);
	$upsert->execute([
		$userId,
		$snapshotDate,
		$netWorth['card'],
		$netWorth['cash'],
		$netWorth['pots']['savings'] ?? 0.0,
		$netWorth['pots']['investments'] ?? 0.0,
		$netWorth['pots']['pension'] ?? 0.0,
		$netWorth['total'],
	]);
}

function refreshNetWorthSnapshotIfCompleted(PDO $pdo, int $userId, string $entryDate): void {
	$monthEnd = date('Y-m-t', strtotime($entryDate));

	if ($monthEnd < Clock::today()) {
		saveNetWorthSnapshot($pdo, $userId, $monthEnd);
	}
}

function getNetWorthSnapshot(PDO $pdo, int $userId, string $snapshotDate): ?array {
	$rawSql = $pdo->prepare(
		'SELECT card, cash, savings, investments, pension, total
		FROM net_worth_snapshots
		WHERE account_id = ? AND snapshot_date = ?'
	);
	$rawSql->execute([$userId, $snapshotDate]);
	$row = $rawSql->fetch();

	if ($row === false) {
		return null;
	}

	$snapshot = array_map('floatval', $row);
	$snapshot['pots'] = [
		'savings' => $snapshot['savings'],
		'investments' => $snapshot['investments'],
		'pension' => $snapshot['pension'],
	];

	return $snapshot;
}

function ensureNetWorthSnapshots(PDO $pdo, int $userId): void {
	$lastCompletedMonthEnd = date('Y-m-d', strtotime('first day of this month - 1 day'));

	$firstEntrySql = $pdo->prepare('SELECT MIN(entry_date) FROM bank_entries WHERE account_id = ?');
	$firstEntrySql->execute([$userId]);
	$firstEntryDate = $firstEntrySql->fetchColumn();

	if (!$firstEntryDate || $firstEntryDate > $lastCompletedMonthEnd) {
		return;
	}

	$existingSql = $pdo->prepare('SELECT snapshot_date FROM net_worth_snapshots WHERE account_id = ?');
	$existingSql->execute([$userId]);
	$existingSnapshots = array_flip($existingSql->fetchAll(PDO::FETCH_COLUMN));

	$cursor = date('Y-m-t', strtotime($firstEntryDate));
	while ($cursor <= $lastCompletedMonthEnd) {
		if (!isset($existingSnapshots[$cursor])) {
			saveNetWorthSnapshot($pdo, $userId, $cursor);
		}

		$cursor = date('Y-m-t', strtotime($cursor . ' +1 day'));
	}
}

function getNetWorthHistory(PDO $pdo, int $userId): array {
	ensureNetWorthSnapshots($pdo, $userId);
	$types = getHistoryGrowthTypes(getHistoryCardTypes(getActiveBankTypes($pdo, $userId)));

	$rawSql = $pdo->prepare(
		'SELECT DISTINCT stat_month FROM monthly_stats WHERE account_id = ? ORDER BY stat_month ASC'
	);
	$rawSql->execute([$userId]);
	$statMonths = $rawSql->fetchAll(PDO::FETCH_COLUMN);

	$currentStatMonth = Clock::monthStart();
	if (!in_array($currentStatMonth, $statMonths, true)) {
		$statMonths[] = $currentStatMonth;
	}

	$today = Clock::today();
	$previous = null;
	$previousTypeActuals = [];
	$history = [];

	foreach ($statMonths as $statMonth) {
		$lastDayOfMonth = date('Y-m-t', strtotime($statMonth));
		$isCompletedMonth = $lastDayOfMonth < $today;
		$netWorth = $isCompletedMonth
			? getNetWorthSnapshot($pdo, $userId, $lastDayOfMonth) ?? getNetWorthAsOf($pdo, $userId, $lastDayOfMonth)
			: getNetWorthAsOf($pdo, $userId, $today);
		$asOfDate = $isCompletedMonth ? $lastDayOfMonth : $today;
		$typeStats = getMonthlyStatByType($pdo, $userId, $statMonth);

		$previousForGrowth = $previous ?? ['total' => 0.0, 'card' => 0.0, 'cash' => 0.0, 'pots' => []];
		$growth = [
			'total' => $netWorth['total'] - $previousForGrowth['total'],
			'total_pct' => $previousForGrowth['total'] != 0.0
				? ($netWorth['total'] - $previousForGrowth['total']) / abs($previousForGrowth['total']) * 100
				: ($netWorth['total'] != 0.0 ? 100.0 : null),
			'card' => $netWorth['card'] - $previousForGrowth['card'],
			'cash' => $netWorth['cash'] - $previousForGrowth['cash'],
			'types' => [],
		];

		foreach ($types as $type) {
			$slug = $type['slug'];

			if ($type['balance_mode'] === 'pot') {
				$current = $netWorth['pots'][$slug] ?? 0.0;
				$prev = $previousForGrowth['pots'][$slug] ?? 0.0;
				$growth['types'][$slug] = $current - $prev;

				continue;
			}

			$currentActual = (float) ($typeStats[$slug]['actual'] ?? 0);
			$prevActual = (float) ($previousTypeActuals[$slug] ?? 0);
			$growth['types'][$slug] = $currentActual - $prevActual;
		}

		$history[$statMonth] = [
			'net_worth' => $netWorth,
			'growth' => $growth,
			'as_of_date' => $asOfDate,
		];
		$previous = $netWorth;

		foreach ($types as $type) {
			if ($type['balance_mode'] === 'pot') {
				continue;
			}

			$slug = $type['slug'];
			$previousTypeActuals[$slug] = (float) ($typeStats[$slug]['actual'] ?? 0);
		}
	}

	return $history;
}

function computeGoalTargets(PDO $pdo, int $userId, array $types, string $periodStart, string $periodEnd): array {
	$targets = [];

	foreach ($types as $type) {
		if (!$type['is_active'] || $type['income_percent'] === null) {
			continue;
		}

		$targets[$type['slug']] = computeTypeGoalAmount($pdo, $userId, $type, $periodStart, $periodEnd);
	}

	return $targets;
}

function getGoalTrackingTypes(array $types): array {
	return array_values(array_filter(
		$types,
		fn(array $type) => $type['is_active'] && $type['income_percent'] !== null
	));
}

function getHistoryGoalTrackingTypes(array $types): array {
	return array_values(array_filter(
		$types,
		fn(array $type) => $type['income_percent'] !== null
	));
}

function getGoalTrackingDisplayConfig(array $type): array {
	$slug = $type['slug'];
	$labelLower = strtolower($type['label']);

	if ($slug === 'expenses') {
		return [
			'should_label' => 'Should spend',
			'actual_label' => 'Spent',
			'check_mode' => 'under_budget',
		];
	}

	if ($type['balance_mode'] === 'pot') {
		return match ($slug) {
			'savings' => [
				'should_label' => 'Should save',
				'actual_label' => 'Saved',
				'check_mode' => 'at_least',
			],
			'investments' => [
				'should_label' => 'Should invest',
				'actual_label' => 'Invested',
				'check_mode' => 'at_least',
			],
			default => [
				'should_label' => 'Should ' . $labelLower,
				'actual_label' => $type['label'],
				'check_mode' => 'at_least',
			],
		};
	}

	if ($type['balance_mode'] === 'wallet_out') {
		return [
			'should_label' => 'Pay ' . $labelLower,
			'actual_label' => 'Paid',
			'check_mode' => 'at_least',
		];
	}

	return [
		'should_label' => $type['label'],
		'actual_label' => $type['label'],
		'check_mode' => 'at_least',
	];
}

function isGoalTrackingMet(float $goal, float $actual, string $checkMode): bool {
	if ($checkMode === 'under_budget') {
		return $actual <= $goal;
	}

	return $actual >= $goal;
}

function getGoalTrackingCheckState(float $goal, float $actual, array $type): ?string {
	if ($goal <= 0) {
		return null;
	}

	$config = getGoalTrackingDisplayConfig($type);

	return isGoalTrackingMet($goal, $actual, $config['check_mode']) ? 'met' : 'missed';
}

function getGoalTrackingNeedMoreVerb(array $type): string {
	$config = getGoalTrackingDisplayConfig($type);
	$shouldLabel = $config['should_label'];

	if (str_starts_with($shouldLabel, 'Should ')) {
		return strtolower(substr($shouldLabel, 7));
	}

	if (str_starts_with($shouldLabel, 'Pay ')) {
		return 'pay';
	}

	return strtolower($type['label']);
}

function getGoalTrackingNeedMoreAmount(float $goal, float $actual, array $type): ?float {
	if ($goal <= 0) {
		return null;
	}

	$config = getGoalTrackingDisplayConfig($type);

	if ($config['check_mode'] !== 'at_least' || $actual >= $goal) {
		return null;
	}

	return $goal - $actual;
}

function getGoalTrackingCheckAriaLabel(array $type, string $state): string {
	if ($state === 'met') {
		return $type['slug'] === 'expenses' ? 'On track' : 'Goal met';
	}

	return $type['slug'] === 'expenses' ? 'Over budget' : 'Under goal';
}

function getDayGoalActuals(PDO $pdo, int $userId, string $date, array $goalTypes): array {
	$actuals = [];

	foreach ($goalTypes as $type) {
		$slug = $type['slug'];
		$statement = $pdo->prepare(
			'SELECT COALESCE(SUM(amount), 0)
			FROM bank_entries
			WHERE account_id = ? AND type = ? AND entry_date = ?'
		);
		$statement->execute([$userId, $slug, $date]);
		$actuals[$slug] = (float) $statement->fetchColumn();
	}

	return $actuals;
}

function computeDailyGoalShortfalls(PDO $pdo, int $userId, string $date, array $goalTypes): array {
	$income = sumIncomeBetween($pdo, $userId, $date, $date);

	if ($income <= 0) {
		return [];
	}

	$targets = computeGoalTargets($pdo, $userId, $goalTypes, $date, $date);
	$actuals = getDayGoalActuals($pdo, $userId, $date, $goalTypes);
	$shortfalls = [];

	foreach ($goalTypes as $type) {
		if ($type['slug'] === 'expenses') {
			continue;
		}

		$slug = $type['slug'];
		$goal = (float) ($targets[$slug] ?? 0);
		$actual = (float) ($actuals[$slug] ?? 0);
		$config = getGoalTrackingDisplayConfig($type);

		if ($config['check_mode'] !== 'at_least' || $goal <= 0) {
			continue;
		}

		if (!isGoalTrackingMet($goal, $actual, $config['check_mode'])) {
			$shortfalls[$slug] = round(max(0, $goal - $actual), 2);
		}
	}

	return $shortfalls;
}

function computeMonthlyGoalGapForMissing(float $goal, float $actual, array $type): float {
	$config = getGoalTrackingDisplayConfig($type);

	if ($config['check_mode'] !== 'at_least') {
		return 0.0;
	}

	return round($goal - $actual, 2);
}

/**
 * @return array<string, float> signed catch-up balance per type (positive = still missing)
 */
function computeOverallGoalCatchUpBalancesByType(PDO $pdo, int $userId): array {
	return BankRequestCache::remember(
		'overall_goal_catch_up:' . $userId,
		fn(): array => buildOverallGoalCatchUpBalancesByType($pdo, $userId)
	);
}

/**
 * @return array<string, float>
 */
function buildOverallGoalCatchUpBalancesByType(PDO $pdo, int $userId): array {
	$goalTypes = array_values(array_filter(
		getGoalTrackingTypes(getBankTypes($pdo, $userId)),
		static fn(array $type) => $type['slug'] !== 'expenses'
			&& getGoalTrackingDisplayConfig($type)['check_mode'] === 'at_least'
	));

	$history = getMonthlyStatsHistory($pdo, $userId);
	usort(
		$history,
		static fn(array $a, array $b) => strcmp($a['stat_month'], $b['stat_month'])
	);

	$balanceByType = [];

	foreach ($goalTypes as $type) {
		$slug = $type['slug'];
		$balance = 0.0;

		foreach ($history as $monthRow) {
			$statMonth = $monthRow['stat_month'];

			if (!typeAppliesToHistoryMonth($pdo, $userId, $type, $statMonth)) {
				continue;
			}

			$stats = $monthRow['type_stats'][$slug] ?? ['goal' => 0.0, 'actual' => 0.0];
			$balance += computeMonthlyGoalGapForMissing(
				(float) $stats['goal'],
				(float) $stats['actual'],
				$type
			);
		}

		$balanceByType[$slug] = round($balance, 2);
	}

	return $balanceByType;
}

function computeOverallMissingGoals(PDO $pdo, int $userId): array {
	$remainingByType = [];

	foreach (computeOverallGoalCatchUpBalancesByType($pdo, $userId) as $slug => $balance) {
		$remaining = max(0, $balance);

		if ($remaining > 0) {
			$remainingByType[$slug] = $remaining;
		}
	}

	return $remainingByType;
}

function computeOverallSurplusGoals(PDO $pdo, int $userId): array {
	$surplusByType = [];

	foreach (computeOverallGoalCatchUpBalancesByType($pdo, $userId) as $slug => $balance) {
		$surplus = max(0, round(-$balance, 2));

		if ($surplus > 0) {
			$surplusByType[$slug] = $surplus;
		}
	}

	return $surplusByType;
}
