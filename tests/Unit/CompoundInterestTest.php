<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CompoundInterestTest extends TestCase {
	public function testZeroGrowthEndOfMonth(): void {
		$summary = computeCompoundInterestSummary(0, 100, 0, 1, false);

		self::assertSame(1200.0, $summary['totalCashInvested']);
		self::assertSame(1200.0, $summary['finalPortfolio']);
		self::assertSame(0.0, $summary['totalInterestEarned']);
	}

	public function testAnnuityDueExceedsOrdinaryAnnuity(): void {
		$ordinary = computeCompoundInterestSummary(0, 100, 10, 1, false);
		$due = computeCompoundInterestSummary(0, 100, 10, 1, true);

		self::assertGreaterThan($ordinary['finalPortfolio'], $due['finalPortfolio']);
	}

	public function testStartingPrincipalOnly(): void {
		$summary = computeCompoundInterestSummary(1000, 0, 10, 1, false);

		self::assertSame(1000.0, $summary['totalCashInvested']);
		self::assertSame(1104.71, $summary['finalPortfolio']);
		self::assertSame(104.71, $summary['totalInterestEarned']);
	}

	public function testScheduleLengthForTenYears(): void {
		$schedule = computeCompoundInterestSchedule(0, 100, 10, 10, false);

		self::assertCount(11, $schedule);
		self::assertSame(0, $schedule[0]['year']);
		self::assertSame(10, $schedule[10]['year']);
	}
}
