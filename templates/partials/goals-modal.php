<?php
/** @var array $monthlyStats
 * @var array<int, array{slug: string, label: string, balance_mode: string, income_percent: float}> $goalTypes
 * @var float $weekIncome
 * @var array<string, float> $weekGoalTargets
 * @var array<string, float> $weekActuals
 * @var float $currentTotalBalance
 * @var float $todayExpenses
 * @var float $weeklyDailyAllowance
 * @var float $maxDailyAllowance
 * @var float $monthlyDailyAllowance
 * @var float $maxMonthlyDailyAllowance
 * @var array<string, float> $overallMissingGoals
 * @var array<string, float> $overallSurplusGoals
 */
require_once __DIR__ . '/icons.php';

$monthTypeStats = $monthlyStats['type_stats'] ?? [];
$monthTargets = [];
$monthActuals = [];

foreach ($goalTypes as $goalType) {
	$slug = $goalType['slug'];
	$stats = $monthTypeStats[$slug] ?? ['goal' => 0, 'actual' => 0];
	$monthTargets[$slug] = (float) $stats['goal'];
	$monthActuals[$slug] = (float) $stats['actual'];
}
?>
<div class="modal-overlay js-goals-modal" role="dialog" aria-modal="true" aria-labelledby="goals-modal-title">
	<div class="modal goals-modal">
		<div class="modal__header">
			<div>
				<h2 class="modal__title" id="goals-modal-title">Goals</h2>
				<p class="modal__subtitle">How you're tracking against your goals. Informational only — not financial advice.</p>
			</div>
			<button type="button" class="modal__close js-goals-close" aria-label="Close">
				<?php icon('x', 'modal__close-icon'); ?>
			</button>
		</div>

		<div class="goals-modal__scroll">
		<div class="goals-card goals-card--spend js-spend-goals-card">
			<div class="goals-card__header">
				<h3 class="goals-card__title js-spend-goals-title">Spend daily this week</h3>
				<div class="goals-card__period-toggle" role="group" aria-label="Spend allowance period">
					<button type="button" class="goals-card__period-option goals-card__period-option--active js-spend-period-option" data-period="week" aria-pressed="true">Weekly</button>
					<button type="button" class="goals-card__period-option js-spend-period-option" data-period="month" aria-pressed="false">Monthly</button>
				</div>
			</div>
			<p class="goals-card__hint">Based on your current bank and cash balance only.</p>
			<div class="goals-card__panel js-spend-panel" data-period="week">
				<div class="goals-card__row">
					<span>Daily allowance</span>
					<span><?php echo number_format($weeklyDailyAllowance, 2); ?></span>
				</div>
				<div class="goals-card__row">
					<span>Max daily (rest of the week)</span>
					<span><?php echo number_format($maxDailyAllowance, 2); ?></span>
				</div>
				<div class="goals-card__row">
					<span>Spent today</span>
					<span><?php echo htmlspecialchars(formatBankEntryDisplayAmount((float) $todayExpenses, ['balance_mode' => 'wallet_out'])); ?></span>
				</div>
			</div>
			<div class="goals-card__panel goals-card__panel--hidden js-spend-panel" data-period="month" hidden>
				<div class="goals-card__row">
					<span>Daily allowance</span>
					<span><?php echo number_format($monthlyDailyAllowance, 2); ?></span>
				</div>
				<div class="goals-card__row">
					<span>Max daily (rest of the month)</span>
					<span><?php echo number_format($maxMonthlyDailyAllowance, 2); ?></span>
				</div>
				<div class="goals-card__row">
					<span>Spent today</span>
					<span><?php echo htmlspecialchars(formatBankEntryDisplayAmount((float) $todayExpenses, ['balance_mode' => 'wallet_out'])); ?></span>
				</div>
			</div>
		</div>

		<?php
			$periodTitle = "This month's goals";
			$incomeLabel = "Month's income";
			$incomeAmount = (float) ($monthlyStats['income'] ?? 0);
			$targets = $monthTargets;
			$actuals = $monthActuals;
			$showGoalShortfall = true;
			$goalInfoPeriod = 'month';
			include __DIR__ . '/goals-period-card.php';
		?>

		<?php
			$periodTitle = "This week's goals";
			$incomeLabel = "Week's income";
			$incomeAmount = $weekIncome;
			$targets = $weekGoalTargets;
			$actuals = $weekActuals;
			$showGoalShortfall = false;
			$goalInfoPeriod = 'week';
			include __DIR__ . '/goals-period-card.php';
		?>

		<?php include __DIR__ . '/goals-missing-card.php'; ?>
		</div>
	</div>
</div>
