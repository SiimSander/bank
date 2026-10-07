<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\TypeFixtures;

final class GoalTrackingTest extends TestCase {
	public function testAtLeastGoalIsMetWhenActualMeetsTarget(): void {
		self::assertTrue(isGoalTrackingMet(100, 120, 'at_least'));
	}

	public function testAtLeastGoalIsMissedWhenActualIsBelowTarget(): void {
		self::assertFalse(isGoalTrackingMet(100, 80, 'at_least'));
	}

	public function testUnderBudgetGoalIsMetWhenSpentWithinBudget(): void {
		self::assertTrue(isGoalTrackingMet(100, 80, 'under_budget'));
		self::assertTrue(isGoalTrackingMet(100, 100, 'under_budget'));
	}

	public function testUnderBudgetGoalIsMissedWhenOverBudget(): void {
		self::assertFalse(isGoalTrackingMet(100, 120, 'under_budget'));
	}

	public function testSavingsDisplayLabels(): void {
		$config = getGoalTrackingDisplayConfig(TypeFixtures::savings());

		self::assertSame('Should save', $config['should_label']);
		self::assertSame('Saved', $config['actual_label']);
		self::assertSame('at_least', $config['check_mode']);
	}

	public function testDeptWalletOutDisplayLabels(): void {
		$config = getGoalTrackingDisplayConfig(TypeFixtures::dept());

		self::assertSame('Pay dept', $config['should_label']);
		self::assertSame('Paid', $config['actual_label']);
	}

	public function testZeroGoalHasNoCheckState(): void {
		self::assertNull(getGoalTrackingCheckState(0, 50, TypeFixtures::savings()));
	}

	public function testNeedMoreVerbForSavings(): void {
		self::assertSame('save', getGoalTrackingNeedMoreVerb(TypeFixtures::savings()));
	}

	public function testNeedMoreAmountWhenBelowGoal(): void {
		self::assertSame(8.0, getGoalTrackingNeedMoreAmount(15, 7, TypeFixtures::savings()));
	}

	public function testNeedMoreAmountNullWhenGoalMet(): void {
		self::assertNull(getGoalTrackingNeedMoreAmount(15, 15, TypeFixtures::savings()));
		self::assertNull(getGoalTrackingNeedMoreAmount(15, 20, TypeFixtures::savings()));
	}
}
