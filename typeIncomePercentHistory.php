<?php

require_once __DIR__ . '/Clock.php';

function recordIncomePercentSegment(PDO $pdo, int $bankEntryTypeId, string $effectiveFrom, float $incomePercent): void {
	$incomePercent = round($incomePercent, 4);

	$statement = $pdo->prepare(
		'INSERT INTO bank_entry_type_income_percent_history (bank_entry_type_id, effective_from, income_percent)
		VALUES (?, ?, ?)
		ON DUPLICATE KEY UPDATE income_percent = VALUES(income_percent)'
	);
	$statement->execute([$bankEntryTypeId, $effectiveFrom, $incomePercent]);
}

function ensureInitialIncomePercentSegment(PDO $pdo, array $type): void {
	if ($type['income_percent'] === null) {
		return;
	}

	$check = $pdo->prepare(
		'SELECT 1 FROM bank_entry_type_income_percent_history WHERE bank_entry_type_id = ? LIMIT 1'
	);
	$check->execute([(int) $type['id']]);

	if ($check->fetchColumn() !== false) {
		return;
	}

	$effectiveFrom = date('Y-m-d', strtotime($type['created_at']));

	recordIncomePercentSegment($pdo, (int) $type['id'], $effectiveFrom, (float) $type['income_percent']);
}

function getIncomePercentEffectiveOnDate(PDO $pdo, int $bankEntryTypeId, string $date, ?float $fallbackPercent = null): ?float {
	$statement = $pdo->prepare(
		'SELECT income_percent
		FROM bank_entry_type_income_percent_history
		WHERE bank_entry_type_id = ?
			AND effective_from <= ?
		ORDER BY effective_from DESC
		LIMIT 1'
	);
	$statement->execute([$bankEntryTypeId, $date]);
	$row = $statement->fetchColumn();

	if ($row !== false) {
		return (float) $row;
	}

	$earliest = $pdo->prepare(
		'SELECT effective_from FROM bank_entry_type_income_percent_history
		WHERE bank_entry_type_id = ?
		ORDER BY effective_from ASC
		LIMIT 1'
	);
	$earliest->execute([$bankEntryTypeId]);
	$earliestFrom = $earliest->fetchColumn();

	if ($earliestFrom !== false && $date < $earliestFrom) {
		return null;
	}

	return $fallbackPercent;
}

function recordIncomePercentChangeIfNeeded(
	PDO $pdo,
	int $bankEntryTypeId,
	?float $oldPercent,
	?float $newPercent,
): void {
	if ($newPercent === null) {
		return;
	}

	$oldRounded = $oldPercent !== null ? round($oldPercent, 4) : null;
	$newRounded = round($newPercent, 4);

	if ($oldRounded === $newRounded) {
		return;
	}

	recordIncomePercentSegment($pdo, $bankEntryTypeId, Clock::today(), $newRounded);
}
