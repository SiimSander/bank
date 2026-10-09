<?php
/** @var string $uri
 * @var string $selectedMonth
 * @var bool $isCurrentStockMonth
 * @var array $monthBounds
 * @var array $stockGoalRows
 * @var array $stockGoalSummary
 * @var array $stockGoalChart */

require_once __DIR__ . '/partials/icons.php';

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$money = static fn(float $value): string => '€' . number_format($value, 2);
$tickerLabel = static fn(string $ticker): string => trim($ticker, '()');
$selectedYear = date('Y', strtotime($selectedMonth));
$colorButton = static function (array $row) use ($escape, $tickerLabel): string {
	$color = $row['color'] ?? '#a78bfa';
	$label = $row['ticker'] !== '' ? $tickerLabel($row['ticker']) : '';

	return '<button type="button" class="stock-chip stock-chip--ticker stock-chip--color js-stock-color-edit"'
		. ' data-note="' . $escape($row['note']) . '"'
		. ' data-name="' . $escape($row['name']) . '"'
		. ' data-color="' . $escape($color) . '"'
		. ' style="--stock-color: ' . $escape($color) . '"'
		. ' aria-label="Change colour for ' . $escape($row['name']) . '"'
		. ' title="Change colour">'
		. ($label !== '' ? $escape($label) : '<span class="stock-chip__swatch"></span>')
		. '</button>';
};
$carryMonthLabel = static fn(array $carryMonth): string => date(
	date('Y', strtotime($carryMonth['month'])) === $selectedYear ? 'M' : 'M Y',
	strtotime($carryMonth['month'])
);

$coverageBar = static function (float $percent, string $title, array $lines, string $label, bool $insideProgress) use ($escape): string {
	$percent = round($percent, 2);

	return '<div class="stock-coverage js-stock-coverage' . ($insideProgress ? ' stock-coverage--inline' : '') . '" tabindex="0" role="img"'
		. ' aria-label="' . $escape($label) . '"'
		. ' data-title="' . $escape($title) . '"'
		. ' data-lines="' . $escape(json_encode($lines, JSON_UNESCAPED_UNICODE)) . '"'
		. ($insideProgress ? ' style="width: ' . $escape($percent) . '%"' : '')
		. '><span class="stock-coverage__fill"' . ($insideProgress ? '' : ' style="width: ' . $escape($percent) . '%"') . '></span></div>';
};
$previousMonth = shiftStockGoalMonth($selectedMonth, -1);
$nextMonth = shiftStockGoalMonth($selectedMonth, 1);
$hasPreviousMonth = $previousMonth >= $monthBounds['earliest'];
$hasNextMonth = $nextMonth <= $monthBounds['latest'];
$monthLabel = date('F Y', strtotime($selectedMonth));
$allMonths = getStockGoalMonthList($monthBounds);
$selectedIndex = (int) array_search($selectedMonth, $allMonths, true);
$windowStartIndex = (int) array_search(getStockGoalMonthWindow($selectedMonth, $monthBounds)[0], $allMonths, true);

