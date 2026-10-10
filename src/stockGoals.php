<?php

require_once __DIR__ . '/Clock.php';
require_once __DIR__ . '/bank.php';

const STOCK_GOAL_MAX_AMOUNT = 1000000.0;
const STOCK_GOAL_NOTE_MAX_LENGTH = 150;

const STOCK_RING_GAP_DEGREES = 3.0;
const STOCK_RING_RADIUS = 80;
const STOCK_RING_POP_DISTANCE = 6;

const STOCK_GOAL_COLORS = ['#22c55e', '#a78bfa', '#60a5fa', '#fb923c', '#f472b6', '#facc15', '#2dd4bf', '#f87171'];

function stockGoalKey(string $note): string {
	return mb_strtolower(trim($note));
}

function normalizeStockGoalMonth(?string $month): ?string {
	if ($month === null || preg_match('/^\d{4}-\d{2}(-01)?$/', $month) !== 1) {
		return null;
	}

	$firstOfMonth = substr($month, 0, 7) . '-01';

	return isValidIsoDate($firstOfMonth) ? $firstOfMonth : null;
}

function getStockGoalPercent(float $goal, float $invested): ?float {
	if ($goal <= 0) {
		return null;
	}

	return round($invested / $goal * 100, 1);
}

function getStockGoalStatus(float $goal, float $invested): string {
	if ($goal <= 0) {
		return 'none';
	}

	return $invested >= $goal ? 'met' : 'partial';
}

/**
 * @return array<string, array<string, float>> invested additions per month then stock key
 */
function getStockInvestedByMonth(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare(
		"SELECT DATE_FORMAT(entry_date, '%Y-%m-01') AS stat_month, note, SUM(amount) AS amount
		FROM bank_entries
		WHERE account_id = ? AND type = 'investments' AND amount > 0
			AND note IS NOT NULL AND note <> ''
		GROUP BY stat_month, note"
	);
	$rawSql->execute([$userId]);

	$invested = [];
	foreach ($rawSql->fetchAll() as $row) {
		$key = stockGoalKey($row['note']);
		$invested[$row['stat_month']][$key] = ($invested[$row['stat_month']][$key] ?? 0.0) + (float) $row['amount'];
	}

	return $invested;
}

/**
 * @return array<string, array<string, array<int, array{date: string, amount: float}>>> entries per month then stock key, oldest first
 */
function getStockEntriesByMonth(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare(
		"SELECT DATE_FORMAT(entry_date, '%Y-%m-01') AS stat_month, DATE_FORMAT(entry_date, '%Y-%m-%d') AS entry_day, note, amount
		FROM bank_entries
		WHERE account_id = ? AND type = 'investments' AND amount > 0
			AND note IS NOT NULL AND note <> ''
		ORDER BY entry_date ASC, id ASC"
	);
	$rawSql->execute([$userId]);

	$entries = [];
	foreach ($rawSql->fetchAll() as $row) {
		$entries[$row['stat_month']][stockGoalKey($row['note'])][] = [
			'date' => $row['entry_day'],
			'amount' => round((float) $row['amount'], 2),
		];
	}

	return $entries;
}

/**
 * @return array<string, string> stock key to the note spelling used on the most recent entry
 */
function getStockNoteSpellings(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare(
		"SELECT note
		FROM bank_entries
		WHERE account_id = ? AND type = 'investments' AND amount > 0
			AND note IS NOT NULL AND note <> ''
		ORDER BY entry_date ASC, id ASC"
	);
	$rawSql->execute([$userId]);

	$spellings = [];
	foreach ($rawSql->fetchAll(PDO::FETCH_COLUMN) as $note) {
		$spellings[stockGoalKey($note)] = $note;
	}

	return $spellings;
}

/**
 * @return array<string, array{note: string, history: array<int, array{effective_from: string, amount: float}>}>
 */
function getStockGoalHistory(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare(
		'SELECT stock_note, monthly_amount, effective_from
		FROM stock_goals
		WHERE account_id = ?
		ORDER BY effective_from ASC, id ASC'
	);
	$rawSql->execute([$userId]);

	$goals = [];
	foreach ($rawSql->fetchAll() as $row) {
		$key = stockGoalKey($row['stock_note']);
		$goals[$key]['note'] ??= $row['stock_note'];
		$goals[$key]['history'][] = [
			'effective_from' => $row['effective_from'],
			'amount' => (float) $row['monthly_amount'],
		];
	}

	return $goals;
}

