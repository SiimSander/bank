<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StockGoalMathTest extends TestCase {
	public function testPercentIsNullWithoutGoal(): void {
		self::assertNull(getStockGoalPercent(0, 100));
		self::assertNull(getStockGoalPercent(-5, 100));
	}

	public function testPercentRoundsToOneDecimal(): void {
		self::assertSame(63.8, getStockGoalPercent(800, 510.01));
		self::assertSame(112.5, getStockGoalPercent(400, 450));
		self::assertSame(0.0, getStockGoalPercent(800, 0));
	}

	public function testStatus(): void {
		self::assertSame('none', getStockGoalStatus(0, 500));
		self::assertSame('partial', getStockGoalStatus(800, 799.99));
		self::assertSame('met', getStockGoalStatus(800, 800));
		self::assertSame('met', getStockGoalStatus(800, 1200));
	}

	public function testKeyIsCaseAndWhitespaceInsensitive(): void {
		self::assertSame(stockGoalKey('Wise (£WISE)'), stockGoalKey('  wise (£wise) '));
	}

	public function testNormalizeMonth(): void {
		self::assertSame('2026-08-01', normalizeStockGoalMonth('2026-08'));
		self::assertSame('2026-08-01', normalizeStockGoalMonth('2026-08-01'));
		self::assertNull(normalizeStockGoalMonth('2026-13'));
		self::assertNull(normalizeStockGoalMonth('2026-08-15'));
		self::assertNull(normalizeStockGoalMonth(null));
		self::assertNull(normalizeStockGoalMonth('nonsense'));
	}

	public function testCarryOverIsEmptyBeforeAnyMonthHasClosed(): void {
		$carry = calculateStockGoalCarryOver($this->goal('2026-07-01', 100), ['2026-07-01' => 80.0], '2026-07-01');

		self::assertSame(['missed' => 0.0, 'credit' => 0.0, 'missed_months' => []], $carry);
	}

	public function testCarryOverQueuesAShortfall(): void {
		$carry = calculateStockGoalCarryOver($this->goal('2026-07-01', 100), ['2026-07-01' => 80.0], '2026-08-01');

		self::assertSame(20.0, $carry['missed']);
		self::assertSame([['month' => '2026-07-01', 'amount' => 20.0]], $carry['missed_months']);
	}

	public function testCarryOverShortfallIsPaidByLaterExtra(): void {
		$carry = calculateStockGoalCarryOver(
			$this->goal('2026-07-01', 100),
			['2026-07-01' => 80.0, '2026-08-01' => 120.0],
			'2026-09-01'
		);

		self::assertSame(0.0, $carry['missed']);
		self::assertSame(0.0, $carry['credit']);
	}

	public function testCarryOverExtraBecomesCredit(): void {
		$carry = calculateStockGoalCarryOver(
			$this->goal('2026-07-01', 100),
			['2026-07-01' => 80.0, '2026-08-01' => 125.0],
			'2026-09-01'
		);

		self::assertSame(5.0, $carry['credit']);
	}

	public function testCarryOverPaysTheOldestShortfallFirst(): void {
		$carry = calculateStockGoalCarryOver(
			$this->goal('2026-07-01', 100),
			['2026-07-01' => 60.0, '2026-08-01' => 70.0, '2026-09-01' => 130.0],
			'2026-10-01'
		);

		self::assertSame(
			[['month' => '2026-07-01', 'amount' => 10.0], ['month' => '2026-08-01', 'amount' => 30.0]],
			$carry['missed_months']
		);
		self::assertSame(40.0, $carry['missed']);
	}

	public function testCarryOverCreditCoversALaterShortfallFirst(): void {
		$carry = calculateStockGoalCarryOver(
			$this->goal('2026-07-01', 100),
			['2026-07-01' => 150.0, '2026-08-01' => 60.0, '2026-09-01' => 30.0],
			'2026-10-01'
		);

		self::assertSame(60.0, $carry['missed']);
		self::assertSame(0.0, $carry['credit']);
	}

	public function testCarryOverCreditKeepsCarryingAcrossMonths(): void {
		$carry = calculateStockGoalCarryOver(
			$this->goal('2026-07-01', 100),
			['2026-07-01' => 300.0, '2026-08-01' => 100.0],
			'2026-09-01'
		);

		self::assertSame(200.0, $carry['credit']);
	}

	public function testCarryOverIsClearedWhenTheGoalIsRemoved(): void {
		$history = [
			['effective_from' => '2026-07-01', 'amount' => 100.0],
			['effective_from' => '2026-08-01', 'amount' => 0.0],
			['effective_from' => '2026-09-01', 'amount' => 100.0],
		];

		$carry = calculateStockGoalCarryOver($history, ['2026-07-01' => 20.0, '2026-09-01' => 100.0], '2026-10-01');

		self::assertSame(0.0, $carry['missed']);
	}

	public function testCarryOverIgnoresInvestmentsBeforeTheFirstGoal(): void {
		$carry = calculateStockGoalCarryOver($this->goal('2026-08-01', 100), ['2026-06-01' => 500.0], '2026-09-01');

		self::assertSame(100.0, $carry['missed']);
		self::assertSame(0.0, $carry['credit']);
	}

	private function goal(string $effectiveFrom, float $amount): array {
		return [['effective_from' => $effectiveFrom, 'amount' => $amount]];
	}

	public function testMonthWindowShowsTwoPreviousMonthsOnTheLatestMonth(): void {
		$bounds = ['earliest' => '2026-05-01', 'latest' => '2026-10-01'];

		self::assertSame(['2026-08-01', '2026-09-01', '2026-10-01'], getStockGoalMonthWindow('2026-10-01', $bounds));
	}

	public function testMonthWindowCentresTheSelectedMonth(): void {
		$bounds = ['earliest' => '2026-05-01', 'latest' => '2026-10-01'];

		self::assertSame(['2026-06-01', '2026-07-01', '2026-08-01'], getStockGoalMonthWindow('2026-07-01', $bounds));
		self::assertSame(['2026-08-01', '2026-09-01', '2026-10-01'], getStockGoalMonthWindow('2026-09-01', $bounds));
	}

	public function testMonthWindowStopsAtTheFirstMonth(): void {
		$bounds = ['earliest' => '2026-08-01', 'latest' => '2026-10-01'];

		self::assertSame(['2026-08-01', '2026-09-01', '2026-10-01'], getStockGoalMonthWindow('2026-08-01', $bounds));
		self::assertSame(['2026-05-01', '2026-06-01', '2026-07-01'], getStockGoalMonthWindow('2026-05-01', ['earliest' => '2026-05-01', 'latest' => '2026-10-01']));
	}

	public function testMonthWindowIsShorterWhenFewMonthsExist(): void {
		$bounds = ['earliest' => '2026-09-01', 'latest' => '2026-10-01'];

		self::assertSame(['2026-09-01', '2026-10-01'], getStockGoalMonthWindow('2026-10-01', $bounds));
	}

	public function testShiftMonth(): void {
		self::assertSame('2026-11-01', shiftStockGoalMonth('2026-10-01', 1));
		self::assertSame('2026-09-01', shiftStockGoalMonth('2026-10-01', -1));
		self::assertSame('2027-01-01', shiftStockGoalMonth('2026-12-01', 1));
	}
}
