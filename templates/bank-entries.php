<?php
/** @var string $type
 * @var array{slug: string, label: string, color_hex: string} $currentType
 * @var array<int, array{slug: string, label: string, color_hex: string}> $visibleBankTypes
 * @var array<int, array{slug: string, label: string, color_hex: string}> $bankTypes
 * @var string $range
 * @var string $search
 * @var float $entriesTotal
 * @var array<int, array{id: int, entry_date: string, method: string, amount: float, note: string|null, is_pending: int}> $entries
 * @var array<int, array{note: string, amount: float}> $investmentsNoteBreakdown
 * @var string $uri */

require_once __DIR__ . '/partials/icons.php';

$entriesByDate = [];
foreach ($entries as $entry) {
	$entriesByDate[$entry['entry_date']][] = $entry;
}
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

		<h1 class="page-title bank-type--<?php echo htmlspecialchars($type); ?>">
			<span class="bank-type-dot"></span>
			<?php echo htmlspecialchars($currentType['label'] ?? $type); ?>
		</h1>

		<div class="type-page__layout">
			<div class="type-page__sidebar">
				<?php if ($currentType['is_active'] ?? true): ?>
				<div class="form-card">
					<form class="bank-form js-bank-type-form" method="POST" action="/bank/<?php echo htmlspecialchars($type); ?>">
						<?php echo csrfField(); ?>
						<div class="form-field">
							<label for="entry-date">Date</label>
							<input type="date" name="entry_date" id="entry-date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
						</div>
						<?php
							$bankEntryType = $currentType;
							$showToggle = bankTypeSupportsWithdraw($currentType);
							$selectedDirection = 'in';
							include __DIR__ . '/partials/bank-entry-direction.php';
						?>
						<div class="form-row">
							<div class="form-field">
								<label for="entry-method">Method</label>
								<select name="method" id="entry-method">
									<?php foreach (BANK_ENTRY_METHODS as $method): ?>
										<option value="<?php echo $method; ?>"><?php echo bankEntryMethodLabel($method); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="form-field">
								<label for="entry-amount">Amount</label>
								<input type="number" step="0.01" min="0.01" name="amount" id="entry-amount" placeholder="0.00" required>
							</div>
						</div>
						<?php if ($type === 'investments' && !empty($investmentNotes)): ?>
							<div class="form-field">
								<label for="entry-stock">Stock</label>
								<select id="entry-stock" class="js-bank-type-stock">
									<?php foreach ($investmentNotes as $investmentNote): ?>
										<option value="<?php echo htmlspecialchars($investmentNote['note']); ?>"><?php echo htmlspecialchars($investmentNote['label']); ?></option>
									<?php endforeach; ?>
									<option value="" data-new-stock="1">+ New stock...</option>
								</select>
							</div>
						<?php endif; ?>
						<div class="form-field js-bank-type-note-field">
							<label for="entry-note">Note (optional)</label>
							<input type="text" name="note" id="entry-note" maxlength="150" placeholder="Max 150 chars" data-placeholder-default="Max 150 chars" data-placeholder-new-stock="Type the new stock name or ticker">
							<?php if ($type === 'investments'): ?>
								<p class="form-field__hint">We recommend this stock note format: Stock name (€TICKER)</p>
							<?php endif; ?>
						</div>
						<button class="btn btn-primary js-bank-submit-btn" type="submit">
							<?php icon('plus'); ?>
							<?php echo htmlspecialchars(bankEntryDirectionLabels($currentType)['in']); ?>
						</button>
					</form>
				</div>
				<?php else: ?>
					<p class="empty-state">This type is inactive. You can still view past entries.</p>
				<?php endif; ?>

				<div class="type-pills">
					<?php foreach ($visibleBankTypes as $otherType): ?>
						<a class="type-pill bank-type--<?php echo htmlspecialchars($otherType['slug']); ?><?php echo $otherType['slug'] === $type ? ' type-pill--active' : ''; ?>" href="/bank/<?php echo htmlspecialchars($otherType['slug']); ?>">
							<span class="type-pill__dot bank-type-dot"></span>
							<?php echo htmlspecialchars($otherType['label']); ?>
						</a>
					<?php endforeach; ?>
				</div>

				<div class="filter-card">
					<?php
						$rangeFilterBaseUrl = '/bank/' . $type;
						$rangeFilterExtraParams = ['q' => $search];
						include __DIR__ . '/partials/bank-range-filter.php';
					?>

					<form class="search-bar" method="GET" action="/bank/<?php echo htmlspecialchars($type); ?>">
						<input type="hidden" name="range" value="<?php echo htmlspecialchars($range); ?>">
						<?php if ($range === 'custom'): ?>
							<input type="hidden" name="from" value="<?php echo htmlspecialchars((string) $dateRange['start']); ?>">
							<input type="hidden" name="to" value="<?php echo htmlspecialchars($dateRange['end']); ?>">
						<?php endif; ?>
						<?php icon('search', 'search-bar__icon'); ?>
						<input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by note...">
					</form>
				</div>
			</div>

			<div class="type-page__content">
				<?php if ($type === 'investments' && $investmentsNoteBreakdown !== []): ?>
					<?php include __DIR__ . '/partials/investments-note-breakdown.php'; ?>
				<?php endif; ?>

				<div class="entries-header">
					<h2 class="section-title">Entries</h2>
					<span class="entries-header__total">
						Total <strong><?php echo htmlspecialchars(formatBankEntryDisplayAmount((float) $entriesTotal, $currentType)); ?></strong>
					</span>
				</div>

				<?php if (empty($entries)): ?>
					<p class="empty-state"><?php echo $search !== '' ? 'No entries match your search.' : 'No entries in this range yet.'; ?></p>
				<?php else: ?>
					<?php foreach ($entriesByDate as $date => $dateEntries): ?>
						<div class="entry-group">
							<h3 class="entry-group__date"><?php echo date('M j, Y', strtotime($date)); ?></h3>
							<?php foreach ($dateEntries as $entry): ?>
								<div class="entry-card entry-card--dynamic bank-type--<?php echo htmlspecialchars($type); ?>" data-entry-id="<?php echo $entry['id']; ?>" data-entry-type="<?php echo htmlspecialchars($type); ?>">
									<div class="entry-card__view js-bank-entry-view">
										<span class="entry-card__dot bank-type-dot"></span>
										<div class="entry-card__body">
											<span class="entry-card__type"><?php echo htmlspecialchars(bankEntryTypeLabel(db(), $_SESSION['user_id'], $type, $entry['method'])); ?></span>
											<span class="entry-card__meta">
												<?php echo htmlspecialchars(bankEntryMethodLabel($entry['method'])); ?>
												<?php if ($entry['note']): ?>
													&middot; <?php echo htmlspecialchars($entry['note']); ?>
												<?php endif; ?>
												<?php if ($entry['is_pending']): ?>
													&middot; Pending
												<?php endif; ?>
											</span>
										</div>
										<span class="entry-card__amount<?php echo bankEntryDisplayAmountIsNegative((float) $entry['amount'], $currentType) ? ' entry-card__amount--negative' : ''; ?>"><?php echo htmlspecialchars(formatBankEntryDisplayAmount((float) $entry['amount'], $currentType)); ?></span>
									</div>
									<?php include __DIR__ . '/partials/bank-history-entry-edit-form.php'; ?>
									<span class="entry-card__actions">
										<?php echo stockLogoImage($stockLogoFor((string) ($entry['note'] ?? ''))['url'] ?? null, 'stock-logo--action'); ?>
										<button type="button" class="btn-edit js-bank-edit-btn" title="Edit">
											<?php icon('pencil', 'btn-edit__icon'); ?>
										</button>
										<button type="button" class="btn-delete js-bank-delete-btn" title="Delete">
											<?php icon('trash', 'btn-delete__icon'); ?>
										</button>
									</span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