/**
 * Colours follow the order stocks first received a goal, so a stock keeps its colour when others are added later.
 *
 * @param array<string, array{note: string, history: array<int, array{effective_from: string, amount: float}>}> $goals
 * @return array<string, string> stock key to hex colour
 */
function getStockGoalColorMap(array $goals): array {
	$firstGoalMonths = [];
	foreach ($goals as $key => $stock) {
		foreach ($stock['history'] as $segment) {
			if ($segment['amount'] > 0) {
				$firstGoalMonths[$key] = $segment['effective_from'];
				break;
			}
		}
	}

	uksort($firstGoalMonths, fn(string $a, string $b) => [$firstGoalMonths[$a], $a] <=> [$firstGoalMonths[$b], $b]);

	$colors = [];
	$position = 0;
	foreach (array_keys($firstGoalMonths) as $key) {
		$colors[$key] = STOCK_GOAL_COLORS[$position % count(STOCK_GOAL_COLORS)];
		$position++;
	}

	return $colors;
}

function isValidStockColor(string $color): bool {
	return preg_match('/^#[0-9a-f]{6}$/', $color) === 1;
}

/**
 * @return array<string, string> stock key to the hex colour the user picked or was assigned
 */
function getStoredStockColors(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare('SELECT stock_key, color FROM stock_goal_colors WHERE account_id = ?');
	$rawSql->execute([$userId]);

	$colors = [];
	foreach ($rawSql->fetchAll() as $row) {
		$colors[(string) $row['stock_key']] = $row['color'];
	}

	return $colors;
}

/**
 * A stored colour wins over the position-based default.
 *
 * @param array<string, array{note: string, history: array<int, array{effective_from: string, amount: float}>}> $goals
 * @return array<string, string> stock key to hex colour
 */
function getStockColors(PDO $pdo, int $userId, array $goals): array {
	return getStoredStockColors($pdo, $userId) + getStockGoalColorMap($goals);
}

/**
 * Picks an unused palette colour at random, or a random hue once the palette is used up.
 *
 * @param array<int, string> $usedColors
 */
function pickRandomStockColor(array $usedColors): string {
	$used = array_map('strtolower', $usedColors);
	$free = array_values(array_diff(STOCK_GOAL_COLORS, $used));

	if ($free !== []) {
		return $free[random_int(0, count($free) - 1)];
	}

	do {
		$hue = random_int(0, 359);
		$color = hslToHexColor($hue, 0.7, 0.65);
	} while (in_array($color, $used, true));

	return $color;
}

function hslToHexColor(int $hue, float $saturation, float $lightness): string {
	$chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
	$x = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
	$offset = $lightness - $chroma / 2;

	[$red, $green, $blue] = match (intdiv($hue, 60)) {
		0 => [$chroma, $x, 0],
		1 => [$x, $chroma, 0],
		2 => [0, $chroma, $x],
		3 => [0, $x, $chroma],
		4 => [$x, 0, $chroma],
		default => [$chroma, 0, $x],
	};

	return sprintf(
		'#%02x%02x%02x',
		(int) round(($red + $offset) * 255),
		(int) round(($green + $offset) * 255),
		(int) round(($blue + $offset) * 255)
	);
}

function storeStockColor(PDO $pdo, int $userId, string $key, string $color, bool $overwrite): void {
	$rawSql = $pdo->prepare(
		$overwrite
			? 'INSERT INTO stock_goal_colors (account_id, stock_key, color) VALUES (?, ?, ?)
				ON DUPLICATE KEY UPDATE color = VALUES(color)'
			: 'INSERT IGNORE INTO stock_goal_colors (account_id, stock_key, color) VALUES (?, ?, ?)'
	);
	$rawSql->execute([$userId, $key, $color]);
}

/**
 * Gives a stock that is getting its first goal a random colour no other stock uses. The colours the existing
 * stocks show right now are stored first so they cannot shift when the new stock joins.
 */
