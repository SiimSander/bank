<?php

require_once __DIR__ . '/bankTypes.php';

function presetPlanDefinitions(): array {
	return [
		'scratch' => [
			'name' => 'Start from scratch',
			'description' => 'Use the standard default split and adjust everything yourself.',
			'types' => defaultBankTypeDefinitions(),
		],
		'balanced' => [
			'name' => 'Balanced',
			'description' => 'A steady, all-around split.',
			'types' => [
				['slug' => 'expenses', 'label' => 'Expenses', 'color_hex' => '#f472b6', 'income_percent' => 0.60, 'balance_mode' => 'wallet_out', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 1],
				['slug' => 'savings', 'label' => 'Savings', 'color_hex' => '#60a5fa', 'income_percent' => 0.15, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 2],
				['slug' => 'investments', 'label' => 'Investments', 'color_hex' => '#a78bfa', 'income_percent' => 0.20, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 3],
				['slug' => 'pension', 'label' => 'Pension', 'color_hex' => '#fb923c', 'income_percent' => 0.05, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 4],
			],
		],
		'aggressive_saver' => [
			'name' => 'Aggressive Saver',
			'description' => 'Prioritizes growing investments faster.',
			'types' => [
				['slug' => 'expenses', 'label' => 'Expenses', 'color_hex' => '#f472b6', 'income_percent' => 0.50, 'balance_mode' => 'wallet_out', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 1],
				['slug' => 'savings', 'label' => 'Savings', 'color_hex' => '#60a5fa', 'income_percent' => 0.10, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 2],
				['slug' => 'investments', 'label' => 'Investments', 'color_hex' => '#a78bfa', 'income_percent' => 0.35, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 3],
				['slug' => 'pension', 'label' => 'Pension', 'color_hex' => '#fb923c', 'income_percent' => 0.05, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 4],
			],
		],
		'steady_starter' => [
			'name' => 'Steady Starter',
			'description' => 'Lower risk, bigger cash buffer to start.',
			'types' => [
				['slug' => 'expenses', 'label' => 'Expenses', 'color_hex' => '#f472b6', 'income_percent' => 0.70, 'balance_mode' => 'wallet_out', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 1],
				['slug' => 'savings', 'label' => 'Savings', 'color_hex' => '#60a5fa', 'income_percent' => 0.20, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 2],
				['slug' => 'investments', 'label' => 'Investments', 'color_hex' => '#a78bfa', 'income_percent' => 0.05, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 3],
				['slug' => 'pension', 'label' => 'Pension', 'color_hex' => '#fb923c', 'income_percent' => 0.05, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 4],
			],
		],
	];
}

function isValidPlanKey(string $planKey): bool {
	return array_key_exists($planKey, presetPlanDefinitions());
}

function applyPlanToAccount(PDO $pdo, int $accountId, string $planKey): bool {
	$plans = presetPlanDefinitions();

	if (!isset($plans[$planKey])) {
		return false;
	}

	$definitions = $planKey === 'scratch'
		? $plans[$planKey]['types']
		: array_merge(systemBankTypeDefinitions(), $plans[$planKey]['types']);

	seedBankTypesFromDefinitions($pdo, $accountId, $definitions);

	return true;
}
