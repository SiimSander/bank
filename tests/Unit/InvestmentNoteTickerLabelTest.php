<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InvestmentNoteTickerLabelTest extends TestCase {
	public function testReturnsTextInsideTrailingBrackets(): void {
		self::assertSame('£WISE', investmentNoteTickerLabel('Wise (£WISE)'));
		self::assertSame('€VUAA', investmentNoteTickerLabel('Vanguard S&P 500 (€VUAA)'));
		self::assertSame('$MU', investmentNoteTickerLabel('Micron Technology ($MU)'));
	}

	public function testUsesLastBracketsWhenNameContainsBrackets(): void {
		self::assertSame('$ABC', investmentNoteTickerLabel('Acme (Holdings) ($ABC)'));
	}

	public function testReturnsFullNoteWithoutBrackets(): void {
		self::assertSame('Bitcoin', investmentNoteTickerLabel('Bitcoin'));
	}
}
