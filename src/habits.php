<?php

require_once __DIR__ . '/Clock.php';

const HABIT_MAX_COUNT = 8;
const HABIT_TITLE_MAX_LENGTH = 60;
const HABIT_EDIT_WINDOW_DAYS = 7;
const HABIT_STATUS_DONE = 'done';
const HABIT_STATUS_FAILED = 'failed';
const HABIT_STATUS_PENDING = 'pending';
const HABIT_STATUS_NONE = 'none';

function habitMonthStart(string $date): string {
	return date('Y-m-01', strtotime($date));
}

function habitMonthEnd(string $monthStart): string {
	return date('Y-m-t', strtotime($monthStart));
}

function isValidHabitDate(string $date): bool {
	$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

	return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

/** @return list<array{id: int, title: string, position: int, created_date: string, archived_from_month: ?string}> */
function getAllHabits(PDO $pdo, int $userId): array {
	$statement = $pdo->prepare(
		'SELECT id, title, position, created_date, archived_from_month
		FROM habits
		WHERE account_id = ?
		ORDER BY position ASC, id ASC'
	);
	$statement->execute([$userId]);

	return array_map(
		static fn(array $row): array => [
			'id' => (int) $row['id'],
			'title' => (string) $row['title'],
			'position' => (int) $row['position'],
			'created_date' => (string) $row['created_date'],
			'archived_from_month' => $row['archived_from_month'] !== null ? (string) $row['archived_from_month'] : null,
		],
		$statement->fetchAll()
	);
}

function isHabitVisibleInMonth(array $habit, string $monthStart): bool {
	if ($habit['created_date'] > habitMonthEnd($monthStart)) {
		return false;
	}

	return $habit['archived_from_month'] === null || $monthStart < $habit['archived_from_month'];
}

/** @return list<array{id: int, title: string, position: int, created_date: string, archived_from_month: ?string}> */
function getHabitsForMonth(PDO $pdo, int $userId, string $monthStart): array {
	return array_values(array_filter(
		getAllHabits($pdo, $userId),
		static fn(array $habit): bool => isHabitVisibleInMonth($habit, $monthStart)
	));
}

function getHabitById(PDO $pdo, int $userId, int $habitId): ?array {
	foreach (getAllHabits($pdo, $userId) as $habit) {
		if ($habit['id'] === $habitId) {
			return $habit;
		}
	}

	return null;
}

function normalizeHabitTitle(string $title): string {
	return trim(preg_replace('/\s+/u', ' ', $title) ?? '');
}

function validateHabitTitle(string $title): ?string {
	if ($title === '') {
		return 'Habit name is required.';
	}

	if (mb_strlen($title) > HABIT_TITLE_MAX_LENGTH) {
		return 'Habit name can be at most ' . HABIT_TITLE_MAX_LENGTH . ' characters.';
	}

	return null;
}

function createHabit(PDO $pdo, int $userId, string $title): array {
	$title = normalizeHabitTitle($title);
	$titleError = validateHabitTitle($title);

	if ($titleError !== null) {
		return ['success' => false, 'error' => $titleError];
	}

	$currentHabits = getHabitsForMonth($pdo, $userId, Clock::monthStart());

	if (count($currentHabits) >= HABIT_MAX_COUNT) {
		return ['success' => false, 'error' => 'You can track at most ' . HABIT_MAX_COUNT . ' habits.'];
	}

	$usedPositions = array_column($currentHabits, 'position');
	$position = 1;

	while (in_array($position, $usedPositions, true)) {
		$position++;
	}

	$insert = $pdo->prepare(
		'INSERT INTO habits (account_id, title, position, created_date) VALUES (?, ?, ?, ?)'
	);
	$insert->execute([$userId, $title, $position, Clock::today()]);

	return ['success' => true, 'id' => (int) $pdo->lastInsertId()];
}

function renameHabit(PDO $pdo, int $userId, int $habitId, string $title): array {
	$title = normalizeHabitTitle($title);
	$titleError = validateHabitTitle($title);

	if ($titleError !== null) {
		return ['success' => false, 'error' => $titleError];
	}

	$update = $pdo->prepare('UPDATE habits SET title = ? WHERE id = ? AND account_id = ?');
	$update->execute([$title, $habitId, $userId]);

	if ($update->rowCount() === 0 && getHabitById($pdo, $userId, $habitId) === null) {
		return ['success' => false, 'error' => 'Habit not found.'];
	}

	return ['success' => true, 'title' => $title];
}

function deleteHabit(PDO $pdo, int $userId, int $habitId): bool {
	$habit = getHabitById($pdo, $userId, $habitId);

	if ($habit === null || !isHabitVisibleInMonth($habit, Clock::monthStart())) {
		return false;
	}

	$update = $pdo->prepare(
		'UPDATE habits SET archived_from_month = ? WHERE id = ? AND account_id = ?'
	);
	$update->execute([Clock::monthStart(), $habitId, $userId]);

	return true;
}

function earliestEditableHabitDate(?string $today = null): string {
	$today ??= Clock::today();

	return date('Y-m-d', strtotime($today . ' -' . HABIT_EDIT_WINDOW_DAYS . ' days'));
}

function isHabitDateEditable(string $date, ?string $today = null): bool {
	$today ??= Clock::today();

	return isValidHabitDate($date) && $date <= $today && $date >= earliestEditableHabitDate($today);
}

/** @return list<string> oldest first, ending with today */
function getEditableHabitDates(?string $today = null): array {
	$today ??= Clock::today();
	$dates = [];

	for ($offset = HABIT_EDIT_WINDOW_DAYS; $offset >= 0; $offset--) {
		$dates[] = date('Y-m-d', strtotime($today . ' -' . $offset . ' days'));
	}

	return $dates;
}

function setHabitStatus(PDO $pdo, int $userId, int $habitId, string $date, string $status): bool {
	$today = Clock::today();

	if (!in_array($status, [HABIT_STATUS_DONE, HABIT_STATUS_FAILED, HABIT_STATUS_PENDING], true)) {
		return false;
	}

	if (!isHabitDateEditable($date, $today)) {
		return false;
	}

	if ($status === HABIT_STATUS_PENDING && $date !== $today) {
		return false;
	}

	$habit = getHabitById($pdo, $userId, $habitId);

	if ($habit === null || $date < $habit['created_date'] || !isHabitVisibleInMonth($habit, habitMonthStart($date))) {
		return false;
	}

	if ($status === HABIT_STATUS_PENDING) {
		$delete = $pdo->prepare('DELETE FROM habit_logs WHERE habit_id = ? AND log_date = ?');
		$delete->execute([$habitId, $date]);

		return true;
	}

	$upsert = $pdo->prepare(
		'INSERT INTO habit_logs (habit_id, log_date, status) VALUES (?, ?, ?)
		ON DUPLICATE KEY UPDATE status = VALUES(status)'
	);
	$upsert->execute([$habitId, $date, $status]);

	return true;
}

/** @return array<string, string> log date => explicit status */
function getHabitLogsBetween(PDO $pdo, int $habitId, string $from, string $to): array {
	$statement = $pdo->prepare(
		'SELECT log_date, status FROM habit_logs WHERE habit_id = ? AND log_date >= ? AND log_date <= ?'
	);
	$statement->execute([$habitId, $from, $to]);
	$logs = [];

	foreach ($statement->fetchAll() as $row) {
		$logs[(string) $row['log_date']] = (string) $row['status'];
	}

	return $logs;
}

/**
 * A day with no explicit mark counts as failed once it is over, so nothing needs to run at midnight.
 */
function habitEffectiveStatus(array $habit, string $date, ?string $explicitStatus, string $today): string {
	if ($date < $habit['created_date']) {
		return HABIT_STATUS_NONE;
	}

	if ($explicitStatus !== null) {
		return $explicitStatus;
	}

	return $date < $today ? HABIT_STATUS_FAILED : HABIT_STATUS_PENDING;
}

/**
 * @return array{
 *   month_start: string,
 *   days: int,
 *   habits: list<array{id: int, title: string, position: int, created_date: string, statuses: array<int, string>, done: int, failed: int}>,
 *   done: int,
 *   failed: int
 * }
 */
function buildHabitMonth(PDO $pdo, int $userId, string $monthStart): array {
	$today = Clock::today();
	$days = (int) date('t', strtotime($monthStart));
	$monthEnd = habitMonthEnd($monthStart);
	$habits = [];
	$totalDone = 0;
	$totalFailed = 0;

	foreach (getHabitsForMonth($pdo, $userId, $monthStart) as $habit) {
		$logs = getHabitLogsBetween($pdo, $habit['id'], $monthStart, $monthEnd);
		$statuses = [];
		$done = 0;
		$failed = 0;

		for ($day = 1; $day <= $days; $day++) {
			$date = date('Y-m-d', strtotime($monthStart . ' +' . ($day - 1) . ' days'));
			$status = habitEffectiveStatus($habit, $date, $logs[$date] ?? null, $today);
			$statuses[$day] = $status;

			if ($status === HABIT_STATUS_DONE) {
				$done++;
			} elseif ($status === HABIT_STATUS_FAILED) {
				$failed++;
			}
		}

		$habit['statuses'] = $statuses;
		$habit['done'] = $done;
		$habit['failed'] = $failed;
		$habits[] = $habit;
		$totalDone += $done;
		$totalFailed += $failed;
	}

	return [
		'month_start' => $monthStart,
		'days' => $days,
		'habits' => $habits,
		'done' => $totalDone,
		'failed' => $totalFailed,
	];
}

/** @return list<string> first day of each month that had habits, newest first */
function getHabitHistoryMonths(PDO $pdo, int $userId, bool $includeCurrentMonth = false): array {
	$habits = getAllHabits($pdo, $userId);

	if ($habits === []) {
		return [];
	}

	$earliest = min(array_column($habits, 'created_date'));
	$currentMonth = Clock::monthStart();
	$months = [];

	$lastMonth = $includeCurrentMonth ? $currentMonth : date('Y-m-d', strtotime($currentMonth . ' -1 month'));

	for ($month = habitMonthStart($earliest); $month <= $lastMonth; $month = date('Y-m-d', strtotime($month . ' +1 month'))) {
		foreach ($habits as $habit) {
			if (isHabitVisibleInMonth($habit, $month)) {
				$months[] = $month;
				break;
			}
		}
	}

	return array_reverse($months);
}

/** @return list<array{id: int, title: string, status: string}> habits shown for one day, with their effective status */
function getHabitDayItems(PDO $pdo, int $userId, string $date): array {
	$today = Clock::today();
	$monthStart = habitMonthStart($date);
	$items = [];

	foreach (getHabitsForMonth($pdo, $userId, $monthStart) as $habit) {
		if ($date < $habit['created_date']) {
			continue;
		}

		$logs = getHabitLogsBetween($pdo, $habit['id'], $date, $date);
		$items[] = [
			'id' => $habit['id'],
			'title' => $habit['title'],
			'status' => habitEffectiveStatus($habit, $date, $logs[$date] ?? null, $today),
		];
	}

	return $items;
}
