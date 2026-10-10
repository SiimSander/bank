<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StockTickersTest extends TestCase {
	public function testNormalizingIgnoresCaseSpacesAndDashStyle(): void {
		self::assertSame('vanguard s&p 500', normalizeStockName('  Vanguard   S&P 500 '));
		self::assertSame(
			'vanguard global aggregate bond-eur hedged',
			normalizeStockName('Vanguard Global Aggregate Bond – EUR Hedged')
		);
		self::assertSame(
			normalizeStockName('vanguard global aggregate bond - eur hedged'),
			normalizeStockName('Vanguard Global Aggregate Bond-EUR Hedged')
		);
	}

	public function testLookupIsCaseInsensitiveAndExact(): void {
		self::assertSame('VUAA', lookupStockTicker('Vanguard S&P 500'));
		self::assertSame('VWCE', lookupStockTicker('VANGUARD FTSE ALL-WORLD'));
		self::assertSame('VHYL', lookupStockTicker('vanguard ftse all-world high dividend yield'));
		self::assertSame('EMIM', lookupStockTicker('iShares Core MSCI Emerging Markets'));
		self::assertSame('EIMI', lookupStockTicker('iShares Core MSCI Emerging Markets IMI'));
		self::assertSame('IVZ', lookupStockTicker('Invesco'));
		self::assertNull(lookupStockTicker('Vanguard'));
		self::assertNull(lookupStockTicker('Vanguard FTSE All'));
		self::assertNull(lookupStockTicker('Unknown fund'));
	}

	public function testListedNamesFromTheSpec(): void {
		self::assertSame('GOOGL', lookupStockTicker('Alphabet Class A'));
		self::assertSame('GOOG', lookupStockTicker('Alphabet Class C'));
		self::assertSame('IEAC', lookupStockTicker('iShares Core Euro Corporate Bond'));
		self::assertSame('WSML', lookupStockTicker('iShares MSCI World Small Cap'));
		self::assertSame('INF1T', lookupStockTicker('Infortar AS'));
		self::assertSame('MU', lookupStockTicker('Micron Technology'));
		self::assertNull(lookupStockTicker('iShares MSCI World Smapp Cap'));
	}

	public function testTickersAreUpperCaseAndEveryNameIsUnique(): void {
		$normalized = array_map('normalizeStockName', array_keys(STOCK_NAME_TICKERS));

		self::assertCount(count($normalized), array_unique($normalized));

		foreach (STOCK_NAME_TICKERS as $ticker) {
			self::assertMatchesRegularExpression('/^[A-Z0-9]+$/', $ticker);
		}
	}

	public function testEveryListedTickerHasALogoAndAColour(): void {
		$catalog = getStockLogoCatalog();
		$tickers = array_unique(array_values(STOCK_NAME_TICKERS));

		foreach ($tickers as $ticker) {
			$slug = suggestedStockLogoSlug("Name (€{$ticker})", $catalog);

			self::assertNotNull($slug, "{$ticker} has no logo");
			self::assertNotNull(stockLogoColor($slug), "{$ticker} has a logo without a colour");
		}
	}

	public function testNameLogoTickersAreLowerCase(): void {
		self::assertSame('vwce', getStockNameLogoTickers()['vanguard ftse all-world']);
	}
}
