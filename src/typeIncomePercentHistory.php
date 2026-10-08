<?php

require_once __DIR__ . '/BankRequestCache.php';
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

function typeHasIncomePercentHistory(PDO $pdo, int $bankEntryTypeId): bool {
	$check = $pdo->prepare(
		'SELECT 1 FROM bank_entry_type_income_percent_history WHERE bank_entry_type_id = ? LIMIT 1'
	);
	$check->execute([$bankEntryTypeId]);

	return $check->fetchColumn() !== false;
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

/**
 * @return array<int, array{effective_from: string, income_percent: float}> oldest segment first
 */
function getIncomePercentSegments(PDO $pdo, int $bankEntryTypeId): array {
	return BankRequestCache::remember(
		'income_percent_segments:' . $bankEntryTypeId,
		function () use ($pdo, $bankEntryTypeId): array {
			$statement = $pdo->prepare(
				'SELECT effective_from, income_percent
				FROM bank_entry_type_income_percent_history
				WHERE bank_entry_type_id = ?
				ORDER BY effective_from ASC'
			);
			$statement->execute([$bankEntryTypeId]);

			return array_map(
				fn(array $row) => [
					'effective_from' => (string) $row['effective_from'],
					'income_percent' => (float) $row['income_percent'],
				],
				$statement->fetchAll()
			);
		}
	);
}

function getIncomePercentEffectiveOnDate(PDO $pdo, int $bankEntryTypeId, string $date, ?float $fallbackPercent = null): ?float {
	$segments = getIncomePercentSegments($pdo, $bankEntryTypeId);

	if ($segments === []) {
		return $fallbackPercent;
	}

	$effectivePercent = null;

	foreach ($segments as $segment) {
		if ($segment['effective_from'] > $date) {
			break;
		}

		$effectivePercent = $segment['income_percent'];
	}

	// Dates before the first recorded segment have no percent, so null is correct there.
	return $effectivePercent;
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
