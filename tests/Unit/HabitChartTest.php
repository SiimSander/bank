<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HabitChartTest extends TestCase {
	protected function setUp(): void {
		putenv('TEST_TODAY=2026-10-15');
	}

	protected function tearDown(): void {
		putenv('TEST_TODAY=2026-09-15');
	}

	private function month(int $habitCount, string $monthStart = '2026-10-01'): array {
		$days = (int) date('t', strtotime($monthStart));
		$habits = [];

		for ($index = 1; $index <= $habitCount; $index++) {
			$statuses = [];

			for ($day = 1; $day <= $days; $day++) {
				$statuses[$day] = $day < 15 ? 'done' : ($day === 15 ? 'pending' : 'pending');
			}

			$habits[] = ['id' => $index, 'title' => 'Habit ' . $index, 'statuses' => $statuses];
		}

		return ['month_start' => $monthStart, 'days' => $days, 'habits' => $habits];
	}

	public function testDrawsOneCellPerHabitAndDay(): void {
		$svg = renderHabitChartSvg($this->month(3));

		self::assertSame(3 * 31, substr_count($svg, 'habit-chart__cell habit-chart__cell--'));
	}

	public function testUsesMonthLengthForDaySlots(): void {
		$svg = renderHabitChartSvg($this->month(2, '2026-02-01'));

		self::assertSame(2 * 28, substr_count($svg, 'habit-chart__cell habit-chart__cell--'));
		self::assertSame(28, substr_count($svg, 'class="habit-chart__day"'));
	}

	public function testShowsMonthYearAndNumberedHabitNames(): void {
		$svg = renderHabitChartSvg($this->month(2));

		self::assertStringContainsString('>OCTOBER<', $svg);
		self::assertStringContainsString('>2026<', $svg);
		self::assertStringContainsString('>1. Habit 1<', $svg);
		self::assertStringContainsString('>2. Habit 2<', $svg);
	}

	public function testMarksFutureDaysDifferentlyFromPending(): void {
		$svg = renderHabitChartSvg($this->month(1));

		self::assertSame(1, substr_count($svg, 'habit-chart__cell--pending'));
		self::assertSame(16, substr_count($svg, 'habit-chart__cell--future'));
		self::assertSame(14, substr_count($svg, 'habit-chart__cell--done'));
	}

	public function testHighlightsTodayOnlyWhenRequestedForTheCurrentMonth(): void {
		self::assertStringContainsString('habit-chart__today', renderHabitChartSvg($this->month(1), true));
		self::assertStringNotContainsString('habit-chart__today', renderHabitChartSvg($this->month(1)));
		self::assertStringNotContainsString('habit-chart__today', renderHabitChartSvg($this->month(1, '2026-09-01'), true));
	}

	public function testEscapesHabitNames(): void {
		$month = $this->month(1);
		$month['habits'][0]['title'] = '<script>alert(1)</script>';

		self::assertStringNotContainsString('<script>', renderHabitChartSvg($month));
	}

	public function testSectorsStartAtTwelveOClockAndGoClockwise(): void {
		[$topX, $topY] = habitChartPoint(330, 330, 100, 0);
		[$rightX, $rightY] = habitChartPoint(330, 330, 100, 90);
		[$leftX, $leftY] = habitChartPoint(330, 330, 100, 270);

		self::assertEqualsWithDelta([330.0, 230.0], [$topX, $topY], 0.001);
		self::assertEqualsWithDelta([430.0, 330.0], [$rightX, $rightY], 0.001);
		self::assertEqualsWithDelta([230.0, 330.0], [$leftX, $leftY], 0.001);
	}

	public function testEffectiveStatusRules(): void {
		$habit = ['created_date' => '2026-10-05'];

		self::assertSame('none', habitEffectiveStatus($habit, '2026-10-04', null, '2026-10-15'));
		self::assertSame('failed', habitEffectiveStatus($habit, '2026-10-06', null, '2026-10-15'));
		self::assertSame('done', habitEffectiveStatus($habit, '2026-10-06', 'done', '2026-10-15'));
		self::assertSame('pending', habitEffectiveStatus($habit, '2026-10-15', null, '2026-10-15'));
	}

	public function testMonthVisibilityRules(): void {
		$habit = ['created_date' => '2026-09-10', 'archived_from_month' => '2026-11-01'];

		self::assertFalse(isHabitVisibleInMonth($habit, '2026-08-01'));
		self::assertTrue(isHabitVisibleInMonth($habit, '2026-09-01'));
		self::assertTrue(isHabitVisibleInMonth($habit, '2026-10-01'));
		self::assertFalse(isHabitVisibleInMonth($habit, '2026-11-01'));
	}
}