$stocksWithoutGoal = array_values(array_filter($stockGoalRows, static fn(array $row): bool => !$row['has_goal']));
$goalRows = array_values(array_filter($stockGoalRows, static fn(array $row): bool => $row['has_goal']));
$ringSectors = getStockRingSectors($stockGoalRows);
?>
<div class="app-shell">
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main stock-goals">
		<div class="stock-goals__heading">
			<div>
				<h1 class="page-title">Stock Goals</h1>
				<p class="page-subtitle">Set how much you want to invest into each stock every month.</p>
			</div>
			<?php if ($isCurrentStockMonth): ?>
				<button type="button" class="btn btn-secondary stock-goals__add js-stock-goal-add">
					<?php icon('plus'); ?>
					Add stock
				</button>
			<?php endif; ?>
		</div>

		<nav class="stock-month-switcher" aria-label="Month">
			<?php if ($hasPreviousMonth): ?>
				<a class="stock-month-switcher__arrow js-stock-month-link" data-index="<?php echo $escape($selectedIndex - 1); ?>" href="/stock-goals?month=<?php echo $escape(substr($previousMonth, 0, 7)); ?>" aria-label="Previous month">&lsaquo;</a>
			<?php else: ?>
				<span class="stock-month-switcher__arrow stock-month-switcher__arrow--disabled" aria-hidden="true">&lsaquo;</span>
			<?php endif; ?>
			<div class="stock-month-switcher__label">
				<span class="stock-month-switcher__month"><?php echo $escape($monthLabel); ?></span>
				<?php if ($isCurrentStockMonth): ?>
					<span class="stock-chip stock-chip--current">This month</span>
				<?php else: ?>
					<a class="stock-chip stock-chip--link" href="/stock-goals">Back to this month</a>
				<?php endif; ?>
			</div>
			<div class="stock-month-switcher__strip js-stock-month-strip">
				<div class="stock-month-switcher__track js-stock-month-track"
					style="--stock-month-count: <?php echo $escape(count($allMonths)); ?>; --stock-month-start: <?php echo $escape($windowStartIndex); ?>">
					<?php foreach ($allMonths as $monthIndex => $listedMonth): ?>
						<?php $isSelectedListedMonth = $listedMonth === $selectedMonth; ?>
						<a class="stock-month-switcher__item js-stock-month-link<?php echo $isSelectedListedMonth ? ' stock-month-switcher__item--active' : ''; ?>"
							href="/stock-goals?month=<?php echo $escape(substr($listedMonth, 0, 7)); ?>"
							data-index="<?php echo $escape($monthIndex); ?>"
							<?php echo $isSelectedListedMonth ? 'aria-current="true"' : ''; ?>>
							<span class="stock-month-switcher__item-month"><?php echo $escape(date('F Y', strtotime($listedMonth))); ?></span>
							<?php if ($listedMonth === $monthBounds['latest']): ?>
								<span class="stock-month-switcher__item-hint">This month</span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php if ($hasNextMonth): ?>
				<a class="stock-month-switcher__arrow js-stock-month-link" data-index="<?php echo $escape($selectedIndex + 1); ?>" href="/stock-goals?month=<?php echo $escape(substr($nextMonth, 0, 7)); ?>" aria-label="Next month">&rsaquo;</a>
			<?php else: ?>
				<span class="stock-month-switcher__arrow stock-month-switcher__arrow--disabled" aria-hidden="true">&rsaquo;</span>
			<?php endif; ?>
		</nav>

		<?php if (!$isCurrentStockMonth): ?>
			<p class="stock-goals__notice">Past months are read-only. A changed goal applies from the current month on.<a class="stock-goals__back js-stock-month-link" data-index="<?php echo $escape(count($allMonths) - 1); ?>" href="/stock-goals">Back to this month</a></p>
		<?php endif; ?>

		<?php if ($isCurrentStockMonth && $stockGoalSummary['catch_up_left'] > 0): ?>
			<p class="stock-goals__behind" role="status">
				<strong>You are <?php echo $escape($money($stockGoalSummary['catch_up_left'])); ?> behind from earlier months.</strong>
				<?php
				$behindParts = array_map(
					static fn(array $stock): string => ($stock['ticker'] !== '' ? $tickerLabel($stock['ticker']) : $stock['name']) . ' ' . $money($stock['amount']) . ' (aim ' . $money($stock['aim']) . ')',
					$stockGoalSummary['behind']
				);
				echo $escape(implode(' · ', $behindParts));
				?>
			</p>
		<?php endif; ?>

		<?php if ($stockGoalSummary['goal_count'] > 0): ?>
			<section class="stock-summary" aria-label="Month summary">
				<div class="stock-ring js-stock-ring">
					<svg class="stock-ring__svg" viewBox="0 0 200 200" role="group" aria-label="Progress per stock goal">
						<circle class="stock-ring__track" cx="100" cy="100" r="<?php echo $escape(STOCK_RING_RADIUS); ?>"/>
						<?php foreach ($ringSectors as $sector): ?>
							<?php
							$sectorPath = getStockRingArcPath(100, 100, STOCK_RING_RADIUS, $sector['start'], $sector['end']);
							$middleRadians = deg2rad($sector['middle']);
							$sectorLabel = ($sector['ticker'] !== '' ? $tickerLabel($sector['ticker']) : $sector['name'])
								. ': ' . $money($sector['invested']) . ' of ' . $money($sector['goal']);
							?>
							<g class="stock-ring__sector js-stock-ring-sector" tabindex="0" role="img"
								aria-label="<?php echo $escape($sectorLabel); ?>"
								style="--stock-color: <?php echo $escape($sector['color']); ?>; --pop-x: <?php echo $escape(round(sin($middleRadians) * STOCK_RING_POP_DISTANCE, 2)); ?>px; --pop-y: <?php echo $escape(round(-cos($middleRadians) * STOCK_RING_POP_DISTANCE, 2)); ?>px"
								data-name="<?php echo $escape($sector['name']); ?>"
								data-ticker="<?php echo $escape($sector['ticker'] !== '' ? $tickerLabel($sector['ticker']) : ''); ?>"
								data-color="<?php echo $escape($sector['color']); ?>"
								data-invested="<?php echo $escape($sector['invested']); ?>"
								data-goal="<?php echo $escape($sector['goal']); ?>"
								data-percent="<?php echo $escape($sector['percent']); ?>"
								data-contribution="<?php echo $escape($sector['contribution']); ?>"
								data-overall="<?php echo $escape($sector['overall_percent']); ?>">
								<path class="stock-ring__hit" d="<?php echo $escape($sectorPath); ?>"/>
								<g class="stock-ring__shape">
									<path class="stock-ring__fill" d="<?php echo $escape($sectorPath); ?>"/>
								</g>
							</g>
						<?php endforeach; ?>
					</svg>
					<div class="stock-ring__inner">
						<span class="stock-ring__value"><?php echo $escape(round((float) $stockGoalSummary['percent'])); ?>%</span>
						<span class="stock-ring__label">of goal</span>
					</div>
					<div class="stock-ring__tooltip js-stock-ring-tooltip" hidden></div>
				</div>
				<div class="stock-summary__stats">
					<div class="stock-summary__stat">
						<p class="stock-summary__label">Invested</p>
						<p class="stock-summary__value"><?php echo $escape($money($stockGoalSummary['invested'])); ?></p>
						<p class="stock-summary__hint">of <?php echo $escape($money($stockGoalSummary['planned'])); ?> planned</p>
					</div>
					<div class="stock-summary__stat">
						<p class="stock-summary__label">Still missing</p>
						<p class="stock-summary__value<?php echo $stockGoalSummary['left'] > 0 ? ' stock-summary__value--missing' : ''; ?>"><?php echo $escape($money($stockGoalSummary['left'])); ?></p>
						<p class="stock-summary__hint"><?php echo $stockGoalSummary['catch_up_left'] > 0 ? '+ ' . $escape($money($stockGoalSummary['catch_up_left'])) . ' catch-up' : 'across unfinished stocks'; ?></p>
					</div>
					<div class="stock-summary__stat">
						<p class="stock-summary__label">Over goal</p>
						<p class="stock-summary__value<?php echo $stockGoalSummary['over'] > 0 ? ' stock-summary__value--over' : ''; ?>"><?php echo $escape($money($stockGoalSummary['over'])); ?></p>
						<p class="stock-summary__hint"><?php echo $escape($stockGoalSummary['met_count']); ?> of <?php echo $escape($stockGoalSummary['goal_count']); ?> goals met</p>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ($goalRows !== []): ?>
			<section class="stock-card-grid" aria-label="Stocks with a goal">
				<?php foreach ($goalRows as $row): ?>
					<?php
					$barPercent = min(100, (float) $row['percent']);
					if ($row['status'] === 'met') {
						$chipText = $row['over'] > 0 ? '+' . $money($row['over']) . ' over' : 'Goal met';
					} else {
						$chipText = $money($row['left']) . ' left';
					}

					$settlement = $row['settlement'];
					$coveredPercent = 0.0;
					$missedLines = [];

					if ($settlement['covered'] > 0) {
						$coveredPercent = max(min($settlement['covered'] / $row['goal'] * 100, 100 - $barPercent), 0);
						$missedLines = ['Missed this month: ' . $money($settlement['shortfall'])];

						foreach ($settlement['covered_by'] as $payer) {
							$missedLines[] = date('F Y', strtotime($payer['month'])) . ': +' . $money($payer['amount']) . ($payer['extra'] ? ' (extra left over)' : '');
						}

						if ($settlement['still_missing'] > 0) {
							$missedLines[] = 'Still missing: ' . $money($settlement['still_missing']);
						}
					}
					?>
					<article class="stock-card stock-card--<?php echo $escape($row['status']); ?>" style="--stock-color: <?php echo $escape($row['color']); ?>">
						<header class="stock-card__header">
							<div class="stock-card__title">
								<h2 class="stock-card__name"><?php echo $escape($row['name']); ?></h2>
								<?php echo $colorButton($row); ?>
							</div>
							<?php if ($isCurrentStockMonth): ?>
								<button type="button" class="btn-edit js-stock-goal-edit"
									data-note="<?php echo $escape($row['note']); ?>"
									data-name="<?php echo $escape($row['name']); ?>"
									data-goal="<?php echo $escape($row['goal']); ?>"
									aria-label="Edit goal for <?php echo $escape($row['name']); ?>">
									<?php icon('pencil', 'btn-edit__icon'); ?>
								</button>
							<?php endif; ?>
						</header>
						<div class="stock-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $escape(round($barPercent)); ?>">
							<div class="stock-progress__fill" style="width: <?php echo $escape($barPercent); ?>%"></div>
							<?php if ($coveredPercent > 0): ?>
								<?php echo $coverageBar($coveredPercent, 'Covered by later months', $missedLines, $money($settlement['covered']) . ' of the ' . $money($settlement['shortfall']) . ' missed this month is covered', true); ?>
							<?php endif; ?>
						</div>
						<footer class="stock-card__footer">
							<p class="stock-card__amounts">
								<strong><?php echo $escape($money($row['invested'])); ?></strong>
								<span> / <?php echo $escape($money($row['goal'])); ?></span>
							</p>
							<span class="stock-chip stock-chip--<?php echo $escape($row['status']); ?>"><?php echo $escape($chipText); ?></span>
						</footer>
						<?php if ($row['catch_up_left'] > 0): ?>
							<p class="stock-card__note stock-card__note--catch-up" title="<?php echo $escape(implode(', ', array_map(
								static fn(array $carryMonth): string => date('M Y', strtotime($carryMonth['month'])) . ': ' . $money($carryMonth['amount']) . ' missed',
								$row['carry_months']
							))); ?>">
								<?php
								$carryFrom = implode(', ', array_map($carryMonthLabel, $row['carry_months']));
								$missingText = $money($row['catch_up_left']) . ($row['catch_up_left'] < $row['carry_missed'] ? ' still' : '') . ' missing from ' . $carryFrom;

								if ($isCurrentStockMonth) {
									$toGo = $row['invested'] > 0 ? ' ' . $money($row['target'] - $row['invested']) . ' to go.' : '';
									echo $escape($missingText . '. Aim for ' . $money($row['target']) . ' this month to catch up.' . $toGo);
								} else {
									echo $escape($money($row['catch_up_left']) . ' from ' . $carryFrom . ' was still missing this month.');
								}
								?>
							</p>
						<?php endif; ?>
						<?php if ($settlement['covered'] > 0): ?>
							<p class="stock-card__note stock-card__note--covered">
								<?php if ($settlement['still_missing'] > 0): ?>
									<?php echo $escape($money($settlement['covered'])); ?> of the <?php echo $escape($money($settlement['shortfall'])); ?> missed this month is covered by <?php echo $escape(implode(', ', array_map($carryMonthLabel, $settlement['covered_by']))); ?>
									<span class="stock-card__note-missing">· <?php echo $escape($money($settlement['still_missing'])); ?> still missing</span>
								<?php else: ?>
									The <?php echo $escape($money($settlement['shortfall'])); ?> missed this month is covered by <?php echo $escape(implode(', ', array_map($carryMonthLabel, $settlement['covered_by']))); ?>
								<?php endif; ?>
							</p>
						<?php endif; ?>
						<?php if ($settlement['covers_total'] > 0): ?>
							<?php
							$keptExtra = round(max($settlement['surplus'] - $settlement['covers_total'], 0), 2);
							$coverTotal = $settlement['covers_total'] + $keptExtra;
							$coverLines = ['Over this month\'s goal: ' . $money($settlement['surplus'])];
							foreach ($settlement['covers'] as $covered) {
								$coverLines[] = $money($covered['amount']) . ' into ' . date('F Y', strtotime($covered['month'])) . '\'s ' . $money($covered['goal']) . ' goal (' . $money($covered['shortfall']) . ' missed)';
							}
							if ($keptExtra > 0) {
								$coverLines[] = $money($keptExtra) . ' kept as extra for later months';
							}
							?>
							<p class="stock-card__note stock-card__note--covered">
								+<?php echo $escape($money($settlement['covers_total'])); ?> covers missed months: <?php echo $escape(implode(', ', array_map(
									static fn(array $covered): string => date('M', strtotime($covered['month'])) . ' ' . $money($covered['amount']),
									$settlement['covers']
								))); ?>
							</p>
							<?php echo $coverageBar($settlement['covers_total'] / $coverTotal * 100, 'Contributed to missed months', $coverLines, '+' . $money($settlement['covers_total']) . ' covers missed months', false); ?>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php if ($stocksWithoutGoal !== []): ?>
			<h2 class="stock-goals__section-title">No goal yet</h2>
			<section class="stock-card-grid" aria-label="Stocks without a goal">
				<?php foreach ($stocksWithoutGoal as $row): ?>
					<article class="stock-card stock-card--none">
						<header class="stock-card__header">
							<div class="stock-card__title">
								<h2 class="stock-card__name"><?php echo $escape($row['name']); ?></h2>
								<?php echo $colorButton($row); ?>
							</div>
						</header>
						<footer class="stock-card__footer">
							<p class="stock-card__amounts">
								<strong><?php echo $escape($money($row['invested'])); ?></strong>
								<span> invested</span>
							</p>
							<?php if ($isCurrentStockMonth): ?>
								<button type="button" class="btn btn-secondary btn-sm js-stock-goal-edit"
									data-note="<?php echo $escape($row['note']); ?>"
									data-name="<?php echo $escape($row['name']); ?>"
									data-goal="0">Set goal</button>
							<?php endif; ?>
						</footer>
					</article>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php if ($stockGoalRows === []): ?>
			<section class="stock-empty">
				<p class="stock-empty__title">No stocks yet</p>
				<p class="stock-empty__text">Record an investment with a note, or use "Add stock" to plan one before you invest.</p>
			</section>
		<?php endif; ?>

		<section class="stock-chart-card" aria-label="Goal progress over time">
			<h2 class="stock-chart-card__title">Progress over time</h2>
			<?php if ($stockGoalChart['series'] === []): ?>
				<p class="stock-empty__text">Set a goal to start tracking progress month by month.</p>
			<?php else: ?>
				<p class="stock-chart-card__hint js-stock-chart-hint"></p>
				<div class="stock-chart__controls">
					<div class="stock-chart__toggle" role="group" aria-label="Chart type">
						<button type="button" class="stock-chart__toggle-item js-stock-chart-view" data-view="bars">Bars</button>
						<button type="button" class="stock-chart__toggle-item js-stock-chart-view" data-view="cumulative">Over time</button>
					</div>
					<div class="stock-chart__toggle" role="group" aria-label="Values">
						<button type="button" class="stock-chart__toggle-item js-stock-chart-mode" data-mode="percent">% of goal</button>
						<button type="button" class="stock-chart__toggle-item js-stock-chart-mode" data-mode="euro">€ amount</button>
					</div>
				</div>
				<div class="stock-chart__legend js-stock-chart-legend"></div>
				<div class="stock-chart js-stock-chart" role="img" aria-label="Goal progress per stock by month"
					data-chart="<?php echo $escape(json_encode($stockGoalChart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"></div>
			<?php endif; ?>
		</section>
	</main>
