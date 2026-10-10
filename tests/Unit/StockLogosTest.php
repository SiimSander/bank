<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StockLogosTest extends TestCase {
	private string $directory;

	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/stock-logos-' . uniqid('', true);
		mkdir($this->directory);
		file_put_contents($this->directory . '/vuaa.png', 'png');
		file_put_contents($this->directory . '/BTC.svg', '<svg/>');
		file_put_contents($this->directory . '/not a logo.png', 'png');
		file_put_contents($this->directory . '/notes.txt', 'text');
	}

	protected function tearDown(): void {
		array_map('unlink', glob($this->directory . '/*') ?: []);
		rmdir($this->directory);
	}

	public function testCatalogListsImageFilesWithSafeNamesOnly(): void {
		$catalog = getStockLogoCatalog($this->directory);

		self::assertSame(['btc', 'vuaa'], array_keys($catalog));
		self::assertSame('VUAA', $catalog['vuaa']['label']);
		self::assertStringStartsWith('/assets/img/stock-logos/vuaa.png?v=', $catalog['vuaa']['url']);
		self::assertStringStartsWith('/assets/img/stock-logos/BTC.svg?v=', $catalog['btc']['url']);
	}

	public function testSlugComesFromTheTickerOfTheNote(): void {
		self::assertSame('vuaa', stockLogoSlugForNote('Vanguard S&P 500 (€VUAA)'));
		self::assertSame('wise', stockLogoSlugForNote('Wise (£WISE)'));
		self::assertSame('mu', stockLogoSlugForNote('Micron Technology ($MU)'));
		self::assertSame('brk-b', stockLogoSlugForNote('Berkshire ($BRK.B)'));
		self::assertSame('tsla', stockLogoSlugForNote('Tesla (€TSLA)'));
		self::assertSame('tsla', stockLogoSlugForNote('Tesla ($TSLA)'));
		self::assertSame('tsla', stockLogoSlugForNote('Tesla (TslA)'));
		self::assertSame('tsla', stockLogoSlugForNote('Tesla (£tsla)'));
		self::assertSame('', stockLogoSlugForNote('Plain note'));
		self::assertSame('', stockLogoSlugForNote('(€VUAA)'));
	}

	public function testNewStockGetsTheBrandColourOfItsLogo(): void {
		file_put_contents($this->directory . '/mu.png', 'png');

		self::assertSame('#9a0718', stockLogoColorForNewStock('Vanguard (vuaa)', null, $this->directory));
		self::assertSame('#0077c8', stockLogoColorForNewStock('Micron ($MU)', '', $this->directory));
		self::assertSame('#9a0718', stockLogoColorForNewStock('Micron ($MU)', 'vuaa', $this->directory));
	}

	public function testNoBrandColourWithoutALogoFileOrWhenNoLogoIsChosen(): void {
		self::assertNull(stockLogoColorForNewStock('Wise (£WISE)', null, $this->directory));
		self::assertNull(stockLogoColorForNewStock('Vanguard (€VUAA)', STOCK_LOGO_NONE, $this->directory));
		self::assertNull(stockLogoColorForNewStock('Ethereum (€ETH)', null, $this->directory));
		self::assertNull(stockLogoColorForNewStock('Plain note', null, $this->directory));
	}

	public function testAliasedTickerUsesTheSameLogoAndColour(): void {
		$catalog = getStockLogoCatalog($this->directory);

		self::assertSame('vuaa', resolveStockLogoSlug('Vanguard FTSE All-World (€VWCE)', [], $catalog));
		self::assertSame('vuaa', resolveStockLogoSlug('Vanguard FTSE All-World ($vwce)', [], $catalog));
		self::assertSame('#9a0718', stockLogoColorForNewStock('Vanguard FTSE All-World (€VWCE)', null, $this->directory));
		self::assertNull(stockLogoColorForNewStock('Vanguard FTSE All-World (€VWCE)', STOCK_LOGO_NONE, $this->directory));
	}

	public function testLogoFileNamedAfterTheTickerBeatsTheAlias(): void {
		file_put_contents($this->directory . '/vwce.png', 'png');

		self::assertSame('vwce', resolveStockLogoSlug('Vanguard FTSE All-World (€VWCE)', [], getStockLogoCatalog($this->directory)));
	}

	public function testAliasIsIgnoredWhenItsLogoFileIsMissing(): void {
		unlink($this->directory . '/vuaa.png');

		self::assertNull(resolveStockLogoSlug('Vanguard FTSE All-World (€VWCE)', [], getStockLogoCatalog($this->directory)));
	}

	public function testStoredChoiceWinsOverTheTickerMatch(): void {
		$catalog = getStockLogoCatalog($this->directory);
		$note = 'Vanguard S&P 500 (€VUAA)';

		self::assertSame('vuaa', resolveStockLogoSlug($note, [], $catalog));
		self::assertSame('btc', resolveStockLogoSlug($note, [stockGoalKey($note) => 'btc'], $catalog));
	}

	public function testNoneMeansNoLogoEvenWhenTheTickerMatches(): void {
		$catalog = getStockLogoCatalog($this->directory);
		$note = 'Vanguard S&P 500 (€VUAA)';

		self::assertNull(resolveStockLogoSlug($note, [stockGoalKey($note) => STOCK_LOGO_NONE], $catalog));
	}

	public function testStoredLogoThatNoLongerExistsFallsBackToTheTickerMatch(): void {
		$catalog = getStockLogoCatalog($this->directory);
		$note = 'Vanguard S&P 500 (€VUAA)';

		self::assertSame('vuaa', resolveStockLogoSlug($note, [stockGoalKey($note) => 'removed'], $catalog));
	}

	public function testStockWithoutMatchingLogoHasNone(): void {
		$catalog = getStockLogoCatalog($this->directory);

		self::assertNull(resolveStockLogoSlug('Unknown (€ZZZZ)', [], $catalog));
		self::assertNull(resolveStockLogoSlug('No ticker', [], $catalog));
	}

	public function testImageTagEscapesTheUrlAndSkipsMissingLogos(): void {
		self::assertSame('', stockLogoImage(null));
		self::assertSame('', stockLogoImage(''));
		self::assertStringContainsString('src="/a.png?v=1&quot;&gt;"', stockLogoImage('/a.png?v=1">'));
		self::assertStringContainsString('class="stock-logo stock-logo--action"', stockLogoImage('/a.png', 'stock-logo--action'));
	}
}
