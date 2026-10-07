<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BankDateRangeTest extends TestCase {
	public function testCustomRangeUsesGivenBoundaries(): void {
		self::assertSame(
			['range' => 'custom', 'start' => '2026-08-01', 'end' => '2026-08-31'],
			parseBankDateRange('custom', '2026-08-01', '2026-08-31')
		);
	}

	public function testCustomRangeSwapsReversedBoundaries(): void {
		$result = parseBankDateRange('custom', '2026-08-31', '2026-08-01');

		self::assertSame('2026-08-01', $result['start']);
		self::assertSame('2026-08-31', $result['end']);
	}

	public function testCustomRangeEndIsCappedAtToday(): void {
		$today = date('Y-m-d');
		$result = parseBankDateRange('custom', '2020-01-01', '2999-12-31');

		self::assertSame('custom', $result['range']);
		self::assertSame($today, $result['end']);
	}

	public function testCustomRangeInTheFutureCollapsesToToday(): void {
		$today = date('Y-m-d');
		$result = parseBankDateRange('custom', '2998-01-01', '2999-12-31');

		self::assertSame($today, $result['start']);
		self::assertSame($today, $result['end']);
	}

	public function testCustomRangeWithMissingOrInvalidDatesFallsBackToAllTime(): void {
		self::assertSame('all', parseBankDateRange('custom')['range']);
		self::assertSame('all', parseBankDateRange('custom', '2026-08-01')['range']);
		self::assertSame('all', parseBankDateRange('custom', '2026-13-45', '2026-08-31')['range']);
		self::assertSame('all', parseBankDateRange('custom', 'garbage', 'garbage')['range']);
		self::assertNull(parseBankDateRange('custom', 'garbage', 'garbage')['start']);
	}

	public function testRemovedThreeMonthsRangeFallsBackToAllTime(): void {
		self::assertSame('all', parseBankDateRange('3months')['range']);
	}

	public function testPresetRangesIgnoreCustomDates(): void {
		$result = parseBankDateRange('month', '2020-01-01', '2020-01-31');

		self::assertSame('month', $result['range']);
		self::assertSame(date('Y-m-01'), $result['start']);
	}

	public function testFormatsShortRange(): void {
		self::assertSame('01.08.26 - 31.08.26', formatBankDateRangeShort('2026-08-01', '2026-08-31'));
	}

	public function testBreakdownLabelShowsCustomDates(): void {
		$dateRange = parseBankDateRange('custom', '2026-08-01', '2026-08-31');

		self::assertSame('01.08.26 - 31.08.26 by note', bankDateRangeNoteBreakdownLabel('custom', $dateRange));
		self::assertSame('This week by note', bankDateRangeNoteBreakdownLabel('week'));
		self::assertSame('All-time by note', bankDateRangeNoteBreakdownLabel('all'));
	}
}
