<?php
/** @var array<int, array{id: int, type: string, method: string, amount: float, note: string|null, is_pending: int}> $todayEntries
 * @var float $balance
 * @var float $cashBalance
 * @var array<string, float> $potBalances
 * @var array<int, array{slug: string, label: string, color_hex: string, balance_mode: string, income_percent: ?float, is_active: bool, show_in_pills: bool}> $bankTypes
 * @var array<int, array{slug: string, label: string, color_hex: string}> $visibleBankTypes
 * @var array<int, array{slug: string, label: string, color_hex: string}> $addFormBankTypes
 * @var array<int, array{slug: string, label: string, income_percent: float}> $goalTypes
 * @var array<string, float> $weekGoalTargets
 * @var array<string, float> $todayGoalTargets
 * @var string $range
 * @var array<int, array<string, float|string>> $weekHistory
 * @var array<string, float> $totals
 * @var array<int, array<string, float|string>> $history
 * @var array $monthlyStats
 * @var array<string, float> $overallMissingGoals
 * @var array<string, float> $overallSurplusGoals
 * @var string $uri */

require_once __DIR__ . '/partials/icons.php';

$currentTotalBalance = $balance + $cashBalance;
$potTotal = array_sum($potBalances);
$grandTotal = $currentTotalBalance + $potTotal;

$goalTypes = getGoalTrackingTypes($bankTypes);
$weekIncome = array_sum(array_column($weekHistory, 'income'));

$todayIncomeEntries = array_filter($todayEntries, fn($entry) => $entry['type'] === 'income');
$todayIncome = array_sum(array_column($todayIncomeEntries, 'amount'));

$dayOfWeek = (int) date('N');
$daysRemainingOfWeek = 8 - $dayOfWeek;
$maxDailyAllowance = $daysRemainingOfWeek > 0 ? $currentTotalBalance / $daysRemainingOfWeek : 0;
$weeklyDailyAllowance = $currentTotalBalance / 7;

$daysInMonth = (int) date('t');
$dayOfMonth = (int) date('j');
$daysRemainingOfMonth = $daysInMonth - $dayOfMonth + 1;
$monthlyDailyAllowance = $daysInMonth > 0 ? $currentTotalBalance / $daysInMonth : 0;
$maxMonthlyDailyAllowance = $daysRemainingOfMonth > 0 ? $currentTotalBalance / $daysRemainingOfMonth : 0;

$todayExpenseEntries = array_filter($todayEntries, fn($entry) => $entry['type'] === 'expenses');
$todayExpenses = array_sum(array_column($todayExpenseEntries, 'amount'));

$showTodayGoals = $todayIncome > 0;

$todayActuals = [];
$todayGoalTypeHasEntry = [];
foreach ($goalTypes as $goalType) {
	$slug = $goalType['slug'];
	$todayGoalTypeHasEntry[$slug] = false;
}
foreach ($todayEntries as $entry) {
	if (array_key_exists($entry['type'], $todayGoalTypeHasEntry)) {
		$todayGoalTypeHasEntry[$entry['type']] = true;
	}
}
foreach ($goalTypes as $goalType) {
	$slug = $goalType['slug'];
	$todayActuals[$slug] = array_sum(array_column(
		array_filter($todayEntries, fn($entry) => $entry['type'] === $slug),
		'amount'
	));
}

$weekActuals = [];
foreach ($goalTypes as $goalType) {
	$slug = $goalType['slug'];
	$weekActuals[$slug] = array_sum(array_column($weekHistory, $slug));
}

$summaryTypes = array_values(array_filter(
	$bankTypes,
	fn(array $type) => $type['slug'] !== 'wallet_adjustment' && $type['slug'] !== 'kogumiskonto' && ($type['show_in_pills'] || ($totals[$type['slug']] ?? 0) > 0)
));

