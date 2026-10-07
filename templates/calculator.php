<?php
/** @var string $uri */

require_once __DIR__ . '/partials/icons.php';
?>
<div class="app-shell">
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main calculator">
		<h1 class="page-title">Future Value Calculator</h1>
		<p class="page-subtitle">See how monthly investing grows over time with compound returns.</p>

		<section class="calculator-hero" aria-live="polite">
			<p class="calculator-hero__label">Future Value</p>
			<p class="calculator-hero__value js-calculator-final-portfolio">€0.00</p>
			<div class="calculator-hero__stats">
				<div class="calculator-stat">
					<p class="calculator-stat__label">Total Cash Invested</p>
					<p class="calculator-stat__value js-calculator-total-invested">€0.00</p>
				</div>
				<div class="calculator-stat">
					<p class="calculator-stat__label">Total Interest Earned</p>
					<p class="calculator-stat__value calculator-stat__value--interest js-calculator-total-interest">+€0.00</p>
				</div>
			</div>
		</section>

		<section class="calculator-chart-card">
			<div class="calculator-chart__legend">
				<span class="calculator-chart__legend-item">
					<span class="calculator-chart__legend-dot calculator-chart__legend-dot--portfolio"></span>
					Portfolio Value
				</span>
				<span class="calculator-chart__legend-item">
					<span class="calculator-chart__legend-dot calculator-chart__legend-dot--invested"></span>
					Total Cash Invested
				</span>
			</div>
			<div class="calculator-chart js-calculator-chart" role="img" aria-label="Portfolio growth chart"></div>
		</section>

		<section class="calculator-controls">
			<div class="calculator-control">
				<div class="calculator-control__header">
					<label class="calculator-control__label" for="calculator-principal">Starting Principal</label>
					<input type="number" id="calculator-principal" class="calculator-control__number js-calculator-principal-number" min="0" max="500000" step="100" value="0">
				</div>
				<input type="range" class="calculator-range js-calculator-principal-range" min="0" max="500000" step="100" value="0" aria-label="Starting Principal">
			</div>

			<div class="calculator-control">
				<div class="calculator-control__header">
					<label class="calculator-control__label" for="calculator-contribution">Monthly Contribution</label>
					<div class="calculator-timing-toggle" role="group" aria-label="Contribution timing">
						<button type="button" class="calculator-timing-toggle__option js-calculator-timing" data-timing="start">Start of Month</button>
						<button type="button" class="calculator-timing-toggle__option calculator-timing-toggle__option--active js-calculator-timing" data-timing="end">End of Month</button>
					</div>
				</div>
				<div class="calculator-control__header calculator-control__header--value">
					<span class="calculator-control__hint">Contribution amount</span>
					<input type="number" id="calculator-contribution" class="calculator-control__number js-calculator-contribution-number" min="0" max="10000" step="10" value="100">
				</div>
				<input type="range" class="calculator-range js-calculator-contribution-range" min="0" max="10000" step="10" value="100" aria-label="Monthly Contribution">
			</div>

			<div class="calculator-control">
				<div class="calculator-control__header">
					<label class="calculator-control__label" for="calculator-years">Investment Horizon</label>
					<input type="number" id="calculator-years" class="calculator-control__number js-calculator-years-number" min="1" max="40" step="1" value="10">
				</div>
				<div class="calculator-horizon-pills" role="group" aria-label="Quick horizon selection">
					<button type="button" class="calculator-horizon-pill js-calculator-horizon-pill" data-years="5">5Y</button>
					<button type="button" class="calculator-horizon-pill calculator-horizon-pill--active js-calculator-horizon-pill" data-years="10">10Y</button>
					<button type="button" class="calculator-horizon-pill js-calculator-horizon-pill" data-years="20">20Y</button>
					<button type="button" class="calculator-horizon-pill js-calculator-horizon-pill" data-years="30">30Y</button>
					<button type="button" class="calculator-horizon-pill js-calculator-horizon-pill" data-years="40">40Y</button>
				</div>
				<input type="range" class="calculator-range js-calculator-years-range" min="1" max="40" step="1" value="10" aria-label="Investment Horizon in Years">
			</div>

			<div class="calculator-control">
				<div class="calculator-control__header">
					<label class="calculator-control__label" for="calculator-return">Expected Annual Return</label>
					<input type="number" id="calculator-return" class="calculator-control__number js-calculator-return-number" min="0" max="30" step="0.1" value="10">
				</div>
				<input type="range" class="calculator-range js-calculator-return-range" min="0" max="30" step="0.1" value="10" aria-label="Expected Annual Return Percent">
			</div>
		</section>

		<p class="calculator-disclaimer">Projections are informational only — not financial advice. Assumes a constant return rate with no fees, taxes, or inflation.</p>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