</div>

<?php if ($stockGoalRows !== []): ?>
	<div class="modal-overlay js-stock-color-modal" role="dialog" aria-modal="true" aria-labelledby="stock-color-modal-title">
		<div class="modal">
			<div class="modal__header">
				<div>
					<h2 class="modal__title" id="stock-color-modal-title">Stock colour</h2>
					<p class="modal__subtitle">Used on the card and in the progress chart.</p>
				</div>
				<button type="button" class="modal__close js-stock-color-close" aria-label="Close">&times;</button>
			</div>
			<form class="stock-goal-form js-stock-color-form" novalidate>
				<div class="stock-color-picker" role="radiogroup" aria-label="Colour">
					<?php foreach (STOCK_GOAL_COLORS as $paletteColor): ?>
						<button type="button" class="stock-color-picker__swatch js-stock-color-swatch" role="radio" aria-checked="false"
							data-color="<?php echo $escape($paletteColor); ?>"
							style="--swatch-color: <?php echo $escape($paletteColor); ?>"
							aria-label="<?php echo $escape($paletteColor); ?>"></button>
					<?php endforeach; ?>
					<label class="stock-color-picker__custom" title="Custom colour">
						<input type="color" class="js-stock-color-custom" aria-label="Custom colour">
					</label>
				</div>
				<p class="stock-goal-form__error js-stock-color-error" role="alert" hidden></p>
				<div class="stock-goal-form__actions">
					<button type="submit" class="btn btn-primary js-stock-color-save">Save colour</button>
				</div>
			</form>
		</div>
	</div>
