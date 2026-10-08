<?php

require_once __DIR__ . '/BankRequestCache.php';
require_once __DIR__ . '/Clock.php';
require_once __DIR__ . '/numericInput.php';
require_once __DIR__ . '/typeIncomePercentHistory.php';
require_once __DIR__ . '/goalCalculations.php';

const BANK_BALANCE_MODES = ['wallet_in', 'wallet_out', 'pot', 'adjustment'];
const BANK_RESERVED_SLUGS = ['history', 'configure', 'update', 'delete', 'lhv'];

function defaultBankTypeDefinitions(): array {
	return [
		['slug' => 'income', 'label' => 'Income', 'color_hex' => '#22c55e', 'income_percent' => null, 'balance_mode' => 'wallet_in', 'is_system' => 1, 'show_in_pills' => 1, 'sort_order' => 0],
		['slug' => 'expenses', 'label' => 'Expenses', 'color_hex' => '#ff0000', 'income_percent' => 1, 'balance_mode' => 'wallet_out', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 1],
		['slug' => 'wallet_adjustment', 'label' => 'Balance adjustment', 'color_hex' => '#888888', 'income_percent' => null, 'balance_mode' => 'adjustment', 'is_system' => 1, 'show_in_pills' => 0, 'sort_order' => 2],
	];
}

function ensureBankTypesSeeded(PDO $pdo, int $accountId): void {
	$check = $pdo->prepare('SELECT COUNT(*) FROM bank_entry_types WHERE account_id = ?');
	$check->execute([$accountId]);

	if ((int) $check->fetchColumn() > 0) {
		return;
	}

	seedDefaultBankTypes($pdo, $accountId);
}

function seedDefaultBankTypes(PDO $pdo, int $accountId): void {
	seedBankTypesFromDefinitions($pdo, $accountId, defaultBankTypeDefinitions());
}

function seedBankTypesFromDefinitions(PDO $pdo, int $accountId, array $definitions): void {
	$insert = $pdo->prepare(
		'INSERT INTO bank_entry_types
			(account_id, slug, label, color_hex, income_percent, balance_mode, is_active, is_system, show_in_pills, sort_order)
		VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)'
	);

	foreach ($definitions as $type) {
		$insert->execute([
			$accountId,
			$type['slug'],
			$type['label'],
			$type['color_hex'],
			$type['income_percent'],
			$type['balance_mode'],
			$type['is_system'],
			$type['show_in_pills'],
			$type['sort_order'],
		]);

		if ($type['income_percent'] !== null) {
			$typeId = (int) $pdo->lastInsertId();
			$createdStatement = $pdo->prepare('SELECT DATE(created_at) FROM bank_entry_types WHERE id = ?');
			$createdStatement->execute([$typeId]);
			$effectiveFrom = (string) $createdStatement->fetchColumn();

			recordIncomePercentSegment($pdo, $typeId, $effectiveFrom, (float) $type['income_percent']);
		}
	}
}

function systemBankTypeDefinitions(): array {
	return array_values(array_filter(
		defaultBankTypeDefinitions(),
		fn(array $type) => $type['is_system'] === 1
	));
}

function getBankTypes(PDO $pdo, int $accountId, bool $activeOnly = false): array {
	return BankRequestCache::remember(
		'bank_types:' . $accountId . ':' . ($activeOnly ? 'active' : 'all'),
		function () use ($pdo, $accountId, $activeOnly): array {
			ensureBankTypesSeeded($pdo, $accountId);

			$sql = 'SELECT id, slug, label, color_hex, income_percent, goal_income_from, created_at, balance_mode, is_active, is_system, show_in_pills, sort_order
				FROM bank_entry_types
				WHERE account_id = ?';

			if ($activeOnly) {
				$sql .= ' AND is_active = 1';
			}

			$sql .= ' ORDER BY sort_order ASC, label ASC';

			$statement = $pdo->prepare($sql);
			$statement->execute([$accountId]);

			return array_map('normalizeBankTypeRow', $statement->fetchAll());
		}
	);
}