function assignColorToNewStock(PDO $pdo, int $userId, string $note): void {
	$key = stockGoalKey($note);
	$colors = getStockColors($pdo, $userId, getStockGoalHistory($pdo, $userId));

	foreach ($colors as $existingKey => $color) {
		storeStockColor($pdo, $userId, (string) $existingKey, $color, false);
	}

	if (!isset($colors[$key])) {
		storeStockColor($pdo, $userId, $key, pickRandomStockColor(array_values($colors)), false);
	}
}

/**
 * @return true|string true on success, otherwise an error message
 */
function setStockColor(PDO $pdo, int $userId, string $note, string $color): bool|string {
	$key = stockGoalKey($note);
	$color = strtolower(trim($color));

	if (!isValidStockColor($color)) {
		return 'Choose a valid colour.';
	}

	if (!isset(getStockNoteSpellings($pdo, $userId)[$key]) && !isset(getStockGoalHistory($pdo, $userId)[$key])) {
		return 'Stock not found.';
	}

	storeStockColor($pdo, $userId, $key, $color, true);

	return true;
}

/**
 * @param array<int, array{effective_from: string, amount: float}> $history
 */
function getStockGoalAmountForMonth(array $history, string $month): float {
	$amount = 0.0;

	foreach ($history as $segment) {
		if ($segment['effective_from'] > $month) {
			break;
		}

		$amount = $segment['amount'];
	}

	return $amount;
}

function getStockGoalMonthBounds(PDO $pdo, int $userId): array {
	$current = Clock::monthStart();

	$entrySql = $pdo->prepare(
		"SELECT MIN(DATE_FORMAT(entry_date, '%Y-%m-01'))
		FROM bank_entries
		WHERE account_id = ? AND type = 'investments' AND amount > 0 AND note IS NOT NULL AND note <> ''"
	);
	$entrySql->execute([$userId]);
	$firstEntryMonth = $entrySql->fetchColumn();

	$goalSql = $pdo->prepare('SELECT MIN(effective_from) FROM stock_goals WHERE account_id = ? AND monthly_amount > 0');
	$goalSql->execute([$userId]);
	$firstGoalMonth = $goalSql->fetchColumn();

	$candidates = array_filter([$firstEntryMonth ?: null, $firstGoalMonth ?: null, $current]);

	return ['earliest' => min($candidates), 'latest' => $current];
}

function clampStockGoalMonth(?string $requestedMonth, array $bounds): string {
	$month = normalizeStockGoalMonth($requestedMonth) ?? $bounds['latest'];

	if ($month > $bounds['latest']) {
		return $bounds['latest'];
	}

	if ($month < $bounds['earliest']) {
		return $bounds['earliest'];
	}

	return $month;
}

/**
 * @return array<int, string>
 */
function getStockGoalMonthList(array $bounds): array {
	$months = [];
	for ($month = $bounds['earliest']; $month <= $bounds['latest']; $month = shiftStockGoalMonth($month, 1)) {
		$months[] = $month;
	}

	return $months;
}

/**
 * Centres the selected month in the window and slides it to the edge when the bounds are reached.
 *
 * @return array<int, string>
 */
function getStockGoalMonthWindow(string $selectedMonth, array $bounds, int $size = 3): array {
	$start = shiftStockGoalMonth($selectedMonth, -intdiv($size, 2));
	$lastStart = shiftStockGoalMonth($bounds['latest'], -($size - 1));

	if ($start > $lastStart) {
		$start = $lastStart;
	}

	if ($start < $bounds['earliest']) {
		$start = $bounds['earliest'];
	}

	$months = [];
	for ($month = $start; $month <= $bounds['latest'] && count($months) < $size; $month = shiftStockGoalMonth($month, 1)) {
		$months[] = $month;
	}

	return $months;
}

function shiftStockGoalMonth(string $month, int $months): string {
	return date('Y-m-01', strtotime($month . ' ' . ($months >= 0 ? '+' : '') . $months . ' months'));
}

/**
 * Walks the closed months before $month and keeps a running balance per stock: a shortfall is queued and
 * paid off oldest first by later extra money, and unused extra money becomes a credit that covers later
 * shortfalls. Credit never lowers the viewed month's own goal.
 * A month whose goal is 0 clears any carry because the user dropped the goal.
 *
 * @param array<int, array{effective_from: string, amount: float}> $history
 * @param array<string, float> $investedByMonth invested additions keyed by month start
 * @return array{missed: float, credit: float, missed_months: array<int, array{month: string, amount: float}>}
 */
