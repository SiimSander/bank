<?php

declare(strict_types=1);

namespace Tests\Support;

final class TypeFixtures {
	/**
	 * Full type set the goal, history and net-worth tests are written against.
	 * Production defaults are intentionally smaller (see defaultBankTypeDefinitions), so tests seed this set explicitly.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function classicDefinitions(): array {
		return [
			['slug' => 'income', 'label' => 'Income', 'color_hex' => '#22c55e', 'income_percent' => null, 'balance_mode' => 'wallet_in', 'is_system' => 1, 'show_in_pills' => 1, 'sort_order' => 0],
			['slug' => 'expenses', 'label' => 'Expenses', 'color_hex' => '#f472b6', 'income_percent' => 0.6, 'balance_mode' => 'wallet_out', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 1],
			['slug' => 'savings', 'label' => 'Savings', 'color_hex' => '#60a5fa', 'income_percent' => 0.15, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 2],
			['slug' => 'investments', 'label' => 'Investments', 'color_hex' => '#a78bfa', 'income_percent' => 0.25, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 3],
			['slug' => 'pension', 'label' => 'Pension', 'color_hex' => '#fb923c', 'income_percent' => null, 'balance_mode' => 'pot', 'is_system' => 0, 'show_in_pills' => 1, 'sort_order' => 4],
			['slug' => 'kogumiskonto', 'label' => 'Kogumiskonto', 'color_hex' => '#888888', 'income_percent' => null, 'balance_mode' => 'pot', 'is_system' => 1, 'show_in_pills' => 0, 'sort_order' => 5],
			['slug' => 'wallet_adjustment', 'label' => 'Balance adjustment', 'color_hex' => '#888888', 'income_percent' => null, 'balance_mode' => 'adjustment', 'is_system' => 1, 'show_in_pills' => 0, 'sort_order' => 6],
		];
	}

	public static function savings(): array {
		return [
			'slug' => 'savings',
			'label' => 'Savings',
			'balance_mode' => 'pot',
			'income_percent' => 0.15,
		];
	}

	public static function investments(): array {
		return [
			'slug' => 'investments',
			'label' => 'Investments',
			'balance_mode' => 'pot',
			'income_percent' => 0.25,
		];
	}

	public static function expenses(): array {
		return [
			'slug' => 'expenses',
			'label' => 'Expenses',
			'balance_mode' => 'wallet_out',
			'income_percent' => 0.6,
		];
	}

	public static function dept(): array {
		return [
			'slug' => 'dept',
			'label' => 'Dept',
			'balance_mode' => 'wallet_out',
			'income_percent' => 0.01,
		];
	}

	public static function pension(): array {
		return [
			'slug' => 'pension',
			'label' => 'Pension',
			'balance_mode' => 'pot',
			'income_percent' => null,
		];
	}
}
