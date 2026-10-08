<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DatabaseTestCase;

final class PlanDefinitionsTest extends DatabaseTestCase {
	/**
	 * @return array<string, array{string}>
	 */
	public static function planKeys(): array {
		return array_map(fn(string $key) => [$key], array_combine(
			array_keys(presetPlanDefinitions()),
			array_keys(presetPlanDefinitions())
		));
	}

	public function testPresetPlanKeysAreTheOnesOnTheOnboardingPage(): void {
		self::assertSame(
			['scratch', 'steady_starter', 'balanced', 'aggressive_saver', 'aggressive_investor'],
			array_keys(presetPlanDefinitions())
		);
	}

	#[DataProvider('planKeys')]
	public function testPlanAllocatesExactlyTheWholeIncome(string $planKey): void {
		$percentSum = sumActiveIncomePercents($this->activePlanTypes($planKey));

		self::assertEqualsWithDelta(1.0, $percentSum, 0.00001);
	}

	#[DataProvider('planKeys')]
	public function testPlanAlwaysTracksExpenses(string $planKey): void {
		$slugs = array_column(presetPlanDefinitions()[$planKey]['types'], 'slug');

		self::assertContains('expenses', $slugs);
	}

	#[DataProvider('planKeys')]
	public function testPlanTypeSlugsAreUnique(string $planKey): void {
		$slugs = array_column(presetPlanDefinitions()[$planKey]['types'], 'slug');

		self::assertSame($slugs, array_values(array_unique($slugs)));
	}

	#[DataProvider('planKeys')]
	public function testApplyingPlanCreatesItsTypesAndTheSystemTypes(string $planKey): void {
		$accountId = $this->createTestAccount();

		self::assertTrue(applyPlanToAccount($this->pdo, $accountId, $planKey));

		$slugs = array_column(getBankTypes($this->pdo, $accountId), 'slug');
		$expected = array_column(presetPlanDefinitions()[$planKey]['types'], 'slug');

		foreach ($expected as $slug) {
			self::assertContains($slug, $slugs);
		}

		self::assertContains('income', $slugs);
		self::assertContains('wallet_adjustment', $slugs);
	}

	#[DataProvider('planKeys')]
	public function testApplyingPlanStoresThePlanPercents(string $planKey): void {
		$accountId = $this->createTestAccount();
		applyPlanToAccount($this->pdo, $accountId, $planKey);

		foreach (presetPlanDefinitions()[$planKey]['types'] as $definition) {
			if ($definition['income_percent'] === null) {
				continue;
			}

			$stored = getBankTypeBySlug($this->pdo, $accountId, $definition['slug']);

			self::assertEqualsWithDelta($definition['income_percent'], $stored['income_percent'], 0.00001, $planKey . ' / ' . $definition['slug']);
		}
	}

	public function testFreshAccountDefaultsToIncomeAndExpensesOnly(): void {
		$accountId = $this->createTestAccount();

		$types = getBankTypes($this->pdo, $accountId);

		self::assertSame(['income', 'expenses', 'wallet_adjustment'], array_column($types, 'slug'));
		self::assertSame(1.0, getBankTypeBySlug($this->pdo, $accountId, 'expenses')['income_percent']);
	}

	public function testUnknownPlanIsRejected(): void {
		self::assertFalse(isValidPlanKey('does_not_exist'));
		self::assertFalse(applyPlanToAccount($this->pdo, $this->createTestAccount(), 'does_not_exist'));
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function activePlanTypes(string $planKey): array {
		return array_map(
			fn(array $definition) => $definition + ['is_active' => true],
			presetPlanDefinitions()[$planKey]['types']
		);
	}
}