function calculateStockGoalCarryOver(array $history, array $investedByMonth, string $month): array {
	$settlement = settleStockGoalMonths($history, $investedByMonth, shiftStockGoalMonth($month, -1), false);

	return [
		'missed' => round(array_sum(array_column($settlement['queue'], 'amount')), 2),
		'credit' => round(array_sum(array_column($settlement['credit'], 'amount')), 2),
		'missed_months' => $settlement['queue'],
	];
}

/**
 * Walks every month from the first goal up to $lastMonth and settles shortfalls: extra money above a
 * month's own goal pays the oldest open shortfalls first, and what is left over becomes a credit that
 * covers later shortfalls. An open last month can only pay; it records no shortfall until it has closed.
 * Every payment is recorded on both months so each card can say who covered what.
 *
 * @param array<int, array{effective_from: string, amount: float}> $history
 * @param array<string, float> $investedByMonth invested additions keyed by month start
 * @return array{
 *     queue: array<int, array{month: string, amount: float}>,
 *     credit: array<int, array{month: string, amount: float}>,
 *     months: array<string, array{shortfall: float, covered_by: array<int, array{month: string, amount: float, extra: bool}>, covers: array<int, array{month: string, amount: float}>}>
 * }
 */
function settleStockGoalMonths(array $history, array $investedByMonth, string $lastMonth, bool $lastMonthIsOpen): array {
	$firstGoalMonth = null;
	foreach ($history as $segment) {
		if ($segment['amount'] > 0) {
			$firstGoalMonth = $segment['effective_from'];
			break;
		}
	}

	$queue = [];
	$credit = [];
	$months = [];
	$blank = ['goal' => 0.0, 'shortfall' => 0.0, 'surplus' => 0.0, 'open_before' => 0.0, 'covered_by' => [], 'covers' => []];
	$record = function (string $coveredMonth, string $payerMonth, float $amount, bool $isExtra) use (&$months, $blank): void {
		$months[$coveredMonth] ??= $blank;
		$months[$payerMonth] ??= $blank;
		$months[$coveredMonth]['covered_by'][] = ['month' => $payerMonth, 'amount' => $amount, 'extra' => $isExtra];
		$months[$payerMonth]['covers'][] = ['month' => $coveredMonth, 'amount' => $amount];
	};

	if ($firstGoalMonth !== null) {
		for ($month = $firstGoalMonth; $month <= $lastMonth; $month = shiftStockGoalMonth($month, 1)) {
			$goal = getStockGoalAmountForMonth($history, $month);

			if ($goal <= 0) {
				$queue = [];
				$credit = [];
				continue;
			}

			$net = round(($investedByMonth[$month] ?? 0.0) - $goal, 2);
			$months[$month] ??= $blank;
			$months[$month]['goal'] = $goal;

			if ($net >= 0) {
				$months[$month]['surplus'] = $net;
				$months[$month]['open_before'] = round(array_sum(array_column($queue, 'amount')), 2);

				foreach ($queue as $index => $shortfall) {
					$payment = round(min($net, $shortfall['amount']), 2);

					if ($payment <= 0) {
						continue;
					}

					$queue[$index]['amount'] = round($shortfall['amount'] - $payment, 2);
					$net = round($net - $payment, 2);
					$record($shortfall['month'], $month, $payment, false);
				}

				$queue = array_values(array_filter($queue, fn(array $shortfall) => $shortfall['amount'] > 0));

				if ($net > 0) {
					$credit[] = ['month' => $month, 'amount' => $net];
				}

				continue;
			}

			if ($lastMonthIsOpen && $month === $lastMonth) {
				continue;
			}

			$shortfall = round(-$net, 2);
			$months[$month]['shortfall'] = $shortfall;

			foreach ($credit as $index => $extra) {
				$used = round(min($extra['amount'], $shortfall), 2);

				if ($used <= 0) {
					continue;
				}

				$credit[$index]['amount'] = round($extra['amount'] - $used, 2);
				$shortfall = round($shortfall - $used, 2);
				$record($month, $extra['month'], $used, true);
			}

			$credit = array_values(array_filter($credit, fn(array $extra) => $extra['amount'] > 0));

			if ($shortfall > 0) {
				$queue[] = ['month' => $month, 'amount' => $shortfall];
			}
		}
	}

	return ['queue' => $queue, 'credit' => $credit, 'months' => $months];
}

