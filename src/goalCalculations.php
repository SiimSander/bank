<?php

require_once __DIR__ . '/Clock.php';
require_once __DIR__ . '/typeIncomePercentHistory.php';

function sumIncomeBetween(PDO $pdo, int $userId, string $startDate, string $endDate): float {
	$statement = $pdo->prepare(
		"SELECT COALESCE(SUM(amount), 0)
		FROM bank_entries
		WHERE account_id = ?
			AND type = 'income'
			AND entry_date >= ?
			AND entry_date <= ?"
	);
	$statement->execute([$userId, $startDate, $endDate]);

	return (float) $statement->fetchColumn();
}

function resolveGoalIncomeFromOnActivate(array $type): string {
	$createdMonth = date('Y-m-01', strtotime($type['created_at']));
	$currentMonth = Clock::monthStart();

	if ($createdMonth === $currentMonth) {
		return date('Y-m-d', strtotime($type['created_at']));
	}

	return Clock::today();
}

function isPlanDefaultGoalType(array $type): bool {
	return in_array($type['slug'], ['expenses', 'savings', 'investments'], true);
}

function typeHasEntriesInMonth(PDO $pdo, int $userId, string $slug, string $statMonth): bool {
	$monthEnd = date('Y-m-t', strtotime($statMonth));
	$statement = $pdo->prepare(
		'SELECT 1
		FROM bank_entries
		WHERE account_id = ?
			AND type = ?
			AND entry_date >= ?
			AND entry_date <= ?
		LIMIT 1'
	);
	$statement->execute([$userId, $slug, $statMonth, $monthEnd]);

	return $statement->fetchColumn() !== false;
}

function typeWasInactiveWholeMonth(array $type, string $statMonth): bool {
	$goalFrom = $type['goal_income_from'] ?? null;

	if ($goalFrom === null || $goalFrom === '') {
		return false;
	}

	$goalFromMonth = date('Y-m-01', strtotime($goalFrom));

	return $goalFromMonth > $statMonth;
}

function typeAppliesToHistoryMonth(PDO $pdo, int $userId, array $type, string $statMonth): bool {
	if (typeHasEntriesInMonth($pdo, $userId, $type['slug'], $statMonth)) {
		return true;
	}

	if (typeWasInactiveWholeMonth($type, $statMonth)) {
		return false;
	}

	if (typeUsesTrackedMonthlyGoal($type)) {
		$createdDate = date('Y-m-d', strtotime($type['created_at']));
		$monthEnd = date('Y-m-t', strtotime($statMonth));

		if ($createdDate > $monthEnd) {
			return false;
		}

		return $statMonth >= date('Y-m-01', strtotime($type['created_at']));
	}

	if (isPlanDefaultGoalType($type)) {
		return true;
	}

	$createdDate = date('Y-m-d', strtotime($type['created_at']));
	$monthEnd = date('Y-m-t', strtotime($statMonth));

	return $createdDate <= $monthEnd;
}

function typeUsesTrackedMonthlyGoal(array $type): bool {
	$goalFrom = $type['goal_income_from'] ?? null;

	if ($goalFrom === null || $goalFrom === '') {
		return false;
	}

	return !isPlanDefaultGoalType($type);
}

function typeGoalIncomeStartDate(array $type, string $periodStart): string {
	$goalFrom = $type['goal_income_from'] ?? null;

	if ($goalFrom === null || $goalFrom === '') {
		return $periodStart;
	}

	$createdDate = date('Y-m-d', strtotime($type['created_at']));
	$createdMonth = date('Y-m-01', strtotime($type['created_at']));
	$goalFromMonth = date('Y-m-01', strtotime($goalFrom));
	$periodMonth = date('Y-m-01', strtotime($periodStart));

	if ($goalFromMonth > $createdMonth) {
		if ($periodMonth === $goalFromMonth) {
			return $goalFrom;
		}

		return $periodStart;
	}

	if ($goalFrom <= $createdDate) {
		return max($periodStart, $createdDate);
	}

	if ($createdDate === $periodMonth) {
		return max($periodStart, $goalFrom);
	}

	return max($periodStart, $createdDate);
}

function typeCountsIncomeForGoalOnDate(array $type, string $date): bool {
	if (typeUsesTrackedMonthlyGoal($type)) {
		$incomeStart = typeGoalIncomeStartDate($type, date('Y-m-01', strtotime($date)));

		return $date >= $incomeStart;
	}

	$goalFrom = $type['goal_income_from'] ?? null;

	if ($goalFrom !== null && $goalFrom !== '' && $date < $goalFrom) {
		return false;
	}

	return true;
}

