<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SplitInvestmentNoteTest extends TestCase {
	public function testSplitsNameFromTrailingBrackets(): void {
		self::assertSame(['name' => 'Wise', 'ticker' => '(£WISE)'], splitInvestmentNote('Wise (£WISE)'));
		self::assertSame(['name' => 'Vanguard S&P 500', 'ticker' => '(€VUAA)'], splitInvestmentNote('Vanguard S&P 500 (€VUAA)'));
	}

	public function testKeepsEarlierBracketsInName(): void {
		self::assertSame(['name' => 'Acme (Holdings)', 'ticker' => '($ABC)'], splitInvestmentNote('Acme (Holdings) ($ABC)'));
	}

	public function testReturnsWholeNoteWhenNoBrackets(): void {
		self::assertSame(['name' => 'Bitcoin', 'ticker' => ''], splitInvestmentNote('Bitcoin'));
		self::assertSame(['name' => 'Other', 'ticker' => ''], splitInvestmentNote('Other'));
	}

	public function testNoteThatIsOnlyBracketsIsNotSplit(): void {
		self::assertSame(['name' => '(€ETH)', 'ticker' => ''], splitInvestmentNote('(€ETH)'));
	}
}