function getBankTypeBySlug(PDO $pdo, int $accountId, string $slug): ?array {
	if (BankRequestCache::isEnabled()) {
		foreach (getBankTypes($pdo, $accountId) as $type) {
			if ($type['slug'] === $slug) {
				return $type;
			}
		}

		return null;
	}

	ensureBankTypesSeeded($pdo, $accountId);

	$statement = $pdo->prepare(
		'SELECT id, slug, label, color_hex, income_percent, goal_income_from, created_at, balance_mode, is_active, is_system, show_in_pills, sort_order
		FROM bank_entry_types
		WHERE account_id = ? AND slug = ?'
	);
	$statement->execute([$accountId, $slug]);
	$row = $statement->fetch();

	return $row !== false ? normalizeBankTypeRow($row) : null;
}

function getBankTypeById(PDO $pdo, int $accountId, int $typeId): ?array {
	$statement = $pdo->prepare(
		'SELECT id, slug, label, color_hex, income_percent, goal_income_from, created_at, balance_mode, is_active, is_system, show_in_pills, sort_order
		FROM bank_entry_types
		WHERE account_id = ? AND id = ?'
	);
	$statement->execute([$accountId, $typeId]);
	$row = $statement->fetch();

	return $row !== false ? normalizeBankTypeRow($row) : null;
}

function normalizeBankTypeRow(array $row): array {
	$row['id'] = (int) $row['id'];
	$row['is_active'] = (int) $row['is_active'] === 1;
	$row['is_system'] = (int) $row['is_system'] === 1;
	$row['show_in_pills'] = (int) $row['show_in_pills'] === 1;
	$row['sort_order'] = (int) $row['sort_order'];
	$row['income_percent'] = $row['income_percent'] !== null ? (float) $row['income_percent'] : null;
	$row['goal_income_from'] = $row['goal_income_from'] ?? null;

	return $row;
}

function getVisibleBankTypes(PDO $pdo, int $accountId): array {
	return array_values(array_filter(
		getBankTypes($pdo, $accountId, true),
		fn(array $type) => $type['show_in_pills']
	));
}

function getAddFormBankTypes(PDO $pdo, int $accountId): array {
	return array_values(array_filter(
		getBankTypes($pdo, $accountId, true),
		fn(array $type) => $type['slug'] !== 'wallet_adjustment' && $type['slug'] !== 'kogumiskonto'
	));
}

function getEditableBankTypes(PDO $pdo, int $accountId): array {
	return getBankTypes($pdo, $accountId, true);
}

function getActiveBankTypes(PDO $pdo, int $accountId): array {
	return getBankTypes($pdo, $accountId, true);
}

function isBankTypeHiddenFromHistory(array $type): bool {
	return in_array($type['slug'], ['income', 'wallet_adjustment', 'kogumiskonto'], true);
}

function getHistoryCardTypes(array $bankTypes): array {
	return array_values(array_filter(
		$bankTypes,
		fn(array $type) => !isBankTypeHiddenFromHistory($type)
	));
}

function getHistoryDisplayTypes(PDO $pdo, int $accountId): array {
	return getHistoryCardTypes(getBankTypes($pdo, $accountId, false));
}

function getHistoryGrowthTypes(array $historyTypes): array {
	return array_values(array_filter(
		$historyTypes,
		fn(array $type) => $type['balance_mode'] === 'pot'
	));
}

function bankTypeHasEntries(PDO $pdo, int $accountId, string $slug): bool {
	$statement = $pdo->prepare(
		'SELECT 1 FROM bank_entries WHERE account_id = ? AND type = ? LIMIT 1'
	);
	$statement->execute([$accountId, $slug]);

	return $statement->fetchColumn() !== false;
}

function canAccessBankTypePage(PDO $pdo, int $accountId, string $slug): bool {
	$type = getBankTypeBySlug($pdo, $accountId, $slug);

	if ($type === null) {
		return false;
	}

	return $type['is_active'];
}

function bankTypeSupportsWithdraw(array $type): bool {
	return in_array($type['balance_mode'], ['pot', 'wallet_out'], true);
}

function bankTypeShowsDirectionOnMainForm(array $type): bool {
	return $type['balance_mode'] === 'pot';
}

function bankEntryDirectionLabels(array $type): array {
	$label = $type['label'];

	if ($type['balance_mode'] === 'pot') {
		return [
			'in' => "Add to {$label}",
			'out' => "Withdraw from {$label}",
		];
	}

	if ($type['balance_mode'] === 'wallet_out') {
		return [
			'in' => "Record {$label}",
			'out' => "Refund {$label}",
		];
	}

	return [
		'in' => 'Add',
		'out' => 'Withdraw',
	];
}

