<?php
/** @var array<int, array> $statsHistory
 * @var array<int, array{slug: string, label: string, balance_mode: string, income_percent: ?float}> $bankTypes
 * @var array<string, array<string, array<int, array{note: string, amount: float}>>> $typeBreakdowns
 * @var array<string, array> $netWorthHistory
 * @var int $accountId
 * @var \PDO $pdo
 * @var string $uri */

$historyTypes = getHistoryCardTypes($bankTypes);
$growthTypes = getHistoryGrowthTypes($historyTypes);
$goalTypes = getHistoryGoalTrackingTypes($historyTypes);
$potTypes = array_values(array_filter(
	$historyTypes,
	fn(array $type) => $type['balance_mode'] === 'pot' && $type['income_percent'] === null
));

require_once __DIR__ . '/partials/info-ring.php';
?>
<div class="app-shell">
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main">
		<?php
			$href = '/bank';
			$label = 'Back to Bank';
			include __DIR__ . '/partials/back-link.php';
		?>

		<h1 class="page-title">Month History</h1>

		<?php if (empty($statsHistory)): ?>
			<p class="empty-state">No history yet.</p>
		<?php endif; ?>

		<div class="month-history-grid">
		<?php foreach ($statsHistory as $month): ?>
			<?php
				$netWorth = $netWorthHistory[$month['stat_month']] ?? null;
				$typeStats = $month['type_stats'] ?? [];
				$goalInfoPeriod = $month['stat_month'] === date('Y-m-01') ? 'month' : 'history';
			?>
			<div class="month-history-card">
				<h2 class="month-history-card__title">
					<?php echo date('F Y', strtotime($month['stat_month'])); ?>
					<?php if ($month['stat_month'] === date('Y-m-01')): ?>
						(current)
					<?php endif; ?>
				</h2>

				<div class="month-history-row">
					<span class="month-history-row__label">Income</span>
					<span class="month-history-row__value"><?php echo number_format($month['income'], 2); ?></span>
				</div>

				<?php foreach ($goalTypes as $goalType): ?>
					<?php
						if (!typeHasEntriesInMonth($pdo, $accountId, $goalType['slug'], $month['stat_month'])) {
							continue;
						}

						$slug = $goalType['slug'];
						$stats = $typeStats[$slug] ?? ['goal' => 0, 'actual' => 0];
						$display = getGoalTrackingDisplayConfig($goalType);
					?>
					<div class="month-history-row">
						<span class="month-history-row__label">
							<?php echo htmlspecialchars($display['should_label']); ?>:
							<?php renderInfoRing(getGoalInfoTooltipText($slug, 'should', $goalInfoPeriod)); ?>
						</span>
						<span class="month-history-row__value"><?php echo number_format($stats['goal'], 2); ?></span>
					</div>
					<div class="month-history-row">
						<span class="month-history-row__label">
							<?php echo htmlspecialchars($display['actual_label']); ?>:
							<?php renderInfoRing(getGoalInfoTooltipText($slug, 'actual', $goalInfoPeriod)); ?>
						</span>
						<span class="month-history-row__value"><?php echo htmlspecialchars(formatBankEntryDisplayAmount((float) $stats['actual'], $goalType)); ?></span>
					</div>
					<?php if ($slug !== 'expenses'): ?>
						<?php if ($stats['goal'] <= $stats['actual']): ?>
							<span class="month-history-row__note">Over: <?php echo number_format($stats['actual'] - $stats['goal'], 2); ?></span>
						<?php else: ?>
							<span class="month-history-row__note">Missing: <?php echo number_format($stats['goal'] - $stats['actual'], 2); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				<?php endforeach; ?>

				<?php
					$investmentsType = null;

					foreach ($goalTypes as $candidateType) {
						if ($candidateType['slug'] === 'investments') {
							$investmentsType = $candidateType;
							break;
						}
					}

					$investmentsBreakdown = $typeBreakdowns['investments'][$month['stat_month']] ?? [];
				?>
				<?php if ($investmentsType !== null && typeHasEntriesInMonth($pdo, $accountId, 'investments', $month['stat_month']) && $investmentsBreakdown !== []): ?>
					<div class="month-history-row">
						<span class="month-history-row__label">Invested into</span>
					</div>
					<div class="month-history-breakdown month-history-breakdown--aligned">
						<?php foreach ($investmentsBreakdown as $item): ?>
							<?php $noteParts = splitInvestmentNote($item['note']); ?>
							<div class="month-history-breakdown__item">
								<span class="month-history-breakdown__name<?php echo $noteParts['ticker'] === '' ? ' month-history-breakdown__name--wide' : ''; ?>"><?php echo stockLogoImage($stockLogoFor($item['note'])['url'] ?? null, 'stock-logo--inline'); ?><?php echo htmlspecialchars($noteParts['name']); ?></span>
								<?php if ($noteParts['ticker'] !== ''): ?>
									<span class="month-history-breakdown__ticker"><?php echo htmlspecialchars($noteParts['ticker']); ?></span>
								<?php endif; ?>
								<span class="month-history-breakdown__amount"><?php echo number_format($item['amount'], 2); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php foreach ($potTypes as $potType): ?>
					<?php
						if (!typeHasEntriesInMonth($pdo, $accountId, $potType['slug'], $month['stat_month'])) {
							continue;
						}

						$slug = $potType['slug'];
						$stats = $typeStats[$slug] ?? ['goal' => 0, 'actual' => 0];
					?>
					<div class="month-history-row">
						<span class="month-history-row__label"><?php echo htmlspecialchars($potType['label']); ?></span>
						<span class="month-history-row__value"><?php echo number_format($netWorth !== null ? ($netWorth['net_worth']['pots'][$slug] ?? $stats['actual']) : $stats['actual'], 2); ?></span>
					</div>
				<?php endforeach; ?>

				<?php if ($month['wallet_adjustment'] !== 0.0): ?>
					<div class="month-history-row">
						<span class="month-history-row__label">Adjustments</span>
						<span class="month-history-row__value"><?php echo number_format($month['wallet_adjustment'], 2); ?></span>
					</div>
					<span class="month-history-row__note">Not counted as income</span>
				<?php endif; ?>

				<?php if ($netWorth !== null): ?>
					<div class="month-history-row month-history-row--highlight">
						<span class="month-history-row__label">Net worth</span>
						<span class="month-history-row__value"><?php echo number_format($netWorth['net_worth']['total'], 2); ?></span>
					</div>
					<?php if ($netWorth['growth'] !== null): ?>
						<?php $totalGrowthDirection = $netWorth['growth']['total'] >= 0 ? 'positive' : 'negative'; ?>
						<div class="month-history-row month-history-row--section">
							<span class="month-history-row__label">Change vs last month</span>
							<span class="month-history-row__value month-history-row__growth--<?php echo $totalGrowthDirection; ?>">
								<?php echo formatSignedAmount($netWorth['growth']['total']); ?>
								<?php if ($netWorth['growth']['total_pct'] !== null): ?>
									(<?php echo formatSignedAmount($netWorth['growth']['total_pct']); ?>%)
								<?php endif; ?>
							</span>
						</div>
						<div class="month-history-breakdown">
							<div class="month-history-breakdown__item">
								<span>Card</span>
								<span class="month-history-row__growth--<?php echo $netWorth['growth']['card'] >= 0 ? 'positive' : 'negative'; ?>">
									<?php echo formatSignedAmount($netWorth['growth']['card']); ?>
								</span>
							</div>
							<div class="month-history-breakdown__item">
								<span>Cash</span>
								<span class="month-history-row__growth--<?php echo $netWorth['growth']['cash'] >= 0 ? 'positive' : 'negative'; ?>">
									<?php echo formatSignedAmount($netWorth['growth']['cash']); ?>
								</span>
							</div>
							<?php foreach ($growthTypes as $historyType): ?>
								<?php
									if (!typeHasEntriesInMonth($pdo, $accountId, $historyType['slug'], $month['stat_month'])) {
										continue;
									}

									$slug = $historyType['slug'];
									$typeGrowth = (float) ($netWorth['growth']['types'][$slug] ?? 0);
								?>
								<div class="month-history-breakdown__item">
									<span><?php echo htmlspecialchars($historyType['label']); ?></span>
									<span class="month-history-row__growth--<?php echo $typeGrowth >= 0 ? 'positive' : 'negative'; ?>">
										<?php echo formatSignedAmount($typeGrowth); ?>
									</span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		</div>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