/**
 * @param array<string, mixed> $settlement result of settleStockGoalMonths()
 * @return array{
 *     shortfall: float, covered: float, still_missing: float, surplus: float, open_before: float,
 *     covered_by: array<int, array{month: string, amount: float, extra: bool}>,
 *     covers: array<int, array{month: string, amount: float, goal: float, shortfall: float}>, covers_total: float
 * }
 */
function getStockGoalSettlementForMonth(array $settlement, string $month): array {
	$entry = $settlement['months'][$month] ?? ['shortfall' => 0.0, 'surplus' => 0.0, 'open_before' => 0.0, 'covered_by' => [], 'covers' => []];
	$stillMissing = 0.0;

	foreach ($settlement['queue'] as $open) {
		if ($open['month'] === $month) {
			$stillMissing = $open['amount'];
		}
	}

	$covers = array_map(fn(array $covered) => $covered + [
		'goal' => $settlement['months'][$covered['month']]['goal'] ?? 0.0,
		'shortfall' => $settlement['months'][$covered['month']]['shortfall'] ?? 0.0,
	], $entry['covers']);

	return [
		'shortfall' => $entry['shortfall'],
		'surplus' => $entry['surplus'],
		'open_before' => $entry['open_before'],
		'covered' => round(array_sum(array_column($entry['covered_by'], 'amount')), 2),
		'still_missing' => $stillMissing,
		'covered_by' => $entry['covered_by'],
		'covers' => $covers,
		'covers_total' => round(array_sum(array_column($entry['covers'], 'amount')), 2),
	];
}

/**
 * @return array<int, array{
 *     key: string, note: string, name: string, ticker: string, color: string|null,
 *     goal: float, target: float, carry_missed: float, carry_months: array, catch_up_left: float, settlement: array,
 *     invested: float, left: float, over: float,
 *     percent: float|null, status: string, has_goal: bool
 * }>
 */