function bankEntryTypeLabelForType(array $type, string $method = 'card'): string {
	if ($type['slug'] === 'wallet_adjustment') {
		return $method === 'cash' ? 'Cash balance adjustment' : 'Bank balance adjustment';
	}

	return $type['label'];
}

function bankEntryTypeLabel(PDO $pdo, int $accountId, string $slug, string $method = 'card'): string {
	$type = getBankTypeBySlug($pdo, $accountId, $slug);

	if ($type === null) {
		return $slug;
	}

	return bankEntryTypeLabelForType($type, $method);
}

function sumActiveIncomePercents(array $types): float {
	$sum = 0.0;

	foreach ($types as $type) {
		if (!$type['is_active'] || $type['income_percent'] === null) {
			continue;
		}

		$sum += $type['income_percent'];
	}

	return round($sum, 4);
}

function hasActiveIncomePercents(array $types): bool {
	foreach ($types as $type) {
		if ($type['is_active'] && $type['income_percent'] !== null) {
			return true;
		}
	}

	return false;
}

function validateIncomePercentSum(array $types): ?string {
	if (!hasActiveIncomePercents($types)) {
		return null;
	}

	$sum = sumActiveIncomePercents($types);

	if ($sum > 1.0001) {
		$percent = round($sum * 100, 1);

		return "Active income percentages cannot exceed 100% (currently {$percent}%). Reduce other types first.";
	}

	return null;
}

function isValidBankTypeSlug(string $slug): bool {
	if ($slug === '' || strlen($slug) > 64) {
		return false;
	}

	if (in_array($slug, BANK_RESERVED_SLUGS, true)) {
		return false;
	}

	return (bool) preg_match('/^[a-z0-9_]+$/', $slug);
}

function isValidBankTypeColor(string $color): bool {
	return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $color);
}

function slugifyBankTypeLabel(string $label): string {
	$slug = strtolower(trim($label));
	$slug = preg_replace('/[^a-z0-9_]+/', '_', $slug) ?? '';
	$slug = trim($slug, '_');

	return $slug !== '' ? $slug : 'type';
}

function hexToRgba(string $hex, float $alpha): string {
	$hex = ltrim($hex, '#');
	$r = hexdec(substr($hex, 0, 2));
	$g = hexdec(substr($hex, 2, 2));
	$b = hexdec(substr($hex, 4, 2));

	return "rgba({$r}, {$g}, {$b}, {$alpha})";
}

function renderBankTypeCssVariables(array $types): string {
	$lines = [];

	foreach ($types as $type) {
		$slug = $type['slug'];
		$lines[] = "--type-color-{$slug}: {$type['color_hex']};";
		$lines[] = "--type-bg-{$slug}: " . hexToRgba($type['color_hex'], 0.12) . ';';
	}

	return implode("\n\t", $lines);
}

function getPotTypeSlugs(array $types): array {
	return array_values(array_map(
		fn(array $type) => $type['slug'],
		array_filter($types, fn(array $type) => $type['balance_mode'] === 'pot')
	));
}

