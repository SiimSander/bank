<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/goalInfoTooltips.php';

final class GoalInfoTooltipsTest extends TestCase {
	public function testPeriodPhraseInInvestmentsShouldTooltip(): void {
		self::assertSame(
			"How much you should've invest today",
			getGoalInfoTooltipText('investments', 'should', 'today'),
		);
		self::assertSame(
			"How much you should've invest this week",
			getGoalInfoTooltipText('investments', 'should', 'week'),
		);
		self::assertSame(
			"How much you should've invest this month",
			getGoalInfoTooltipText('investments', 'should', 'month'),
		);
	}
}
