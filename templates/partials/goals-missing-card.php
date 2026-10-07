<?php
/** @var array<string, float> $overallMissingGoals
 * @var array<string, float> $overallSurplusGoals
 * @var array<int, array{slug: string, label: string}> $goalTypes */

$goalTypesBySlug = [];
foreach ($goalTypes as $goalType) {
	$goalTypesBySlug[$goalType['slug']] = $goalType;
}

$totalRemaining = array_sum($overallMissingGoals);
$overallSurplusGoals = $overallSurplusGoals ?? [];
$totalSurplus = array_sum($overallSurplusGoals);
?>
<?php if ($overallMissingGoals !== []): ?>
<div class="goals-card">
	<h3 class="goals-card__title">Missing goals</h3>
	<p class="goals-card__hint">Based on missing type goals from all-month history, in later month's if the type(s) is over the goal, it reduces the missing goals.</p>
	<?php foreach ($overallMissingGoals as $slug => $remaining): ?>
		<?php
			$type = $goalTypesBySlug[$slug] ?? ['slug' => $slug, 'label' => $slug];
		?>
		<div class="goals-card__row goals-card__row--actual">
			<span><?php echo htmlspecialchars($type['label']); ?></span>
			<span class="goals-card__value goals-card__value--missed"><?php echo number_format($remaining, 2); ?></span>
		</div>
	<?php endforeach; ?>
	<div class="goals-card__row goals-card__row--total">
		<span>Total remaining</span>
		<span class="goals-card__value goals-card__value--missed"><?php echo number_format($totalRemaining, 2); ?></span>
	</div>
</div>
<?php endif; ?>
<?php if ($overallSurplusGoals !== []): ?>
<div class="goals-card">
	<h3 class="goals-card__title">Over goals</h3>
	<p class="goals-card__hint">Based on over type goals from all-month history. Missing goals will reduce the over goals.</p>
	<?php foreach ($overallSurplusGoals as $slug => $surplus): ?>
		<?php
			$type = $goalTypesBySlug[$slug] ?? ['slug' => $slug, 'label' => $slug];
		?>
		<div class="goals-card__row goals-card__row--actual">
			<span><?php echo htmlspecialchars($type['label']); ?></span>
			<span class="goals-card__value goals-card__value--surplus"><?php echo number_format($surplus, 2); ?></span>
		</div>
	<?php endforeach; ?>
	<div class="goals-card__row goals-card__row--total">
		<span>Total ahead</span>
		<span class="goals-card__value goals-card__value--surplus"><?php echo number_format($totalSurplus, 2); ?></span>
	</div>
</div>
<?php endif; ?>