function createBankType(PDO $pdo, int $accountId, array $input): array {
	$label = trim($input['label'] ?? '');
	$slug = trim($input['slug'] ?? '') ?: slugifyBankTypeLabel($label);
	$colorHex = strtolower(trim($input['color_hex'] ?? '#888888'));
	$balanceMode = $input['balance_mode'] ?? 'pot';
	$incomePercent = $input['income_percent'] ?? null;

	if ($label === '') {
		return ['success' => false, 'error' => 'Label is required.'];
	}

	if (!isValidBankTypeSlug($slug)) {
		return ['success' => false, 'error' => 'Slug must use lowercase letters, numbers, and underscores.'];
	}

	if (!isValidBankTypeColor($colorHex)) {
		return ['success' => false, 'error' => 'Color must be a valid hex code.'];
	}

	if (!in_array($balanceMode, BANK_BALANCE_MODES, true) || in_array($balanceMode, ['wallet_in', 'adjustment'], true)) {
		return ['success' => false, 'error' => 'New types must be expenses (wallet_out) or savings bucket (pot).'];
	}

	if ($incomePercent !== null && $incomePercent !== '') {
		if (!isPlainDecimal($incomePercent)) {
			return ['success' => false, 'error' => 'Income percent must be a number.'];
		}

		$incomePercent = round((float) $incomePercent, 4);

		if ($incomePercent < 0 || $incomePercent > 1) {
			return ['success' => false, 'error' => 'Income percent must be between 0 and 1.'];
		}
	} else {
		$incomePercent = null;
	}

	$existingTypes = getBankTypes($pdo, $accountId);
	foreach ($existingTypes as $type) {
		if ($type['slug'] === $slug) {
			return ['success' => false, 'error' => 'A type with this slug already exists.'];
		}
	}

	$maxOrder = 0;
	foreach ($existingTypes as $type) {
		$maxOrder = max($maxOrder, $type['sort_order']);
	}

	$trialTypes = array_merge($existingTypes, [[
		'slug' => $slug,
		'label' => $label,
		'color_hex' => $colorHex,
		'income_percent' => $incomePercent,
		'balance_mode' => $balanceMode,
		'is_active' => true,
		'is_system' => false,
		'show_in_pills' => true,
		'sort_order' => $maxOrder + 1,
	]]);

	$percentError = validateIncomePercentSum($trialTypes);
	if ($percentError !== null) {
		return ['success' => false, 'error' => $percentError];
	}

	$goalIncomeFrom = $incomePercent !== null ? Clock::today() : null;

	$insert = $pdo->prepare(
		'INSERT INTO bank_entry_types
			(account_id, slug, label, color_hex, income_percent, goal_income_from, balance_mode, is_active, is_system, show_in_pills, sort_order)
		VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, 1, ?)'
	);
	$insert->execute([$accountId, $slug, $label, $colorHex, $incomePercent, $goalIncomeFrom, $balanceMode, $maxOrder + 1]);

	$newTypeId = (int) $pdo->lastInsertId();

	if ($incomePercent !== null) {
		$newType = getBankTypeById($pdo, $accountId, $newTypeId);
		if ($newType !== null) {
			ensureInitialIncomePercentSegment($pdo, $newType);
		}
	}

	return ['success' => true, 'id' => $newTypeId, 'slug' => $slug];
}

