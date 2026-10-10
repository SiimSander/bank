<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class StockLogosTest extends DatabaseTestCase {
	private const VUAA = 'Vanguard S&P 500 (€VUAA)';
	private const WISE = 'Wise (£WISE)';
	private const ETH = 'Ethereum (€ETH)';

	private string $directory;

	protected function setUp(): void {
		parent::setUp();

		$this->directory = sys_get_temp_dir() . '/stock-logos-' . uniqid('', true);
		mkdir($this->directory);
		file_put_contents($this->directory . '/vuaa.png', 'png');
		file_put_contents($this->directory . '/wise.png', 'png');
	}

	protected function tearDown(): void {
		array_map('unlink', glob($this->directory . '/*') ?: []);
		rmdir($this->directory);

		parent::tearDown();
	}

	public function testResolverSuggestsTheTickerLogoUntilTheUserChoosesOtherwise(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: self::VUAA);

		self::assertSame('vuaa', stockLogoResolver($this->pdo, $accountId, $this->directory)(self::VUAA)['slug']);

		self::assertTrue(setStockLogo($this->pdo, $accountId, self::VUAA, 'wise', $this->directory));
		self::assertSame('wise', stockLogoResolver($this->pdo, $accountId, $this->directory)(self::VUAA)['slug']);

		self::assertTrue(setStockLogo($this->pdo, $accountId, self::VUAA, STOCK_LOGO_NONE, $this->directory));
		self::assertNull(stockLogoResolver($this->pdo, $accountId, $this->directory)(self::VUAA));
	}

	public function testChoiceIsStoredPerStockCaseInsensitively(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: self::VUAA);

		setStockLogo($this->pdo, $accountId, strtoupper(self::VUAA), 'wise', $this->directory);

		self::assertSame([stockGoalKey(self::VUAA) => 'wise'], getStoredStockLogos($this->pdo, $accountId));
	}

	public function testRejectsUnknownLogosAndUnknownStocks(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: self::VUAA);

		self::assertSame('Choose a logo from the list.', setStockLogo($this->pdo, $accountId, self::VUAA, '../secret', $this->directory));
		self::assertSame('Stock not found.', setStockLogo($this->pdo, $accountId, self::WISE, 'wise', $this->directory));
		self::assertSame([], getStoredStockLogos($this->pdo, $accountId));
	}

	public function testPlannedStockWithoutEntriesCanGetALogo(): void {
		$accountId = $this->createTestAccount();
		self::assertTrue(setStockGoal($this->pdo, $accountId, self::WISE, 100, true));

		self::assertTrue(setStockLogo($this->pdo, $accountId, self::WISE, STOCK_LOGO_NONE, $this->directory));
	}

	public function testNewStockWithAMatchingLogoStartsWithItsBrandColour(): void {
		$accountId = $this->createTestAccount();

		self::assertTrue(setStockGoal($this->pdo, $accountId, self::VUAA, 100, true, null, $this->directory));
		self::assertTrue(setStockGoal($this->pdo, $accountId, self::WISE, 100, true, STOCK_LOGO_NONE, $this->directory));

		$colors = getStoredStockColors($this->pdo, $accountId);

		self::assertSame('#9a0718', $colors[stockGoalKey(self::VUAA)]);
		self::assertContains($colors[stockGoalKey(self::WISE)], STOCK_GOAL_COLORS);
	}

	public function testBrandColourFollowsTheLogoPickedInTheForm(): void {
		$accountId = $this->createTestAccount();

		self::assertTrue(setStockGoal($this->pdo, $accountId, self::ETH, 100, true, 'wise', $this->directory));

		self::assertSame('#9fe870', getStoredStockColors($this->pdo, $accountId)[stockGoalKey(self::ETH)]);
	}

	public function testBrandColourCanStillBeChanged(): void {
		$accountId = $this->createTestAccount();
		setStockGoal($this->pdo, $accountId, self::VUAA, 100, true, null, $this->directory);

		self::assertTrue(setStockColor($this->pdo, $accountId, self::VUAA, '#112233'));
		self::assertSame('#112233', getStoredStockColors($this->pdo, $accountId)[stockGoalKey(self::VUAA)]);
	}

	public function testPortfolioUsesTheBrandColourForAStockWithoutAStoredColour(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 50, '2026-09-04', note: self::ETH);

		$items = array_column(getStockPortfolio($this->pdo, $accountId, $this->directory)['items'], null, 'note');

		self::assertSame('#9a0718', $items[self::VUAA]['color']);
		self::assertNull($items[self::ETH]['color']);
	}

	public function testLogosAreScopedToTheAccount(): void {
		$first = $this->createTestAccount();
		$this->seedTypes($first);
		$this->addEntry($first, 'investments', 100, '2026-09-03', note: self::VUAA);
		setStockLogo($this->pdo, $first, self::VUAA, STOCK_LOGO_NONE, $this->directory);

		$second = $this->createTestAccountWithPassword();

		self::assertSame([], getStoredStockLogos($this->pdo, $second));
	}

	public function testPortfolioSharesCountOnlyMoneyPutInAndSumToOneHundred(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 600, '2026-08-03', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 200, '2026-09-04', note: self::WISE);
		$this->addEntry($accountId, 'investments', 100, '2026-09-05', note: self::ETH);
		createBankEntry($this->pdo, $accountId, 'investments', 'card', -500, self::ETH, '2026-09-06');
		$this->addEntry($accountId, 'investments', 50, '2026-09-07');

		$portfolio = getStockPortfolio($this->pdo, $accountId, $this->directory);

		self::assertSame(1000.0, $portfolio['total']);
		self::assertSame([self::VUAA, self::WISE, self::ETH], array_column($portfolio['items'], 'note'));
		self::assertSame([70.0, 20.0, 10.0], array_column($portfolio['items'], 'share'));
		self::assertSame(100.0, array_sum(array_column($portfolio['items'], 'share')));
		self::assertStringStartsWith('/assets/img/stock-logos/vuaa.png', $portfolio['items'][0]['logo']);
		self::assertNull($portfolio['items'][2]['logo']);
	}

	public function testPortfolioIsEmptyWithoutInvestments(): void {
		$accountId = $this->createTestAccount();

		self::assertSame(['total' => 0.0, 'items' => []], getStockPortfolio($this->pdo, $accountId, $this->directory));
	}

	public function testExportAndDeleteCoverStoredLogos(): void {
		$accountId = $this->createTestAccountWithPassword();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-03', note: self::VUAA);
		setStockLogo($this->pdo, $accountId, self::VUAA, 'wise', $this->directory);

		$export = buildAccountExport($this->pdo, $accountId);

		self::assertSame('wise', $export['stock_goal_logos'][0]['logo_slug']);
	}
}
