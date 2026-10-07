<?php
/** @var string $periodTitle
 * @var array<int, array{slug: string, label: string, balance_mode: string}> $goalTypes
 * @var array<string, float> $targets
 * @var array<string, float> $actuals
 * @var string|null $incomeLabel
 * @var float|null $incomeAmount
 * @var bool $showGoalShortfall
 * @var bool $showGoalNeedMore
 * @var array<string, bool> $goalTypeHasEntryToday
 * @var string $goalInfoPeriod today|week|month */
$showGoalShortfall = $showGoalShortfall ?? false;
$showGoalNeedMore = $showGoalNeedMore ?? false;
$goalTypeHasEntryToday = $goalTypeHasEntryToday ?? [];
$goalInfoPeriod = $goalInfoPeriod ?? 'month';

require_once __DIR__ . '/info-ring.php';
?>
<div class="goals-card">
	<h3 class="goals-card__title"><?php echo htmlspecialchars($periodTitle); ?></h3>
	<?php if ($incomeLabel !== null): ?>
		<div class="goals-card__row goals-card__row--income">
			<span><?php echo htmlspecialchars($incomeLabel); ?></span>
			<span><?php echo number_format($incomeAmount ?? 0, 2); ?></span>
		</div>
	<?php endif; ?>
	<?php foreach ($goalTypes as $goalType): ?>
		<?php
			$slug = $goalType['slug'];
			$goal = (float) ($targets[$slug] ?? 0);
			$actual = (float) ($actuals[$slug] ?? 0);
			$display = getGoalTrackingDisplayConfig($goalType);
			$checkState = getGoalTrackingCheckState($goal, $actual, $goalType);
		?>
		<div class="goals-card__row">
			<span class="goals-card__label">
				<?php echo htmlspecialchars($display['should_label']); ?>:
				<?php renderInfoRing(getGoalInfoTooltipText($slug, 'should', $goalInfoPeriod)); ?>
			</span>
			<span><?php echo number_format($goal, 2); ?></span>
		</div>
		<div class="goals-card__row goals-card__row--actual">
			<span class="goals-card__label">
				<?php echo htmlspecialchars($display['actual_label']); ?>:
				<?php renderInfoRing(getGoalInfoTooltipText($slug, 'actual', $goalInfoPeriod)); ?>
			</span>
			<span class="goals-card__value goals-card__value--signed">
				<?php echo htmlspecialchars(formatBankEntryDisplayAmount($actual, $goalType)); ?>
				<?php if ($checkState !== null): ?>
					<span class="goals-card__check goals-card__check--<?php echo htmlspecialchars($checkState); ?>"
						aria-label="<?php echo htmlspecialchars(getGoalTrackingCheckAriaLabel($goalType, $checkState)); ?>">
						<?php echo $checkState === 'met' ? '&#10003;' : '&#10007;'; ?>
					</span>
				<?php endif; ?>
			</span>
		</div>
		<?php
			$needMoreAmount = getGoalTrackingNeedMoreAmount($goal, $actual, $goalType);
			$hasEntryForGoalType = $goalTypeHasEntryToday[$slug] ?? false;
		?>
		<?php if ($showGoalNeedMore && $hasEntryForGoalType && $needMoreAmount !== null): ?>
			<p class="goals-card__shortfall">
				Need to <?php echo htmlspecialchars(getGoalTrackingNeedMoreVerb($goalType)); ?>
				<?php echo number_format($needMoreAmount, 2); ?> more
			</p>
		<?php elseif ($showGoalShortfall && $display['check_mode'] === 'at_least' && $goal > $actual): ?>
			<p class="goals-card__shortfall">Missing: <?php echo number_format($goal - $actual, 2); ?></p>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
