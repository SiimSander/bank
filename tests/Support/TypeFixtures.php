<?php

declare(strict_types=1);

namespace Tests\Support;

final class TypeFixtures {
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
