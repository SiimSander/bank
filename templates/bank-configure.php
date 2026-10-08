<?php
/** @var array<int, array{id: int, slug: string, label: string, color_hex: string, income_percent: ?float, balance_mode: string, is_active: bool, is_system: bool, show_in_pills: bool}> $bankTypes
 * @var float $allocationTotal
 * @var bool $allocationOver
 * @var float|null $guaranteedMonthlyIncome
 * @var string $uri */

require_once __DIR__ . '/partials/icons.php';

$allocationPercent = round($allocationTotal * 100, 1);
?>
<div class="app-shell bank-type-vars">
	<?php include __DIR__ . '/partials/bank-type-styles.php'; ?>
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main">
		<?php
			$href = '/bank';
			$label = 'Back to Bank';
			include __DIR__ . '/partials/back-link.php';
		?>

		<h1 class="page-title">Configure Types</h1>
		<p class="page-subtitle">Manage labels, income percentages, colors, and visibility.</p>

		<div class="configure-summary<?php echo $allocationOver ? ' configure-summary--invalid' : ($allocationPercent > 0 && $allocationPercent < 100 ? ' configure-summary--warn' : ' configure-summary--valid'); ?>">
			<span>Active income allocation total: <strong class="js-allocation-total"><?php echo number_format($allocationPercent, 1); ?>%</strong></span>
			<span class="configure-summary__error js-allocation-error"<?php echo $allocationOver ? '' : ' hidden'; ?>>Cannot exceed 100%. Reduce other types.</span>
			<span class="configure-summary__warn js-allocation-warn"<?php echo ($allocationPercent > 0 && $allocationPercent < 100 && !$allocationOver) ? '' : ' hidden'; ?>>Under 100% is allowed — remaining income is unallocated.</span>
			<span class="configure-summary__hint">Changing a type&rsquo;s income % applies from today forward. Past income entries keep the rate that was active on each day.</span>
		</div>

		<div class="projection-panel">
			<label class="projection-panel__income">
				Guaranteed monthly income
				<input type="number" step="0.01" min="0" id="projection-income" name="guaranteed_monthly_income" class="js-projection-income" value="<?php echo $guaranteedMonthlyIncome !== null ? htmlspecialchars((string) $guaranteedMonthlyIncome) : ''; ?>" placeholder="e.g. 2000">
			</label>
			<p class="projection-panel__error js-projection-income-error" hidden></p>
			<div class="projection-grid js-projection-grid"></div>
			<p class="projection-panel__explainer js-projection-explainer" hidden>
				What you can achieve in a year, based on guaranteed monthly income and created types. <br>
				This is a planning view only; goals still follow the income you actually log.
			</p>
			<p class="projection-panel__hint js-projection-hint" hidden>Set a percentage on a type below to see it here.</p>
		</div>

		<div class="configure-list">
			<?php foreach ($bankTypes as $type): ?>
				<?php if ($type['slug'] === 'wallet_adjustment' || $type['slug'] === 'kogumiskonto'): ?>
					<?php continue; ?>
				<?php endif; ?>
				<form class="configure-row js-configure-row" data-type-id="<?php echo $type['id']; ?>" data-is-system="<?php echo $type['is_system'] ? '1' : '0'; ?>">
					<div class="configure-row__color">
						<input type="color" name="color_hex" value="<?php echo htmlspecialchars($type['color_hex']); ?>" class="configure-row__color-input" aria-label="Color for <?php echo htmlspecialchars($type['label']); ?>">
					</div>
					<div class="configure-row__fields">
						<div class="configure-row__field">
							<label>Label</label>
							<input type="text" name="label" value="<?php echo htmlspecialchars($type['label']); ?>" maxlength="100" required>
						</div>
						<div class="configure-row__field">
							<label>Slug</label>
							<input type="text" name="slug" value="<?php echo htmlspecialchars($type['slug']); ?>" maxlength="64"<?php echo $type['is_system'] ? ' readonly' : ''; ?>>
						</div>
						<div class="configure-row__field configure-row__field--percent">
							<label>Income %</label>
							<input type="number" name="income_percent" min="0" max="100" step="0.1" value="<?php echo $type['income_percent'] !== null ? round($type['income_percent'] * 100, 1) : ''; ?>" placeholder="—">
						</div>
					</div>
					<div class="configure-row__actions">
						<?php if (!$type['is_system']): ?>
							<label class="configure-toggle">
								<input type="checkbox" name="is_active" class="js-configure-active"<?php echo $type['is_active'] ? ' checked' : ''; ?>>
								<span>Active</span>
							</label>
							<button type="button" class="btn-delete js-configure-delete" title="Delete type">
								<?php icon('trash', 'btn-delete__icon'); ?>
							</button>
						<?php else: ?>
							<span class="configure-row__system-badge">System</span>
						<?php endif; ?>
					</div>
					<p class="configure-row__error js-configure-error" hidden></p>
				</form>
			<?php endforeach; ?>
		</div>

		<div class="form-card configure-add">
			<h2 class="section-title">Add new type</h2>
			<form class="configure-add-form js-configure-add-form">
				<div class="configure-row__fields">
					<div class="configure-row__field">
						<label for="new-type-label">Label</label>
						<input type="text" id="new-type-label" name="label" maxlength="100" required placeholder="e.g. Debt">
					</div>
					<div class="configure-row__field">
						<label for="new-type-slug">Slug</label>
						<input type="text" id="new-type-slug" name="slug" maxlength="64" placeholder="auto from label">
					</div>
					<div class="configure-row__field">
						<label for="new-type-color">Color</label>
						<input type="color" id="new-type-color" name="color_hex" value="#60a5fa">
					</div>
					<div class="configure-row__field configure-row__field--percent">
						<label for="new-type-percent">Income %</label>
						<input type="number" id="new-type-percent" name="income_percent" min="0" max="100" step="0.1" placeholder="e.g. 10">
					</div>
					<div class="configure-row__field">
						<label for="new-type-mode">Kind</label>
						<select id="new-type-mode" name="balance_mode">
							<option value="pot">Savings bucket (pot)</option>
							<option value="wallet_out">Expense (from wallet)</option>
						</select>
					</div>
				</div>
				<p class="configure-row__error js-configure-add-error" hidden></p>
				<button type="submit" class="btn btn-primary">
					<?php icon('plus'); ?>
					Add type
				</button>
			</form>
		</div>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
