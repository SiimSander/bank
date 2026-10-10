<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class StockGoalsTest extends DatabaseTestCase {
	private const VUAA = 'Vanguard S&P 500 (€VUAA)';
	private const WISE = 'Wise (£WISE)';

	public function testGoalAppliesFromItsEffectiveMonthOn(): void {
		$accountId = $this->createTestAccount();
		$this->insertGoal($accountId, self::VUAA, 800, '2026-08-01');
		$this->insertGoal($accountId, self::VUAA, 1000, '2026-10-01');

		$history = getStockGoalHistory($this->pdo, $accountId)[stockGoalKey(self::VUAA)]['history'];

		self::assertSame(0.0, getStockGoalAmountForMonth($history, '2026-07-01'));
		self::assertSame(800.0, getStockGoalAmountForMonth($history, '2026-08-01'));
		self::assertSame(800.0, getStockGoalAmountForMonth($history, '2026-09-01'));
		self::assertSame(1000.0, getStockGoalAmountForMonth($history, '2026-10-01'));
	}

	public function testZeroGoalStopsLaterMonthsButKeepsEarlierOnes(): void {
		$accountId = $this->createTestAccount();
		$this->insertGoal($accountId, self::VUAA, 800, '2026-07-01');
		$this->insertGoal($accountId, self::VUAA, 0, '2026-09-01');

		$history = getStockGoalHistory($this->pdo, $accountId)[stockGoalKey(self::VUAA)]['history'];

		self::assertSame(800.0, getStockGoalAmountForMonth($history, '2026-08-01'));
		self::assertSame(0.0, getStockGoalAmountForMonth($history, '2026-09-01'));
	}

	public function testRowsCountAdditionsOnlyAndCalculateLeftOverAndPercent(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 800, '2026-09-01');
		$this->insertGoal($accountId, self::WISE, 400, '2026-09-01');

		$this->addEntry($accountId, 'investments', 500, '2026-09-03', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 10.01, '2026-09-10', note: self::VUAA);
		createBankEntry($this->pdo, $accountId, 'investments', 'card', -200, self::VUAA, '2026-09-12');
		$this->addEntry($accountId, 'investments', 450, '2026-09-04', note: self::WISE);

		$rows = $this->rowsByNote(getStockGoalRows($this->pdo, $accountId, '2026-09-01'));

		self::assertSame(510.01, $rows[self::VUAA]['invested']);
		self::assertSame(289.99, $rows[self::VUAA]['left']);
		self::assertSame(0.0, $rows[self::VUAA]['over']);
		self::assertSame(63.8, $rows[self::VUAA]['percent']);
		self::assertSame('partial', $rows[self::VUAA]['status']);

		self::assertSame(450.0, $rows[self::WISE]['invested']);
		self::assertSame(50.0, $rows[self::WISE]['over']);
		self::assertSame(0.0, $rows[self::WISE]['left']);
		self::assertSame(112.5, $rows[self::WISE]['percent']);
		self::assertSame('met', $rows[self::WISE]['status']);
	}

	public function testPastMonthUsesGoalThatWasActiveThen(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 800, '2026-08-01');
		$this->insertGoal($accountId, self::VUAA, 1000, '2026-09-01');
		$this->addEntry($accountId, 'investments', 700, '2026-08-05', note: self::VUAA);

		$rows = $this->rowsByNote(getStockGoalRows($this->pdo, $accountId, '2026-08-01'));

		self::assertSame(800.0, $rows[self::VUAA]['goal']);
		self::assertSame(100.0, $rows[self::VUAA]['left']);
	}

	public function testCurrentMonthListsStocksWithoutGoalAfterGoalStocks(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::WISE, 400, '2026-09-01');
		$this->addEntry($accountId, 'investments', 240, '2026-09-02', note: 'Ethereum (€ETH)');
		$this->addEntry($accountId, 'investments', 100, '2026-08-02', note: self::VUAA);

		$rows = getStockGoalRows($this->pdo, $accountId, '2026-09-01');

		self::assertSame([self::WISE, 'Ethereum (€ETH)', self::VUAA], array_column($rows, 'note'));
		self::assertTrue($rows[0]['has_goal']);
		self::assertFalse($rows[1]['has_goal']);
		self::assertSame('none', $rows[1]['status']);
		self::assertNull($rows[1]['percent']);
	}

	public function testGoalStocksAreOrderedFromMostInvestedToLeastThenBiggestGoal(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::WISE, 400, '2026-09-01');
		$this->insertGoal($accountId, 'Bitcoin (€BTC)', 200, '2026-09-01');
		$this->insertGoal($accountId, self::VUAA, 800, '2026-09-01');
		$this->insertGoal($accountId, 'Apple (€AAPL)', 300, '2026-09-01');
		$this->insertGoal($accountId, 'Zeta (€ZETA)', 100, '2026-09-01');
		$this->addEntry($accountId, 'investments', 400, '2026-09-02', note: self::WISE);
		$this->addEntry($accountId, 'investments', 150, '2026-09-03', note: self::VUAA);

		$rows = getStockGoalRows($this->pdo, $accountId, '2026-09-01');

		self::assertSame(
			[self::WISE, self::VUAA, 'Apple (€AAPL)', 'Bitcoin (€BTC)', 'Zeta (€ZETA)'],
			array_column($rows, 'note')
		);
	}

	public function testSettlementReportsWhatWasMissingBeforeTheMonthCoveredIt(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-08-01');
		$this->addEntry($accountId, 'investments', 25, '2026-08-10', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 150, '2026-09-10', note: self::VUAA);

		$settlement = getStockGoalSettlementForMonth(
			settleStockGoalMonths(
				getStockGoalHistory($this->pdo, $accountId)[stockGoalKey(self::VUAA)]['history'],
				['2026-08-01' => 25.0, '2026-09-01' => 150.0],
				'2026-09-01',
				true
			),
			'2026-09-01'
		);

		self::assertSame(75.0, $settlement['open_before']);
		self::assertSame(50.0, $settlement['covers_total']);
	}

	public function testRingSectorsFollowTheMostInvestedOrder(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 800, '2026-09-01');
		$this->insertGoal($accountId, self::WISE, 400, '2026-09-01');
		$this->addEntry($accountId, 'investments', 100, '2026-09-02', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 300, '2026-09-03', note: self::WISE);

		$sectors = getStockRingSectors(getStockGoalRows($this->pdo, $accountId, '2026-09-01'));

		self::assertSame([stockGoalKey(self::WISE), stockGoalKey(self::VUAA)], array_column($sectors, 'key'));
	}

	public function testMissedAmountFromLastMonthIsCaughtUpByExtraThisMonth(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-08-01');
		$this->addEntry($accountId, 'investments', 80, '2026-08-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 120, '2026-09-05', note: self::VUAA);

		$row = getStockGoalRows($this->pdo, $accountId, '2026-09-01')[0];

		self::assertSame(100.0, $row['goal']);
		self::assertSame(120.0, $row['target']);
		self::assertSame(20.0, $row['carry_missed']);
		self::assertSame(0.0, $row['catch_up_left']);
		self::assertSame(0.0, $row['left']);
		self::assertSame(20.0, $row['over']);
		self::assertSame('met', $row['status']);
		self::assertSame([['month' => '2026-08-01', 'amount' => 20.0]], $row['carry_months']);
	}

	public function testExtraFromAnEarlierMonthDoesNotLowerTheNextMonthsGoal(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-08-01');
		$this->addEntry($accountId, 'investments', 80, '2026-08-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 125, '2026-09-05', note: self::VUAA);

		$september = getStockGoalRows($this->pdo, $accountId, '2026-09-01')[0];
		$october = getStockGoalRows($this->pdo, $accountId, '2026-10-01')[0];

		self::assertSame(25.0, $september['over']);
		self::assertSame(0.0, $october['carry_missed']);
		self::assertSame(100.0, $october['goal']);
		self::assertSame(100.0, $october['left']);
	}

	public function testExtraFromAnEarlierMonthCoversALaterShortfall(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-07-01');
		$this->addEntry($accountId, 'investments', 150, '2026-07-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 70, '2026-08-05', note: self::VUAA);

		$september = getStockGoalRows($this->pdo, $accountId, '2026-09-01')[0];

		self::assertSame(0.0, $september['carry_missed']);
		self::assertSame(100.0, $september['goal']);
	}

	public function testPartialInvestmentKeepsTheGoalAndLeavesTheCatchUpOpen(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-08-01');
		$this->addEntry($accountId, 'investments', 80, '2026-08-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 60, '2026-09-05', note: self::VUAA);

		$row = getStockGoalRows($this->pdo, $accountId, '2026-09-01')[0];

		self::assertSame(100.0, $row['goal']);
		self::assertSame(120.0, $row['target']);
		self::assertSame(40.0, $row['left']);
		self::assertSame(20.0, $row['catch_up_left']);
		self::assertSame('partial', $row['status']);
		self::assertSame(60.0, $row['percent']);
	}

	public function testMonthViewIgnoresLaterMonthsWhenCalculatingCarry(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-07-01');
		$this->addEntry($accountId, 'investments', 80, '2026-07-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 200, '2026-09-05', note: self::VUAA);

		$august = getStockGoalRows($this->pdo, $accountId, '2026-08-01')[0];

		self::assertSame(20.0, $august['carry_missed']);
	}

	public function testSummaryReportsOnlyTheOpenCatchUpAndWhichStocksAreBehind(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-08-01');
		$this->insertGoal($accountId, self::WISE, 100, '2026-08-01');
		$this->addEntry($accountId, 'investments', 80, '2026-08-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 70, '2026-08-05', note: self::WISE);
		$this->addEntry($accountId, 'investments', 110, '2026-09-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 40, '2026-09-05', note: self::WISE);

		$summary = summarizeStockGoalRows(getStockGoalRows($this->pdo, $accountId, '2026-09-01'));

		self::assertSame(40.0, $summary['catch_up_left']);
		self::assertSame(
			[
				['name' => 'Vanguard S&P 500', 'ticker' => '(€VUAA)', 'amount' => 10.0, 'aim' => 120.0],
				['name' => 'Wise', 'ticker' => '(£WISE)', 'amount' => 30.0, 'aim' => 130.0],
			],
			$summary['behind']
		);
		self::assertSame(200.0, $summary['planned']);
	}

	public function testLaterExtraCoversEarlierMissedMonthsOldestFirst(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-06-01');
		$this->addEntry($accountId, 'investments', 65, '2026-06-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 35, '2026-07-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 140, '2026-08-05', note: self::VUAA);

		$june = getStockGoalRows($this->pdo, $accountId, '2026-06-01')[0]['settlement'];
		$july = getStockGoalRows($this->pdo, $accountId, '2026-07-01')[0]['settlement'];
		$august = getStockGoalRows($this->pdo, $accountId, '2026-08-01')[0]['settlement'];

		self::assertSame(35.0, $june['shortfall']);
		self::assertSame(35.0, $june['covered']);
		self::assertSame(0.0, $june['still_missing']);
		self::assertSame([['month' => '2026-08-01', 'amount' => 35.0, 'extra' => false]], $june['covered_by']);

		self::assertSame(65.0, $july['shortfall']);
		self::assertSame(5.0, $july['covered']);
		self::assertSame(60.0, $july['still_missing']);
		self::assertSame([['month' => '2026-08-01', 'amount' => 5.0, 'extra' => false]], $july['covered_by']);

		self::assertSame(40.0, $august['covers_total']);
		self::assertSame(40.0, $august['surplus']);
		self::assertSame(
			[
				['month' => '2026-06-01', 'amount' => 35.0, 'goal' => 100.0, 'shortfall' => 35.0],
				['month' => '2026-07-01', 'amount' => 5.0, 'goal' => 100.0, 'shortfall' => 65.0],
			],
			$august['covers']
		);
	}

	public function testExtraSmallerThanTheOldestMissLeavesLaterMissesUncovered(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-06-01');
		$this->addEntry($accountId, 'investments', 65, '2026-06-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 35, '2026-07-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 125, '2026-08-05', note: self::VUAA);

		$june = getStockGoalRows($this->pdo, $accountId, '2026-06-01')[0]['settlement'];
		$july = getStockGoalRows($this->pdo, $accountId, '2026-07-01')[0]['settlement'];
		$september = getStockGoalRows($this->pdo, $accountId, '2026-09-01')[0];

		self::assertSame(25.0, $june['covered']);
		self::assertSame(10.0, $june['still_missing']);
		self::assertSame(0.0, $july['covered']);
		self::assertSame([], $july['covered_by']);
		self::assertSame(65.0, $july['still_missing']);
		self::assertSame(75.0, $september['carry_missed']);
	}

	public function testExtraInTheOpenMonthCoversEarlierMissesWhileItIsStillRunning(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-07-01');
		$this->addEntry($accountId, 'investments', 70, '2026-07-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 100, '2026-08-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 120, '2026-09-05', note: self::VUAA);

		$july = getStockGoalRows($this->pdo, $accountId, '2026-07-01')[0]['settlement'];
		$september = getStockGoalRows($this->pdo, $accountId, '2026-09-01')[0];

		self::assertSame(20.0, $july['covered']);
		self::assertSame(10.0, $july['still_missing']);
		self::assertSame(20.0, $september['settlement']['covers_total']);
		self::assertSame(0.0, $september['settlement']['shortfall']);
		self::assertSame(0.0, $september['left']);
		self::assertSame(10.0, $september['catch_up_left']);
	}

	public function testEarlierExtraCoveringALaterMissIsRecordedOnBothMonths(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-06-01');
		$this->addEntry($accountId, 'investments', 150, '2026-06-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 70, '2026-07-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 100, '2026-08-05', note: self::VUAA);

		$june = getStockGoalRows($this->pdo, $accountId, '2026-06-01')[0]['settlement'];
		$july = getStockGoalRows($this->pdo, $accountId, '2026-07-01')[0]['settlement'];

		self::assertSame(50.0, $june['surplus']);
		self::assertSame([['month' => '2026-07-01', 'amount' => 30.0, 'goal' => 100.0, 'shortfall' => 30.0]], $june['covers']);
		self::assertSame([['month' => '2026-06-01', 'amount' => 30.0, 'extra' => true]], $july['covered_by']);
		self::assertSame(0.0, $july['still_missing']);
	}

	public function testDroppingTheGoalForgetsMissesThatWereNeverCovered(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-06-01');
		$this->insertGoal($accountId, self::VUAA, 0, '2026-07-01');
		$this->insertGoal($accountId, self::VUAA, 100, '2026-08-01');
		$this->addEntry($accountId, 'investments', 70, '2026-06-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 160, '2026-08-05', note: self::VUAA);

		$june = getStockGoalRows($this->pdo, $accountId, '2026-06-01')[0]['settlement'];

		self::assertSame(0.0, $june['covered']);
		self::assertSame(0.0, $june['still_missing']);
	}

	public function testPastMonthHidesStocksWithNeitherGoalNorInvestment(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-02', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 50, '2026-08-02', note: 'Bitcoin (€BTC)');

		$rows = getStockGoalRows($this->pdo, $accountId, '2026-08-01');

		self::assertSame(['Bitcoin (€BTC)'], array_column($rows, 'note'));
	}

	public function testSummaryTotalsOnlyCoverStocksWithGoals(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 800, '2026-09-01');
		$this->insertGoal($accountId, self::WISE, 400, '2026-09-01');
		$this->addEntry($accountId, 'investments', 510, '2026-09-03', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 450, '2026-09-04', note: self::WISE);
		$this->addEntry($accountId, 'investments', 999, '2026-09-04', note: 'Ethereum (€ETH)');

		$summary = summarizeStockGoalRows(getStockGoalRows($this->pdo, $accountId, '2026-09-01'));

		self::assertSame(1200.0, $summary['planned']);
		self::assertSame(960.0, $summary['invested']);
		self::assertSame(290.0, $summary['left']);
		self::assertSame(50.0, $summary['over']);
		self::assertSame(80.0, $summary['percent']);
		self::assertSame(2, $summary['goal_count']);
		self::assertSame(1, $summary['met_count']);
	}

	public function testChartSeriesStartAtEachStocksFirstGoalMonth(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 800, '2026-07-01');
		$this->insertGoal($accountId, self::WISE, 400, '2026-08-01');
		$this->insertGoal($accountId, self::WISE, 0, '2026-09-01');
		$this->addEntry($accountId, 'investments', 400, '2026-07-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 200, '2026-08-05', note: self::WISE);

		$chart = getStockGoalChartSeries($this->pdo, $accountId);

		self::assertSame(['2026-07-01', '2026-08-01', '2026-09-01'], $chart['months']);

		$byNote = array_column($chart['series'], null, 'note');
		self::assertSame(50.0, $byNote[self::VUAA]['points'][0]['percent']);
		self::assertNull($byNote[self::WISE]['points'][0]['percent']);
		self::assertSame(50.0, $byNote[self::WISE]['points'][1]['percent']);
		self::assertNull($byNote[self::WISE]['points'][2]['percent']);
	}

	public function testRingSectorsAreSizedByShareOfThePlannedTotal(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 300, '2026-09-01');
		$this->insertGoal($accountId, self::WISE, 100, '2026-09-01');
		$this->addEntry($accountId, 'investments', 150, '2026-09-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 50, '2026-09-06', note: self::WISE);

		$sectors = getStockRingSectors(getStockGoalRows($this->pdo, $accountId, '2026-09-01'));
		$trim = STOCK_RING_GAP_DEGREES / 2;

		self::assertCount(2, $sectors);
		self::assertEqualsWithDelta($trim, $sectors[0]['start'], 0.001);
		self::assertEqualsWithDelta(135 - $trim, $sectors[0]['end'], 0.001);
		self::assertEqualsWithDelta(135 + $trim, $sectors[1]['start'], 0.001);
		self::assertEqualsWithDelta(180 - $trim, $sectors[1]['end'], 0.001);
		self::assertEqualsWithDelta(($sectors[0]['start'] + $sectors[0]['end']) / 2, $sectors[0]['middle'], 0.001);
	}

	public function testRingHasNoSectorForAStockWithNothingInvested(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 300, '2026-09-01');
		$this->insertGoal($accountId, self::WISE, 100, '2026-09-01');
		$this->addEntry($accountId, 'investments', 150, '2026-09-05', note: self::VUAA);

		$sectors = getStockRingSectors(getStockGoalRows($this->pdo, $accountId, '2026-09-01'));

		self::assertCount(1, $sectors);
		self::assertSame([], getStockRingSectors(getStockGoalRows($this->pdo, $this->createTestAccount(), '2026-09-01')));
	}

	public function testRingSectorReportsOwnPercentAndShareOfTheOverallPercent(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 300, '2026-09-01');
		$this->insertGoal($accountId, self::WISE, 100, '2026-09-01');
		$this->addEntry($accountId, 'investments', 150, '2026-09-05', note: self::VUAA);

		$sector = getStockRingSectors(getStockGoalRows($this->pdo, $accountId, '2026-09-01'))[0];

		self::assertSame(50.0, $sector['percent']);
		self::assertSame(37.5, $sector['contribution']);
		self::assertSame(37.5, $sector['overall_percent']);
	}

	public function testRingIsFullWhenTheWholePlanIsInvested(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 300, '2026-09-01');
		$this->addEntry($accountId, 'investments', 300, '2026-09-05', note: self::VUAA);

		$sectors = getStockRingSectors(getStockGoalRows($this->pdo, $accountId, '2026-09-01'));

		self::assertCount(1, $sectors);
		self::assertEqualsWithDelta(359.99, $sectors[0]['end'] - $sectors[0]['start'], 0.001);
	}

	public function testRingIgnoresStocksWithoutGoalAndNeverExceedsTheFullCircle(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 100, '2026-09-01');
		$this->addEntry($accountId, 'investments', 250, '2026-09-05', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 50, '2026-09-06', note: self::WISE);

		$sectors = getStockRingSectors(getStockGoalRows($this->pdo, $accountId, '2026-09-01'));

		self::assertCount(1, $sectors);
		self::assertEqualsWithDelta(359.99, $sectors[0]['end'], 0.001);
		self::assertSame(250.0, $sectors[0]['percent']);
		self::assertSame([], getStockRingSectors([]));
	}

	public function testRingArcPathStartsAtTheTopAndFlagsLargeArcs(): void {
		self::assertSame('M 100 20 A 80 80 0 0 1 180 100', getStockRingArcPath(100, 100, 80, 0, 90));
		self::assertStringContainsString(' 0 1 1 ', getStockRingArcPath(100, 100, 80, 0, 200));
	}

	public function testChartPointsListEachEntryDayAndAmountOldestFirst(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->insertGoal($accountId, self::VUAA, 300, '2026-08-01');
		$this->addEntry($accountId, 'investments', 80.5, '2026-09-12', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 120, '2026-09-03', note: self::VUAA);
		$this->addEntry($accountId, 'investments', -50, '2026-09-04', note: self::VUAA);
		$this->addEntry($accountId, 'investments', 40, '2026-09-05', note: self::WISE);

		$points = getStockGoalChartSeries($this->pdo, $accountId)['series'][0]['points'];

		self::assertSame([], $points[0]['entries']);
		self::assertSame(
			[['date' => '2026-09-03', 'amount' => 120.0], ['date' => '2026-09-12', 'amount' => 80.5]],
			$points[1]['entries']
		);
	}

	public function testChartSeriesAreOrderedFromBiggestGoalToLowest(): void {
		$accountId = $this->createTestAccount();
		$this->insertGoal($accountId, self::WISE, 400, '2026-08-01');
		$this->insertGoal($accountId, self::VUAA, 800, '2026-08-01');
		$this->insertGoal($accountId, 'Ethereum (€ETH)', 500, '2026-08-01');
		$this->insertGoal($accountId, 'Bitcoin (€BTC)', 200, '2026-08-01');
		$this->insertGoal($accountId, 'Bitcoin (€BTC)', 0, '2026-09-01');

		$notes = array_column(getStockGoalChartSeries($this->pdo, $accountId)['series'], 'note');

		self::assertSame([self::VUAA, 'Ethereum (€ETH)', self::WISE, 'Bitcoin (€BTC)'], $notes);
	}

	public function testCardAndChartLineShareTheSameColorPerStock(): void {
		$accountId = $this->createTestAccount();
		$this->insertGoal($accountId, self::WISE, 400, '2026-08-01');
		$this->insertGoal($accountId, self::VUAA, 800, '2026-07-01');
		$this->insertGoal($accountId, 'Ethereum (€ETH)', 500, '2026-09-01');

		$rowColors = array_column(getStockGoalRows($this->pdo, $accountId, '2026-09-01'), 'color', 'note');
		$seriesColors = array_column(getStockGoalChartSeries($this->pdo, $accountId)['series'], 'color', 'note');

		ksort($rowColors);
		ksort($seriesColors);

		self::assertSame($rowColors, $seriesColors);
		self::assertSame(STOCK_GOAL_COLORS[0], $rowColors[self::VUAA]);
		self::assertSame(STOCK_GOAL_COLORS[1], $rowColors[self::WISE]);
		self::assertSame(STOCK_GOAL_COLORS[2], $rowColors['Ethereum (€ETH)']);
	}

	public function testStockWithoutAnyGoalHasNoColor(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-02', note: self::VUAA);

		$rows = getStockGoalRows($this->pdo, $accountId, '2026-09-01');

		self::assertNull($rows[0]['color']);
	}

	public function testChartIsEmptyWithoutGoals(): void {
		$accountId = $this->createTestAccount();

		self::assertSame(['months' => [], 'series' => []], getStockGoalChartSeries($this->pdo, $accountId));
	}

	public function testChosenColorOverridesTheDefaultOnCardAndChart(): void {
		$accountId = $this->createTestAccount();
		$this->insertGoal($accountId, self::VUAA, 800, '2026-08-01');

		self::assertTrue(setStockColor($this->pdo, $accountId, self::VUAA, '#AABBCC'));

		$rows = getStockGoalRows($this->pdo, $accountId, '2026-09-01');
		$series = getStockGoalChartSeries($this->pdo, $accountId)['series'];

		self::assertSame('#aabbcc', $rows[0]['color']);
		self::assertSame('#aabbcc', $series[0]['color']);
	}

	public function testColorCanBeChosenForAStockWithoutAGoal(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-09-02', note: self::VUAA);

		self::assertTrue(setStockColor($this->pdo, $accountId, self::VUAA, '#112233'));
		self::assertSame('#112233', getStockGoalRows($this->pdo, $accountId, '2026-09-01')[0]['color']);
	}

	public function testChangingColorTwiceKeepsTheLatest(): void {
		$accountId = $this->createTestAccount();
		$this->insertGoal($accountId, self::VUAA, 800, '2026-08-01');

		setStockColor($this->pdo, $accountId, self::VUAA, '#111111');
		setStockColor($this->pdo, $accountId, self::VUAA, '#222222');

		self::assertSame([stockGoalKey(self::VUAA) => '#222222'], getStoredStockColors($this->pdo, $accountId));
	}

	public function testSetStockColorRejectsInvalidInput(): void {
		$accountId = $this->createTestAccount();
		$this->insertGoal($accountId, self::VUAA, 800, '2026-08-01');

		self::assertIsString(setStockColor($this->pdo, $accountId, self::VUAA, 'red'));
		self::assertIsString(setStockColor($this->pdo, $accountId, self::VUAA, '#12345'));
		self::assertIsString(setStockColor($this->pdo, $accountId, 'Unknown stock', '#123456'));
		self::assertSame([], getStoredStockColors($this->pdo, $accountId));
	}

	public function testColorsAreIsolatedPerAccount(): void {
		$first = $this->createTestAccount();
		$second = $this->createTestAccount();
		$this->insertGoal($first, self::VUAA, 800, '2026-08-01');
		$this->insertGoal($second, self::VUAA, 800, '2026-08-01');

		setStockColor($this->pdo, $first, self::VUAA, '#123456');

		self::assertSame(STOCK_GOAL_COLORS[0], getStockGoalRows($this->pdo, $second, '2026-09-01')[0]['color']);
	}

	public function testNewStockGetsAColorNoOtherStockUses(): void {
		$accountId = $this->createTestAccount();
		$this->insertGoal($accountId, self::VUAA, 800, '2026-08-01');
		$this->insertGoal($accountId, self::WISE, 400, '2026-08-01');

		self::assertTrue(setStockGoal($this->pdo, $accountId, 'Bitcoin (€BTC)', 200));

		$colors = array_column(getStockGoalRows($this->pdo, $accountId, '2026-09-01'), 'color', 'note');

		self::assertCount(3, array_unique($colors));
		self::assertTrue(isValidStockColor($colors['Bitcoin (€BTC)']));
		self::assertContains($colors['Bitcoin (€BTC)'], STOCK_GOAL_COLORS);
	}

	public function testAddingAStockDoesNotChangeTheColorsOfExistingStocks(): void {
		$accountId = $this->createTestAccount();
		$this->insertGoal($accountId, self::WISE, 400, '2026-09-01');
		$this->insertGoal($accountId, self::VUAA, 800, '2026-09-01');
		$before = array_column(getStockGoalRows($this->pdo, $accountId, '2026-09-01'), 'color', 'note');

		setStockGoal($this->pdo, $accountId, 'Apple (€AAPL)', 100);

		$after = array_column(getStockGoalRows($this->pdo, $accountId, '2026-09-01'), 'color', 'note');

		self::assertSame($before[self::WISE], $after[self::WISE]);
		self::assertSame($before[self::VUAA], $after[self::VUAA]);
	}

	public function testChangingAnExistingGoalKeepsTheStockColor(): void {
		$accountId = $this->createTestAccount();
		setStockGoal($this->pdo, $accountId, self::VUAA, 800);
		$color = getStockGoalRows($this->pdo, $accountId, '2026-09-01')[0]['color'];

		setStockGoal($this->pdo, $accountId, self::VUAA, 900);

		self::assertSame($color, getStockGoalRows($this->pdo, $accountId, '2026-09-01')[0]['color']);
	}

	public function testRandomColorFallsBackToAGeneratedHueWhenThePaletteIsUsedUp(): void {
		$color = pickRandomStockColor(STOCK_GOAL_COLORS);

		self::assertTrue(isValidStockColor($color));
		self::assertNotContains($color, STOCK_GOAL_COLORS);
	}

	public function testSetStockGoalStoresGoalFromCurrentMonth(): void {
		$accountId = $this->createTestAccount();

		self::assertTrue(setStockGoal($this->pdo, $accountId, self::VUAA, 800));
		self::assertTrue(setStockGoal($this->pdo, $accountId, self::VUAA, 900));

		$history = getStockGoalHistory($this->pdo, $accountId)[stockGoalKey(self::VUAA)]['history'];

		self::assertSame([['effective_from' => '2026-09-01', 'amount' => 900.0]], $history);
	}

	public function testSetStockGoalKeepsEarlierMonthsWhenChanged(): void {
		$accountId = $this->createTestAccount();
		$this->insertGoal($accountId, self::VUAA, 800, '2026-07-01');

		self::assertTrue(setStockGoal($this->pdo, $accountId, self::VUAA, 1000));

		$history = getStockGoalHistory($this->pdo, $accountId)[stockGoalKey(self::VUAA)]['history'];

		self::assertSame(800.0, getStockGoalAmountForMonth($history, '2026-08-01'));
		self::assertSame(1000.0, getStockGoalAmountForMonth($history, '2026-09-01'));
	}

	public function testRemovingGoalWritesZeroOnlyWhenGoalExists(): void {
		$accountId = $this->createTestAccount();

		self::assertTrue(setStockGoal($this->pdo, $accountId, self::VUAA, 0));
		self::assertSame([], getStockGoalHistory($this->pdo, $accountId));

		setStockGoal($this->pdo, $accountId, self::VUAA, 800);
		self::assertTrue(setStockGoal($this->pdo, $accountId, self::VUAA, 0));

		$history = getStockGoalHistory($this->pdo, $accountId)[stockGoalKey(self::VUAA)]['history'];
		self::assertSame(0.0, getStockGoalAmountForMonth($history, '2026-09-01'));
	}

	public function testSetStockGoalRejectsInvalidInput(): void {
		$accountId = $this->createTestAccount();

		self::assertIsString(setStockGoal($this->pdo, $accountId, '   ', 100));
		self::assertIsString(setStockGoal($this->pdo, $accountId, str_repeat('a', 151), 100));
		self::assertIsString(setStockGoal($this->pdo, $accountId, self::VUAA, -1));
		self::assertIsString(setStockGoal($this->pdo, $accountId, self::VUAA, STOCK_GOAL_MAX_AMOUNT + 1));
		self::assertSame([], getStockGoalHistory($this->pdo, $accountId));
	}

	public function testGoalsAreIsolatedPerAccount(): void {
		$accountId = $this->createTestAccount();
		$otherAccountId = $this->createTestAccount();
		$this->insertGoal($otherAccountId, self::VUAA, 800, '2026-08-01');

		self::assertSame([], getStockGoalHistory($this->pdo, $accountId));
		self::assertSame(['months' => [], 'series' => []], getStockGoalChartSeries($this->pdo, $accountId));
	}

	public function testMonthBoundsAndClamping(): void {
		$accountId = $this->createTestAccount();
		$this->seedTypes($accountId);
		$this->addEntry($accountId, 'investments', 100, '2026-06-20', note: self::VUAA);

		$bounds = getStockGoalMonthBounds($this->pdo, $accountId);

		self::assertSame(['earliest' => '2026-06-01', 'latest' => '2026-09-01'], $bounds);
		self::assertSame('2026-09-01', clampStockGoalMonth(null, $bounds));
		self::assertSame('2026-09-01', clampStockGoalMonth('2027-01', $bounds));
		self::assertSame('2026-06-01', clampStockGoalMonth('2025-01', $bounds));
		self::assertSame('2026-07-01', clampStockGoalMonth('2026-07', $bounds));
		self::assertSame('2026-09-01', clampStockGoalMonth('garbage', $bounds));
	}

	private function insertGoal(int $accountId, string $note, float $amount, string $effectiveFrom): void {
		$this->pdo->prepare(
			'INSERT INTO stock_goals (account_id, stock_note, monthly_amount, effective_from) VALUES (?, ?, ?, ?)'
		)->execute([$accountId, $note, $amount, $effectiveFrom]);
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<string, array<string, mixed>>
	 */
	private function rowsByNote(array $rows): array {
		return array_column($rows, null, 'note');
	}
}
