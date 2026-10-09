<?php
/** @var array<string, array{name: string, description: string, types: array}> $plans
 * @var string|null $error
 * @var string $incomeValue
 * @var string $selectedPlan */
$incomeType = array_values(array_filter(
	systemBankTypeDefinitions(),
	fn(array $type) => $type['slug'] === 'income'
))[0];
?>
<div class="onboarding-page">
	<div class="onboarding-card">
		<h1>Let's set up your plan</h1>
		<p class="onboarding-subtitle">Enter your guaranteed monthly income, then pick a starting plan. You can change everything later in Configure.</p>
		<p class="onboarding-subtitle">(Based on the guaranteed monthly income, see what You can achieve in 12 months)</p>

		<?php if ($error !== null): ?>
			<p class="auth-error"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>

		<form class="onboarding-form js-onboarding-form" method="POST" action="/onboarding">
			<?php echo csrfField(); ?>
			<label class="onboarding-income">
				Guaranteed monthly income
				<input type="number" step="0.01" min="0" name="guaranteed_monthly_income" id="onboarding-income" value="<?php echo htmlspecialchars($incomeValue); ?>" required>
			</label>

			<div class="plan-grid">
				<?php foreach ($plans as $planKey => $plan): ?>
					<?php
						$projectionTypes = array_values(array_filter(
							$plan['types'],
							fn(array $type) => $type['income_percent'] !== null
						));
					?>
					<label class="plan-card js-plan-card<?php echo $selectedPlan === $planKey ? ' plan-card--selected' : ''; ?>">
						<input type="radio" name="plan" value="<?php echo htmlspecialchars($planKey); ?>" class="js-plan-radio" hidden<?php echo $selectedPlan === $planKey ? ' checked' : ''; ?>>
						<h2 class="plan-card__name"><?php echo htmlspecialchars($plan['name']); ?></h2>
						<p class="plan-card__description"><?php echo htmlspecialchars($plan['description']); ?></p>
						<div class="plan-card__chips">
							<span class="plan-chip" style="--type-color: <?php echo htmlspecialchars($incomeType['color_hex']); ?>; --type-bg: <?php echo hexToRgba($incomeType['color_hex'], 0.15); ?>;">
								<?php echo htmlspecialchars($incomeType['label']); ?>
							</span>
							<?php foreach ($plan['types'] as $type): ?>
								<?php if ($type['income_percent'] === null): continue; endif; ?>
								<span class="plan-chip" style="--type-color: <?php echo htmlspecialchars($type['color_hex']); ?>; --type-bg: <?php echo hexToRgba($type['color_hex'], 0.15); ?>;">
									<?php echo htmlspecialchars($type['label']); ?> &middot; <?php echo round($type['income_percent'] * 100); ?>%
								</span>
							<?php endforeach; ?>
						</div>
						<div class="plan-card__projection js-plan-projection" data-income-label="<?php echo htmlspecialchars($incomeType['label']); ?>" data-income-color="<?php echo htmlspecialchars($incomeType['color_hex']); ?>" data-types='<?php echo htmlspecialchars(json_encode($projectionTypes)); ?>'></div>
					</label>
				<?php endforeach; ?>
			</div>

			<button type="submit" class="btn btn-primary js-onboarding-submit" disabled>Continue</button>
		</form>
	</div>
</div>