<?php endif; ?>

<?php if ($isCurrentStockMonth): ?>
	<div class="modal-overlay js-stock-goal-modal" role="dialog" aria-modal="true" aria-labelledby="stock-goal-modal-title">
		<div class="modal">
			<div class="modal__header">
				<div>
					<h2 class="modal__title" id="stock-goal-modal-title">Stock goal</h2>
					<p class="modal__subtitle js-stock-goal-subtitle">Applies from this month on.</p>
				</div>
				<button type="button" class="modal__close js-stock-goal-close" aria-label="Close">&times;</button>
			</div>
			<form class="stock-goal-form js-stock-goal-form" novalidate>
				<div class="form-field js-stock-goal-name-field" hidden>
					<label for="stock-goal-name">Stock</label>
					<input type="text" id="stock-goal-name" class="js-stock-goal-name" maxlength="<?php echo $escape(STOCK_GOAL_NOTE_MAX_LENGTH); ?>" placeholder="Vanguard S&amp;P 500 (€VUAA)" autocomplete="off">
					<p class="form-field__hint">We recommend this stock note format: Stock name (€TICKER)</p>
				</div>
				<div class="form-field">
					<label for="stock-goal-amount">Monthly goal (€)</label>
					<input type="number" id="stock-goal-amount" class="js-stock-goal-amount" min="0" max="1000000" step="0.01" inputmode="decimal" placeholder="800">
				</div>
				<p class="stock-goal-form__error js-stock-goal-error" role="alert" hidden></p>
				<div class="stock-goal-form__actions">
					<button type="button" class="btn btn-danger js-stock-goal-remove" hidden>Remove goal</button>
					<button type="submit" class="btn btn-primary js-stock-goal-save">Save goal</button>
				</div>
			</form>
		</div>
	</div>
<?php endif; ?>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