$bankTypesBySlug = [];
foreach ($bankTypes as $bankType) {
	$bankTypesBySlug[$bankType['slug']] = $bankType;
}
?>
<div class="app-shell bank-type-vars">
	<?php include __DIR__ . '/partials/bank-type-styles.php'; ?>
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main">
		<div class="page-header-row">
			<h1 class="page-title">Bank</h1>
			<div class="bank-actions">
				<button type="button" class="btn-pill js-goals-open">
					<?php icon('target'); ?>
					Goals
				</button>
				<a class="btn-pill" href="/bank/configure">
					<?php icon('sliders'); ?>
					Configure
				</a>
				<a class="btn-pill" href="/bank/history">
					<?php icon('history'); ?>
					History
				</a>
			</div>
		</div>

		<div class="bank-balance-grid">
			<div class="bank-balance-card">
				<span class="bank-balance-card__label">Bank balance</span>
				<span class="bank-balance-card__value"><?php echo number_format($balance, 2); ?></span>
			</div>
			<div class="bank-balance-card">
				<span class="bank-balance-card__label">Cash balance</span>
				<span class="bank-balance-card__value"><?php echo number_format($cashBalance, 2); ?></span>
			</div>
			<?php foreach ($bankTypes as $type): ?>
				<?php if ($type['balance_mode'] !== 'pot' || !$type['show_in_pills']): ?>
					<?php continue; ?>
				<?php endif; ?>
				<div class="bank-balance-card bank-type--<?php echo htmlspecialchars($type['slug']); ?>">
					<span class="bank-balance-card__label"><?php echo htmlspecialchars($type['label']); ?></span>
					<span class="bank-balance-card__value"><?php echo number_format($potBalances[$type['slug']] ?? 0, 2); ?></span>
				</div>
			<?php endforeach; ?>
			<div class="bank-balance-card bank-balance-card--total">
				<span class="bank-balance-card__label">Total</span>
				<span class="bank-balance-card__value"><?php echo number_format($grandTotal, 2); ?></span>
			</div>
		</div>

		<div class="bank-page__columns">
		<div class="bank-page__primary">
		<div class="form-card">
			<form class="bank-form js-bank-add-form" method="POST" action="/bank">
				<?php echo csrfField(); ?>
				<div class="form-field">
					<label for="bank-add-type">Type</label>
					<select name="type" id="bank-add-type" class="js-bank-add-type">
						<?php foreach ($addFormBankTypes as $type): ?>
							<?php $directionLabels = bankEntryDirectionLabels($type); ?>
							<option value="<?php echo htmlspecialchars($type['slug']); ?>"
								data-label="<?php echo htmlspecialchars(bankEntryTypeLabelForType($type, 'card')); ?>"
								data-label-cash="<?php echo htmlspecialchars(bankEntryTypeLabelForType($type, 'cash')); ?>"
								data-balance-mode="<?php echo htmlspecialchars($type['balance_mode']); ?>"
								data-label-in="<?php echo htmlspecialchars($directionLabels['in']); ?>"
								data-label-out="<?php echo htmlspecialchars($directionLabels['out']); ?>"><?php echo htmlspecialchars(bankEntryTypeLabelForType($type, 'card')); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php
					$defaultAddType = $addFormBankTypes[0] ?? ['slug' => 'income', 'label' => 'Income', 'balance_mode' => 'wallet_in'];
					$bankEntryType = $defaultAddType;
					$showToggle = bankTypeShowsDirectionOnMainForm($defaultAddType);
					$selectedDirection = 'in';
					include __DIR__ . '/partials/bank-entry-direction.php';
				?>
				<div class="form-row">
					<div class="form-field">
						<label for="bank-add-method">Method</label>
						<select name="method" id="bank-add-method" class="js-bank-add-method">
							<?php foreach (BANK_ENTRY_METHODS as $method): ?>
								<option value="<?php echo $method; ?>"><?php echo bankEntryMethodLabel($method); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="form-field">
						<label for="bank-add-amount">Amount</label>
						<input type="number" step="0.01" min="0.01" name="amount" id="bank-add-amount" placeholder="0.00" required>
					</div>
				</div>
				<?php if (!empty($investmentNotes)): ?>
					<div class="form-field js-bank-add-stock-field" hidden>
						<label for="bank-add-stock">Stock</label>
						<select id="bank-add-stock" class="js-bank-add-stock">
							<?php foreach ($investmentNotes as $investmentNote): ?>
								<option value="<?php echo htmlspecialchars($investmentNote['note']); ?>"><?php echo htmlspecialchars($investmentNote['label']); ?></option>
							<?php endforeach; ?>
							<option value="" data-new-stock="1">+ New stock...</option>
						</select>
					</div>
				<?php endif; ?>
				<div class="form-field js-bank-add-note-field">
					<label for="bank-add-note">Note (optional)</label>
					<input type="text" name="note" id="bank-add-note" maxlength="50" placeholder="Max 50 chars" data-placeholder-default="Max 50 chars" data-placeholder-new-stock="Vanguard S&P 500 (€VUAA)">
					<p class="form-field__hint js-bank-note-hint" hidden>We recommend this stock note format: Stock name (€TICKER)</p>
				</div>
				<button class="btn btn-primary js-bank-submit-btn" type="submit">
					<?php icon('plus'); ?>
					Add
				</button>
			</form>
		</div>

		<?php if ($showTodayGoals): ?>
			<?php
				$periodTitle = "Today's goals";
				$incomeLabel = "Today's income";
				$incomeAmount = $todayIncome;
				$targets = $todayGoalTargets;
				$actuals = $todayActuals;
				$goalTypeHasEntryToday = $todayGoalTypeHasEntry;
				$showGoalNeedMore = true;
				$goalInfoPeriod = 'today';
				$goalTypes = array_values(array_filter(
					$goalTypes,
					fn(array $type) => $type['slug'] !== 'expenses'
				));
				include __DIR__ . '/partials/goals-period-card.php';
			?>
		<?php endif; ?>

		<h2 class="section-title">Today's entries</h2>
		<?php if (empty($todayEntries)): ?>
			<p class="empty-state">No entries today.</p>
		<?php else: ?>
			<?php
				$todayEntryCount = count($todayEntries);
				$todayEntriesUseStack = $todayEntryCount > 1;
			?>
			<?php if ($todayEntriesUseStack): ?>
				<div
					class="today-entries-stack js-today-entries-stack"
					data-total-count="<?php echo $todayEntryCount; ?>"
					data-stack-limit="5"
				>
			<?php endif; ?>
			<ul
				id="today-entries-list"
				class="card-list bank-entry-list<?php echo $todayEntriesUseStack ? ' bank-entry-list--stacked' : ''; ?>"
			>
				<?php foreach ($todayEntries as $stackIndex => $entry): ?>
					<?php $entryType = $bankTypesBySlug[$entry['type']] ?? ['balance_mode' => 'wallet_in']; ?>
					<?php
						$stackItemClasses = 'card-list-item bank-entry-item bank-entry-item--dynamic bank-type--' . htmlspecialchars($entry['type']);
						if ($todayEntriesUseStack && $stackIndex >= 5) {
							$stackItemClasses .= ' bank-entry-item--stack-hidden';
						}
					?>
					<li
						class="<?php echo $stackItemClasses; ?>"
						data-entry-id="<?php echo $entry['id']; ?>"
						<?php if ($todayEntriesUseStack): ?>
							data-stack-index="<?php echo $stackIndex; ?>"
							style="--stack-index: <?php echo $stackIndex; ?>"
						<?php endif; ?>
					>
						<div class="bank-entry-item__view js-bank-entry-view">
							<span class="bank-entry-item__type"><?php echo htmlspecialchars(bankEntryTypeLabel(db(), $_SESSION['user_id'], $entry['type'], $entry['method'])); ?></span>
							<span class="bank-entry-item__method"><?php echo htmlspecialchars(bankEntryMethodLabel($entry['method'])); ?></span>
							<span class="bank-entry-item__amount<?php echo bankEntryDisplayAmountClass((float) $entry['amount'], $entryType); ?>"><?php echo htmlspecialchars(formatBankEntryDisplayAmount((float) $entry['amount'], $entryType)); ?></span>
							<?php if ($entry['note']): ?>
								<span class="bank-entry-item__note"><?php
									if ($entry['type'] === 'investments') {
										echo stockLogoImage($stockLogoFor((string) $entry['note'])['url'] ?? null, 'stock-logo--inline');
									}
									echo htmlspecialchars($entry['note']);
								?></span>
							<?php endif; ?>
							<?php if ($entry['is_pending']): ?>
								<span class="bank-entry-item__pending">Pending</span>
							<?php endif; ?>
						</div>
						<div class="bank-entry-item__edit-form js-bank-entry-edit-form<?php echo ($todayEntriesUseStack && $stackIndex === 0) ? ' bank-entry-item__edit-form--stack-front' : ''; ?>" hidden>
							<div class="bank-entry-item__type-row">
								<?php if ($todayEntriesUseStack && $stackIndex === 0): ?>
									<button
										type="button"
										class="today-entries-stack__toggle js-today-entries-toggle"
										aria-expanded="false"
										aria-controls="today-entries-list"
									>
										<?php icon('chevron-down', 'today-entries-stack__toggle-icon'); ?>
										<span class="today-entries-stack__toggle-label js-today-entries-toggle-label">More</span>
									</button>
								<?php endif; ?>
								<select class="js-bank-entry-type">
								<?php foreach ($bankTypes as $bankType): ?>
									<?php $editDirectionLabels = bankEntryDirectionLabels($bankType); ?>
									<option value="<?php echo htmlspecialchars($bankType['slug']); ?>"
										data-balance-mode="<?php echo htmlspecialchars($bankType['balance_mode']); ?>"
										data-label-in="<?php echo htmlspecialchars($editDirectionLabels['in']); ?>"
										data-label-out="<?php echo htmlspecialchars($editDirectionLabels['out']); ?>"
										<?php echo $bankType['slug'] === $entry['type'] ? ' selected' : ''; ?>><?php echo htmlspecialchars(bankEntryTypeLabelForType($bankType, $entry['method'])); ?></option>
								<?php endforeach; ?>
								</select>
							</div>
							<select class="js-bank-entry-method">
								<?php foreach (BANK_ENTRY_METHODS as $method): ?>
									<option value="<?php echo $method; ?>"<?php echo $method === $entry['method'] ? ' selected' : ''; ?>><?php echo bankEntryMethodLabel($method); ?></option>
								<?php endforeach; ?>
							</select>
							<?php
								$entryBankType = null;
								foreach ($bankTypes as $bankType) {
									if ($bankType['slug'] === $entry['type']) {
										$entryBankType = $bankType;
										break;
									}
								}
								if ($entryBankType !== null):
									$bankEntryType = $entryBankType;
									$showToggle = bankTypeShowsDirectionOnMainForm($entryBankType);
									$selectedDirection = $entry['amount'] < 0 ? 'out' : 'in';
									include __DIR__ . '/partials/bank-entry-direction.php';
								endif;
							?>
							<input type="number" step="0.01" min="0.01" class="js-bank-entry-amount" value="<?php echo abs((float) $entry['amount']); ?>">
							<?php
								$entryNote = (string) $entry['note'];
								$editStockNotes = $investmentNotes ?? [];
								$entryNoteInStocks = in_array($entryNote, array_column($editStockNotes, 'note'), true);

								if ($entry['type'] === 'investments' && $entryNote !== '' && !$entryNoteInStocks) {
									$editStockNotes[] = ['note' => $entryNote, 'label' => investmentNoteTickerLabel($entryNote)];
									$entryNoteInStocks = true;
								}

								$selectEntryStock = $entry['type'] === 'investments' && $entryNoteInStocks;
							?>
							<?php if (!empty($editStockNotes)): ?>
								<select class="js-bank-entry-stock" hidden>
									<?php foreach ($editStockNotes as $stockNote): ?>
										<option value="<?php echo htmlspecialchars($stockNote['note']); ?>"<?php echo ($selectEntryStock && $stockNote['note'] === $entryNote) ? ' selected' : ''; ?>><?php echo htmlspecialchars($stockNote['label']); ?></option>
									<?php endforeach; ?>
									<option value="" data-new-stock="1"<?php echo $selectEntryStock ? '' : ' selected'; ?>>+ New stock...</option>
								</select>
							<?php endif; ?>
							<input type="text" class="js-bank-entry-note" value="<?php echo htmlspecialchars($entryNote); ?>" data-original-note="<?php echo htmlspecialchars($entryNote); ?>" placeholder="Note">
							<button type="button" class="btn btn-save js-bank-entry-save">Save</button>
						</div>
						<span class="card-list-item__actions">
							<button type="button" class="btn-delete js-bank-delete-btn" title="Delete">
								<?php icon('trash', 'btn-delete__icon'); ?>
							</button>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ($todayEntriesUseStack): ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		</div>

		<div class="bank-page__secondary">
		<h2 class="section-title">By type</h2>
		<div class="type-pills">
			<?php foreach ($visibleBankTypes as $type): ?>
				<a class="type-pill bank-type--<?php echo htmlspecialchars($type['slug']); ?>" href="/bank/<?php echo htmlspecialchars($type['slug']); ?>">
					<span class="type-pill__dot bank-type-dot"></span>
					<?php echo htmlspecialchars($type['label']); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="summary-card">
			<?php
				$rangeFilterBaseUrl = '/bank';
				$rangeFilterExtraParams = [];
				include __DIR__ . '/partials/bank-range-filter.php';
			?>

			<div class="bank-summary-grid">
				<?php foreach ($summaryTypes as $type): ?>
					<div class="bank-summary-card bank-type-colored bank-type--<?php echo htmlspecialchars($type['slug']); ?>">
						<span class="bank-summary-card__label"><?php echo htmlspecialchars($type['label']); ?></span>
						<span class="bank-summary-card__value"><?php echo htmlspecialchars(formatBankEntryDisplayAmount((float) ($totals[$type['slug']] ?? 0), $type)); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<h2 class="section-title">Daily history</h2>
		<div class="daily-history">
			<?php if (empty($history)): ?>
				<p class="empty-state">No entries in this range yet.</p>
			<?php else: ?>
				<?php foreach ($history as $day): ?>
					<div class="daily-history__item">
						<span class="daily-history__date"><?php echo date('M j, Y', strtotime($day['entry_date'])); ?></span>
						<div class="daily-history__amounts">
							<?php foreach ($bankTypes as $type): ?>
								<?php if ($type['slug'] === 'wallet_adjustment'): ?>
									<?php continue; ?>
								<?php endif; ?>
								<?php $amount = (float) ($day[$type['slug']] ?? 0); ?>
								<?php if ($amount != 0): ?>
									<span class="daily-history__amount bank-type-colored bank-type--<?php echo htmlspecialchars($type['slug']); ?>"><?php echo htmlspecialchars($type['label']); ?>: <?php echo htmlspecialchars(formatBankEntryDisplayAmount($amount, $type)); ?></span>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		</div>
		</div>
		<?php include __DIR__ . '/partials/legal-disclaimer.php'; ?>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
<?php include __DIR__ . '/partials/goals-modal.php'; ?>