function updateBankType(PDO $pdo, int $accountId, int $typeId, array $input): array {
	$type = getBankTypeById($pdo, $accountId, $typeId);

	if ($type === null) {
		return ['success' => false, 'error' => 'Type not found.'];
	}

	$submittedPercent = $input['income_percent'] ?? null;
	if ($submittedPercent !== null && $submittedPercent !== '' && !isPlainDecimal($submittedPercent)) {
		return ['success' => false, 'error' => 'Income percent must be a number.'];
	}

	$label = trim($input['label'] ?? $type['label']);
	$newSlug = trim($input['slug'] ?? $type['slug']);
	$colorHex = strtolower(trim($input['color_hex'] ?? $type['color_hex']));
	$incomePercent = array_key_exists('income_percent', $input)
		? ($input['income_percent'] === '' || $input['income_percent'] === null ? null : round((float) $input['income_percent'], 4))
		: $type['income_percent'];
	$isActive = array_key_exists('is_active', $input) ? (bool) $input['is_active'] : $type['is_active'];

	if ($label === '') {
		return ['success' => false, 'error' => 'Label is required.'];
	}

	if ($type['is_system']) {
		$newSlug = $type['slug'];
		$isActive = true;
	} elseif (!isValidBankTypeSlug($newSlug)) {
		return ['success' => false, 'error' => 'Slug must use lowercase letters, numbers, and underscores.'];
	}

	if (!isValidBankTypeColor($colorHex)) {
		return ['success' => false, 'error' => 'Color must be a valid hex code.'];
	}

	if ($incomePercent !== null && ($incomePercent < 0 || $incomePercent > 1)) {
		return ['success' => false, 'error' => 'Income percent must be between 0 and 1.'];
	}

	$allTypes = getBankTypes($pdo, $accountId);
	$trialTypes = [];

	foreach ($allTypes as $row) {
		if ((int) $row['id'] === $typeId) {
			$row['slug'] = $newSlug;
			$row['label'] = $label;
			$row['color_hex'] = $colorHex;
			$row['income_percent'] = $incomePercent;
			$row['is_active'] = $isActive;
		} elseif ($row['slug'] === $newSlug && $newSlug !== $type['slug']) {
			return ['success' => false, 'error' => 'A type with this slug already exists.'];
		}

		$trialTypes[] = $row;
	}

	$percentError = validateIncomePercentSum($trialTypes);
	if ($percentError !== null) {
		return ['success' => false, 'error' => $percentError];
	}

	$oldPercent = $type['income_percent'] !== null ? round((float) $type['income_percent'], 4) : null;
	$newPercent = $incomePercent !== null ? round((float) $incomePercent, 4) : null;
	$wasActive = $type['is_active'];
	$goalIncomeFrom = $type['goal_income_from'];
	$goalIncomeFromChanged = false;

	if ($isActive && !$wasActive && $newPercent !== null) {
		$goalIncomeFrom = resolveGoalIncomeFromOnActivate($type);
		$goalIncomeFromChanged = true;
	} elseif ($oldPercent === null && $newPercent !== null && ($goalIncomeFrom === null || $goalIncomeFrom === '')) {
		$goalIncomeFrom = Clock::today();
		$goalIncomeFromChanged = true;
	} elseif ($newPercent === null) {
		$goalIncomeFrom = null;
		$goalIncomeFromChanged = true;
	}

	$pdo->beginTransaction();

	try {
		if ($newSlug !== $type['slug']) {
			$updateEntries = $pdo->prepare(
				'UPDATE bank_entries SET type = ? WHERE account_id = ? AND type = ?'
			);
			$updateEntries->execute([$newSlug, $accountId, $type['slug']]);

			$updateStats = $pdo->prepare(
				'UPDATE monthly_stat_by_type SET type_slug = ? WHERE account_id = ? AND type_slug = ?'
			);
			$updateStats->execute([$newSlug, $accountId, $type['slug']]);
		}

		$update = $pdo->prepare(
			'UPDATE bank_entry_types
			SET slug = ?, label = ?, color_hex = ?, income_percent = ?, is_active = ?
			WHERE id = ? AND account_id = ?'
		);
		$update->execute([
			$newSlug,
			$label,
			$colorHex,
			$incomePercent,
			$isActive ? 1 : 0,
			$typeId,
			$accountId,
		]);

		if ($goalIncomeFromChanged) {
			$updateGoalFrom = $pdo->prepare(
				'UPDATE bank_entry_types SET goal_income_from = ? WHERE id = ? AND account_id = ?'
			);
			$updateGoalFrom->execute([$goalIncomeFrom, $typeId, $accountId]);
		}

		if ($isActive && !$wasActive && $newPercent !== null && $goalIncomeFrom !== null && $goalIncomeFrom !== '') {
			recordIncomePercentSegment($pdo, $typeId, $goalIncomeFrom, $newPercent);
		}

		recordIncomePercentChangeIfNeeded($pdo, $typeId, $oldPercent, $newPercent);

		$pdo->commit();
	} catch (Throwable $e) {
		$pdo->rollBack();

		return ['success' => false, 'error' => 'Could not save type changes.'];
	}

	return ['success' => true, 'slug' => $newSlug];
}

function deleteBankType(PDO $pdo, int $accountId, int $typeId): array {
	$type = getBankTypeById($pdo, $accountId, $typeId);

	if ($type === null) {
		return ['success' => false, 'error' => 'Type not found.'];
	}

	if ($type['is_system']) {
		return ['success' => false, 'error' => 'System types cannot be deleted.'];
	}

	if (bankTypeHasEntries($pdo, $accountId, $type['slug'])) {
		return ['success' => false, 'error' => 'This type has entries. Deactivate it instead of deleting.'];
	}

	$delete = $pdo->prepare('DELETE FROM bank_entry_types WHERE id = ? AND account_id = ?');
	$delete->execute([$typeId, $accountId]);

	if ($delete->rowCount() === 0) {
		return ['success' => false, 'error' => 'Type not found.'];
	}

	$remaining = getBankTypes($pdo, $accountId);
	$percentError = validateIncomePercentSum($remaining);
	if ($percentError !== null) {
		return ['success' => false, 'error' => $percentError];
	}

	return ['success' => true];
}

function toggleBankTypeActive(PDO $pdo, int $accountId, int $typeId, bool $isActive): array {
	return updateBankType($pdo, $accountId, $typeId, ['is_active' => $isActive]);
}

function isBankTypeAllowedForNewEntry(PDO $pdo, int $accountId, string $slug): bool {
	$type = getBankTypeBySlug($pdo, $accountId, $slug);

	return $type !== null && $type['is_active'];
}