function getStockGoalRows(PDO $pdo, int $userId, string $month): array {
	$investedByMonth = getStockInvestedByMonth($pdo, $userId);
	$spellings = getStockNoteSpellings($pdo, $userId);
	$goals = getStockGoalHistory($pdo, $userId);
	$colors = getStockColors($pdo, $userId, $goals);
	$isCurrentMonth = $month === Clock::monthStart();

	$keys = array_unique(array_merge(array_keys($spellings), array_keys($goals)));
	$rows = [];

	foreach ($keys as $key) {
		$history = $goals[$key]['history'] ?? [];
		$baseGoal = getStockGoalAmountForMonth($history, $month);
		$invested = round($investedByMonth[$month][$key] ?? 0.0, 2);

		if (!$isCurrentMonth && $baseGoal <= 0 && $invested <= 0) {
			continue;
		}

		$hasGoal = $baseGoal > 0;
		$stockInvestedByMonth = array_map(fn(array $months) => $months[$key] ?? 0.0, $investedByMonth);
		$carry = $hasGoal
			? calculateStockGoalCarryOver($history, $stockInvestedByMonth, $month)
			: ['missed' => 0.0, 'credit' => 0.0, 'missed_months' => []];
		$settlement = getStockGoalSettlementForMonth(
			$hasGoal ? settleStockGoalMonths($history, $stockInvestedByMonth, Clock::monthStart(), true) : ['queue' => [], 'months' => []],
			$month
		);
		$target = $hasGoal ? round($baseGoal + $carry['missed'], 2) : 0.0;
		$catchUpLeft = $hasGoal ? round(min($carry['missed'], max($target - $invested, 0)), 2) : 0.0;

		$note = $spellings[$key] ?? $goals[$key]['note'];
		$parts = splitInvestmentNote($note);

		$rows[] = [
			'key' => $key,
			'note' => $note,
			'name' => $parts['name'],
			'ticker' => $parts['ticker'],
			'color' => $colors[$key] ?? null,
			'goal' => $baseGoal,
			'target' => $target,
			'carry_missed' => $carry['missed'],
			'carry_months' => $carry['missed_months'],
			'catch_up_left' => $catchUpLeft,
			'settlement' => $settlement,
			'invested' => $invested,
			'left' => round(max($baseGoal - $invested, 0), 2),
			'over' => $hasGoal ? round(max($invested - $baseGoal, 0), 2) : 0.0,
			'percent' => $hasGoal ? round($invested / $baseGoal * 100, 1) : null,
			'status' => $hasGoal ? ($invested >= $baseGoal ? 'met' : 'partial') : 'none',
			'has_goal' => $hasGoal,
		];
	}

	usort($rows, function (array $a, array $b): int {
		if ($a['has_goal'] !== $b['has_goal']) {
			return $a['has_goal'] ? -1 : 1;
		}

		if ($a['has_goal']) {
			return [$b['invested'], $b['goal'], $a['name']] <=> [$a['invested'], $a['goal'], $b['name']];
		}

		return [$b['invested'], $a['name']] <=> [$a['invested'], $b['name']];
	});

	return $rows;
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function summarizeStockGoalRows(array $rows): array {
	$goalRows = array_values(array_filter($rows, fn(array $row) => $row['has_goal']));
	$planned = round(array_sum(array_column($goalRows, 'goal')), 2);
	$invested = round(array_sum(array_column($goalRows, 'invested')), 2);

	$behind = [];
	foreach ($goalRows as $row) {
		if ($row['catch_up_left'] > 0) {
			$behind[] = [
				'name' => $row['name'],
				'ticker' => $row['ticker'],
				'amount' => $row['catch_up_left'],
				'aim' => $row['target'],
			];
		}
	}

	return [
		'planned' => $planned,
		'invested' => $invested,
		'catch_up_left' => round(array_sum(array_column($behind, 'amount')), 2),
		'behind' => $behind,
		'left' => round(array_sum(array_column($goalRows, 'left')), 2),
		'over' => round(array_sum(array_column($goalRows, 'over')), 2),
		'percent' => getStockGoalPercent($planned, $invested),
		'goal_count' => count($goalRows),
		'met_count' => count(array_filter($goalRows, fn(array $row) => $row['status'] === 'met')),
	];
}

/**
 * The whole ring stands for the planned total, so each stock gets a sector as wide as the share of the
 * planned total it has invested; the rest of the ring stays empty. Angles run clockwise from the top.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function getStockRingSectors(array $rows): array {
	$goalRows = array_values(array_filter($rows, fn(array $row) => $row['has_goal'] && $row['goal'] > 0));
	$planned = array_sum(array_column($goalRows, 'goal'));
	$invested = array_sum(array_column($goalRows, 'invested'));

	if ($planned <= 0 || $invested <= 0) {
		return [];
	}

	$scale = min(1.0, $planned / $invested);
	$angle = 0.0;
	$sectors = [];

	foreach ($goalRows as $row) {
		if ($row['invested'] <= 0) {
			continue;
		}

		$fullSpan = $row['invested'] * $scale / $planned * 360;
		$isWholeRing = $fullSpan >= 359.9;
		$trim = $isWholeRing ? 0.0 : min(STOCK_RING_GAP_DEGREES / 2, $fullSpan * 0.25);
		$start = $angle + $trim;
		$end = min($angle + $fullSpan - $trim, 359.99);

		$sectors[] = [
			'key' => $row['key'],
			'name' => $row['name'],
			'ticker' => $row['ticker'],
			'color' => $row['color'] ?? '#22c55e',
			'goal' => $row['goal'],
			'invested' => $row['invested'],
			'percent' => round($row['invested'] / $row['goal'] * 100, 1),
			'contribution' => round($row['invested'] / $planned * 100, 1),
			'overall_percent' => round($invested / $planned * 100, 1),
			'start' => $start,
			'end' => $end,
			'middle' => ($start + $end) / 2,
		];

		$angle += $fullSpan;
	}

	return $sectors;
}

/**
 * SVG path for an arc on a circle, with angles in degrees clockwise from the top.
 */
function getStockRingArcPath(float $centerX, float $centerY, float $radius, float $startDegrees, float $endDegrees): string {
	$point = fn(float $degrees): array => [
		round($centerX + $radius * sin(deg2rad($degrees)), 3),
		round($centerY - $radius * cos(deg2rad($degrees)), 3),
	];

	[$startX, $startY] = $point($startDegrees);
	[$endX, $endY] = $point($endDegrees);
	$largeArc = $endDegrees - $startDegrees > 180 ? 1 : 0;

	return sprintf('M %s %s A %s %s 0 %d 1 %s %s', $startX, $startY, $radius, $radius, $largeArc, $endX, $endY);
}

/**
 * @return array{months: array<int, string>, series: array<int, array<string, mixed>>}
 */
function getStockGoalChartSeries(PDO $pdo, int $userId): array {
	$goals = getStockGoalHistory($pdo, $userId);
	$investedByMonth = getStockInvestedByMonth($pdo, $userId);
	$entriesByMonth = getStockEntriesByMonth($pdo, $userId);
	$spellings = getStockNoteSpellings($pdo, $userId);
	$current = Clock::monthStart();
	$colors = getStockColors($pdo, $userId, $goals);

	$firstGoalMonths = [];
	foreach ($goals as $key => $stock) {
		foreach ($stock['history'] as $segment) {
			if ($segment['amount'] > 0) {
				$firstGoalMonths[$key] = $segment['effective_from'];
				break;
			}
		}
	}

	if ($firstGoalMonths === []) {
		return ['months' => [], 'series' => []];
	}

	$months = [];
	for ($month = min($firstGoalMonths); $month <= $current; $month = shiftStockGoalMonth($month, 1)) {
		$months[] = $month;
	}

	$series = [];
	foreach ($firstGoalMonths as $key => $firstGoalMonth) {
		$note = $spellings[$key] ?? $goals[$key]['note'];
		$parts = splitInvestmentNote($note);
		$points = [];

		foreach ($months as $month) {
			$goal = $month >= $firstGoalMonth ? getStockGoalAmountForMonth($goals[$key]['history'], $month) : 0.0;
			$invested = round($investedByMonth[$month][$key] ?? 0.0, 2);

			$points[] = [
				'month' => $month,
				'goal' => $goal,
				'invested' => $invested,
				'percent' => getStockGoalPercent($goal, $invested),
				'entries' => $entriesByMonth[$month][$key] ?? [],
			];
		}

		$positiveGoals = array_filter(array_column($points, 'goal'), fn(float $goal) => $goal > 0);

		$series[] = [
			'note' => $note,
			'name' => $parts['name'],
			'ticker' => $parts['ticker'],
			'color' => $colors[$key],
			'points' => $points,
			'current_goal' => getStockGoalAmountForMonth($goals[$key]['history'], $current),
			'last_goal' => $positiveGoals === [] ? 0.0 : end($positiveGoals),
		];
	}

	usort($series, fn(array $a, array $b) => [$b['current_goal'], $b['last_goal'], $a['name']] <=> [$a['current_goal'], $a['last_goal'], $b['name']]);

	return ['months' => $months, 'series' => $series];
}

/**
 * @return true|string true on success, otherwise an error message
 */
function setStockGoal(PDO $pdo, int $userId, string $note, float $amount, bool $isNewStock = false): bool|string {
	$note = trim($note);

	if ($note === '' || mb_strlen($note) > STOCK_GOAL_NOTE_MAX_LENGTH) {
		return 'Enter a stock name of up to ' . STOCK_GOAL_NOTE_MAX_LENGTH . ' characters.';
	}

	if ($amount < 0 || $amount > STOCK_GOAL_MAX_AMOUNT) {
		return 'Enter an amount between 0 and ' . number_format(STOCK_GOAL_MAX_AMOUNT, 0) . '.';
	}

	$amount = round($amount, 2);
	$existing = getStockGoalHistory($pdo, $userId)[stockGoalKey($note)] ?? null;

	if ($isNewStock) {
		if ($amount <= 0) {
			return 'Enter a monthly goal above 0.';
		}

		if ($existing !== null && getStockGoalAmountForMonth($existing['history'], Clock::monthStart()) > 0) {
			return 'This stock already has a goal. Edit it from its card instead.';
		}
	}

	if ($amount <= 0 && $existing === null) {
		return true;
	}

	if ($existing === null) {
		assignColorToNewStock($pdo, $userId, $note);
	}

	$rawSql = $pdo->prepare(
		'INSERT INTO stock_goals (account_id, stock_note, monthly_amount, effective_from)
		VALUES (?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE monthly_amount = VALUES(monthly_amount)'
	);
	$rawSql->execute([$userId, $existing['note'] ?? $note, $amount, Clock::monthStart()]);

	return true;
}