function sumGoalIncomeWeightedByPercent(
	PDO $pdo,
	int $userId,
	array $type,
	string $periodStart,
	string $periodEnd,
	bool $forTrackedMonthlyGoal = true,
): float {
	if ($type['income_percent'] === null) {
		return 0.0;
	}

	if ($forTrackedMonthlyGoal) {
		$incomeStart = typeGoalIncomeStartDate($type, $periodStart);
		$startDate = max($periodStart, $incomeStart);
	} else {
		$startDate = $periodStart;
	}

	if ($startDate > $periodEnd) {
		return 0.0;
	}

	$statement = $pdo->prepare(
		"SELECT entry_date, amount
		FROM bank_entries
		WHERE account_id = ?
			AND type = 'income'
			AND entry_date >= ?
			AND entry_date <= ?
		ORDER BY entry_date ASC, id ASC"
	);
	$statement->execute([$userId, $startDate, $periodEnd]);

	$total = 0.0;
	$fallbackPercent = (float) $type['income_percent'];

	foreach ($statement->fetchAll() as $row) {
		$entryDate = $row['entry_date'];

		if ($forTrackedMonthlyGoal) {
			if (!typeCountsIncomeForGoalOnDate($type, $entryDate)) {
				continue;
			}
		} elseif (!isPlanDefaultGoalType($type)) {
			// Display monthly goal for custom types uses full calendar-month income.
		} elseif (!typeCountsIncomeForGoalOnDate($type, $entryDate)) {
			continue;
		}

		$percent = getIncomePercentEffectiveOnDate(
			$pdo,
			(int) $type['id'],
			$entryDate,
			$fallbackPercent
		);

		if ($percent === null) {
			if (!$forTrackedMonthlyGoal) {
				$percent = $fallbackPercent;
			} else {
				continue;
			}
		}

		$total += (float) $row['amount'] * $percent;
	}

	return round($total, 2);
}

function typeShouldRecomputeMonthlyDisplayGoal(PDO $pdo, int $userId, array $type, string $statMonth): bool {
	if ($type['income_percent'] === null) {
		return false;
	}

	if ($type['is_active']) {
		return true;
	}

	return typeHasEntriesInMonth($pdo, $userId, $type['slug'], $statMonth);
}

function computeTypeDisplayGoalAmount(
	PDO $pdo,
	int $userId,
	array $type,
	string $periodStart,
	string $periodEnd,
	bool $requireActive = true,
): float {
	if ($requireActive && (!$type['is_active'] || $type['income_percent'] === null)) {
		return 0.0;
	}

	return sumGoalIncomeWeightedByPercent($pdo, $userId, $type, $periodStart, $periodEnd, false);
}

function computeTypeTrackedGoalAmount(
	PDO $pdo,
	int $userId,
	array $type,
	string $periodStart,
	string $periodEnd,
	bool $requireActive = true,
): float {
	if ($requireActive && (!$type['is_active'] || $type['income_percent'] === null)) {
		return 0.0;
	}

	$incomeStart = typeGoalIncomeStartDate($type, $periodStart);

	if ($incomeStart > $periodEnd) {
		return 0.0;
	}

	return sumGoalIncomeWeightedByPercent($pdo, $userId, $type, $incomeStart, $periodEnd, true);
}

function computeTypeMonthlyGoalAmount(
	PDO $pdo,
	int $userId,
	array $type,
	string $statMonth,
	string $monthEnd,
	bool $requireActive = true,
): float {
	if (!typeUsesTrackedMonthlyGoal($type)) {
		return computeTypeDisplayGoalAmount($pdo, $userId, $type, $statMonth, $monthEnd, $requireActive);
	}

	return computeTypeTrackedGoalAmount($pdo, $userId, $type, $statMonth, $monthEnd, $requireActive);
}

function computeTypeDailyGoalAmount(
	PDO $pdo,
	int $userId,
	array $type,
	string $date,
	bool $requireActive = true,
): float {
	if ($requireActive && (!$type['is_active'] || $type['income_percent'] === null)) {
		return 0.0;
	}

	if ($type['income_percent'] === null) {
		return 0.0;
	}

	if (!typeCountsIncomeForGoalOnDate($type, $date)) {
		return 0.0;
	}

	$income = sumIncomeBetween($pdo, $userId, $date, $date);
	$percent = getIncomePercentEffectiveOnDate(
		$pdo,
		(int) $type['id'],
		$date,
		(float) $type['income_percent']
	);

	if ($percent === null) {
		return 0.0;
	}

	return round($income * $percent, 2);
}

function computeTypeGoalAmount(PDO $pdo, int $userId, array $type, string $periodStart, string $periodEnd): float {
	if ($periodStart === $periodEnd) {
		return computeTypeDailyGoalAmount($pdo, $userId, $type, $periodStart);
	}

	return computeTypeDisplayGoalAmount($pdo, $userId, $type, $periodStart, $periodEnd);
}

function applyDisplayGoalsToMonthlyTypeStats(PDO $pdo, int $userId, string $statMonth, array $types, array $typeStats): array {
	$monthEnd = date('Y-m-t', strtotime($statMonth));

	foreach ($types as $type) {
		if ($type['slug'] === 'income' || $type['slug'] === 'wallet_adjustment') {
			continue;
		}

		if (!typeShouldRecomputeMonthlyDisplayGoal($pdo, $userId, $type, $statMonth)) {
			continue;
		}

		$slug = $type['slug'];
		$typeStats[$slug] ??= ['goal' => 0.0, 'actual' => 0.0];
		$typeStats[$slug]['goal'] = computeTypeMonthlyGoalAmount(
			$pdo,
			$userId,
			$type,
			$statMonth,
			$monthEnd,
			false
		);
	}

	return $typeStats;
}
