<?php
/** @var string|null $error
 * @var string $bankBalanceValue
 * @var string $cashBalanceValue */
?>
<div class="onboarding-page">
	<div class="onboarding-card">
		<h1>Sync your current balances</h1>
		<p class="onboarding-subtitle">Enter what you have in bank and cash right now. This does not split money by your plan — your percentages start with the next income you add.</p>

		<?php if ($error !== null): ?>
			<p class="auth-error"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>

		<form class="onboarding-form" method="POST" action="/onboarding/balances">
			<?php echo csrfField(); ?>
			<div class="onboarding-balances">
				<label class="onboarding-income">
					Current bank balance
					<input type="number" step="0.01" min="0" name="bank_balance" value="<?php echo htmlspecialchars($bankBalanceValue); ?>" placeholder="e.g. 1200" required>
				</label>
				<label class="onboarding-income">
					Current cash balance
					<input type="number" step="0.01" min="0" name="cash_balance" value="<?php echo htmlspecialchars($cashBalanceValue); ?>" placeholder="e.g. 150" required>
				</label>
			</div>

			<button type="submit" class="btn btn-primary">Continue to Configure</button>
		</form>
	</div>
</div>
