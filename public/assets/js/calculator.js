const CALCULATOR_STORAGE_KEY = 'php-learn.calculator.state';

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

const roundMoney = (value) => Math.round(value * 100) / 100;

const formatCurrency = (value) => `€${value.toLocaleString(undefined, {
	minimumFractionDigits: 2,
	maximumFractionDigits: 2,
})}`;

const formatInterest = (value) => {
	const prefix = value >= 0 ? '+' : '';

	return `${prefix}${formatCurrency(Math.abs(value))}`;
};

const computeCompoundInterestSchedule = ({
	startingPrincipal,
	monthlyContribution,
	annualReturnPercent,
	years,
	contributionsAtStartOfMonth,
}) => {
	const boundedYears = clamp(Math.round(years), 1, 40);
	const monthlyRate = annualReturnPercent / 100 / 12;
	const totalMonths = boundedYears * 12;
	let balance = startingPrincipal;
	const schedule = [{
		year: 0,
		totalInvested: roundMoney(startingPrincipal),
		portfolioValue: roundMoney(balance),
	}];

	for (let month = 1; month <= totalMonths; month += 1) {
		if (contributionsAtStartOfMonth) {
			balance = (balance + monthlyContribution) * (1 + monthlyRate);
		} else {
			balance = (balance * (1 + monthlyRate)) + monthlyContribution;
		}

		if (month % 12 === 0) {
			schedule.push({
				year: month / 12,
				totalInvested: roundMoney(startingPrincipal + (monthlyContribution * month)),
				portfolioValue: roundMoney(balance),
			});
		}
	}

	return schedule;
};

const computeCompoundInterestSummary = (inputs) => {
	const schedule = computeCompoundInterestSchedule(inputs);
	const final = schedule[schedule.length - 1];

	return {
		finalPortfolio: final.portfolioValue,
		totalCashInvested: final.totalInvested,
		totalInterestEarned: roundMoney(final.portfolioValue - final.totalInvested),
		schedule,
	};
};

const normalizeInputValue = (rawValue, min, max, step) => {
	const parsed = parseFloat(rawValue);

	if (Number.isNaN(parsed)) {
		return null;
	}

	const clamped = clamp(parsed, min, max);
	const stepped = Math.round(clamped / step) * step;
	const decimalPlaces = String(step).includes('.') ? String(step).split('.')[1].length : 0;

	return Number(stepped.toFixed(decimalPlaces));
};

const syncPair = (rangeInput, numberInput) => {
	if (!rangeInput || !numberInput) {
		return;
	}

	const min = parseFloat(rangeInput.min);
	const max = parseFloat(rangeInput.max);
	const step = parseFloat(rangeInput.step) || 1;

	const commitNumberValue = () => {
		const normalized = normalizeInputValue(numberInput.value, min, max, step);

		if (normalized === null) {
			numberInput.value = rangeInput.value;
			recalculate();
			return;
		}

		rangeInput.value = String(normalized);
		numberInput.value = String(normalized);
		recalculate();
	};

	rangeInput.addEventListener('input', () => {
		numberInput.value = rangeInput.value;
	});

	numberInput.addEventListener('input', () => {
		const parsed = parseFloat(numberInput.value);

		if (!Number.isNaN(parsed)) {
			rangeInput.value = String(clamp(parsed, min, max));
		}
	});

	numberInput.addEventListener('change', commitNumberValue);
	numberInput.addEventListener('blur', commitNumberValue);

	numberInput.addEventListener('keydown', (event) => {
		if (event.key === 'Enter') {
			commitNumberValue();
			numberInput.blur();
		}
	});
};

const getChartYearLabels = (maxYear) => {
	if (maxYear <= 20) {
		return Array.from({ length: maxYear + 1 }, (_, index) => index);
	}

	const labels = Array.from({ length: 21 }, (_, index) => index);

	for (let year = 25; year < maxYear; year += 5) {
		labels.push(year);
	}

	if (!labels.includes(maxYear)) {
		labels.push(maxYear);
	}

	return labels;
};

const formatChartYearLabel = (year) => (year === 0 ? '0' : String(year));

const formatAxisCurrency = (value) => {
	if (value >= 1000000) {
		return `€${(value / 1000000).toFixed(1)}M`;
	}

	if (value >= 1000) {
		return `€${Math.round(value / 1000)}k`;
	}

	return `€${Math.round(value)}`;
};

const renderChart = (container, schedule) => {
	if (!container || schedule.length === 0) {
		return;
	}

	const width = 640;
	const height = 260;
	const padding = { top: 16, right: 16, bottom: 32, left: 56 };
	const plotWidth = width - padding.left - padding.right;
	const plotHeight = height - padding.top - padding.bottom;
	const maxValue = Math.max(
		...schedule.map((point) => Math.max(point.totalInvested, point.portfolioValue)),
		1,
	);
	const xForYear = (year, maxYear) => padding.left + ((year / maxYear) * plotWidth);
	const yForValue = (value) => padding.top + plotHeight - ((value / maxValue) * plotHeight);
	const maxYear = schedule[schedule.length - 1].year;

	const investedPoints = schedule
		.map((point) => `${xForYear(point.year, maxYear)},${yForValue(point.totalInvested)}`)
		.join(' ');
	const portfolioPoints = schedule
		.map((point) => `${xForYear(point.year, maxYear)},${yForValue(point.portfolioValue)}`)
		.join(' ');

	const gridLines = [0, 0.25, 0.5, 0.75, 1].map((fraction) => {
		const value = maxValue * fraction;
		const y = yForValue(value);

		return `
			<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" class="calculator-chart__grid-line"></line>
			<text x="${padding.left - 8}" y="${y + 4}" class="calculator-chart__axis-label" text-anchor="end">${formatAxisCurrency(value)}</text>
		`;
	}).join('');

	const xLabels = getChartYearLabels(maxYear).map((year) => {
		const x = xForYear(year, maxYear);

		return `<text x="${x}" y="${height - 8}" class="calculator-chart__axis-label" text-anchor="middle">${formatChartYearLabel(year)}</text>`;
	}).join('');

	container.innerHTML = `
		<svg class="calculator-chart__svg" viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
			${gridLines}
			<polyline points="${investedPoints}" class="calculator-chart__line calculator-chart__line--invested"></polyline>
			<polyline points="${portfolioPoints}" class="calculator-chart__line calculator-chart__line--portfolio"></polyline>
			${xLabels}
		</svg>
	`;
};

const readInputs = () => ({
	startingPrincipal: parseFloat(document.querySelector('.js-calculator-principal-number')?.value ?? '0') || 0,
	monthlyContribution: parseFloat(document.querySelector('.js-calculator-contribution-number')?.value ?? '0') || 0,
	annualReturnPercent: parseFloat(document.querySelector('.js-calculator-return-number')?.value ?? '0') || 0,
	years: parseInt(document.querySelector('.js-calculator-years-number')?.value ?? '10', 10) || 10,
	contributionsAtStartOfMonth: document.querySelector('.js-calculator-timing[data-timing="start"]')?.classList.contains('calculator-timing-toggle__option--active') ?? false,
});

const setSyncedPair = (rangeSelector, numberSelector, value) => {
	const $range = document.querySelector(rangeSelector);
	const $number = document.querySelector(numberSelector);

	if ($range) {
		$range.value = String(value);
	}

	if ($number) {
		$number.value = String(value);
	}
};

const saveCalculatorState = (inputs) => {
	try {
		localStorage.setItem(CALCULATOR_STORAGE_KEY, JSON.stringify(inputs));
	} catch {
		// Ignore quota or private-mode storage errors.
	}
};

const restoreCalculatorState = () => {
	try {
		const raw = localStorage.getItem(CALCULATOR_STORAGE_KEY);

		if (!raw) {
			return;
		}

		const saved = JSON.parse(raw);

		if (typeof saved !== 'object' || saved === null) {
			return;
		}

		if (saved.startingPrincipal !== undefined) {
			setSyncedPair('.js-calculator-principal-range', '.js-calculator-principal-number', saved.startingPrincipal);
		}

		if (saved.monthlyContribution !== undefined) {
			setSyncedPair('.js-calculator-contribution-range', '.js-calculator-contribution-number', saved.monthlyContribution);
		}

		if (saved.annualReturnPercent !== undefined) {
			setSyncedPair('.js-calculator-return-range', '.js-calculator-return-number', saved.annualReturnPercent);
		}

		if (saved.years !== undefined) {
			setSyncedPair('.js-calculator-years-range', '.js-calculator-years-number', saved.years);
		}

		if (typeof saved.contributionsAtStartOfMonth === 'boolean') {
			document.querySelectorAll('.js-calculator-timing').forEach(($option) => {
				const isStart = $option.dataset.timing === 'start';
				$option.classList.toggle(
					'calculator-timing-toggle__option--active',
					saved.contributionsAtStartOfMonth ? isStart : !isStart,
				);
			});
		}
	} catch {
		// Ignore corrupt saved state.
	}
};

const updateHorizonPills = (years) => {
	document.querySelectorAll('.js-calculator-horizon-pill').forEach(($pill) => {
		const isActive = parseInt($pill.dataset.years ?? '0', 10) === years;
		$pill.classList.toggle('calculator-horizon-pill--active', isActive);
	});
};

const recalculate = () => {
	const inputs = readInputs();
	saveCalculatorState(inputs);
	const summary = computeCompoundInterestSummary(inputs);

	const $finalPortfolio = document.querySelector('.js-calculator-final-portfolio');
	const $totalInvested = document.querySelector('.js-calculator-total-invested');
	const $totalInterest = document.querySelector('.js-calculator-total-interest');
	const $chart = document.querySelector('.js-calculator-chart');

	if ($finalPortfolio) {
		$finalPortfolio.textContent = formatCurrency(summary.finalPortfolio);
	}

	if ($totalInvested) {
		$totalInvested.textContent = formatCurrency(summary.totalCashInvested);
	}

	if ($totalInterest) {
		$totalInterest.textContent = formatInterest(summary.totalInterestEarned);
		$totalInterest.classList.toggle('calculator-stat__value--negative', summary.totalInterestEarned < 0);
	}

	renderChart($chart, summary.schedule);
	updateHorizonPills(inputs.years);
};

const bindControls = () => {
	syncPair(
		document.querySelector('.js-calculator-principal-range'),
		document.querySelector('.js-calculator-principal-number'),
	);
	syncPair(
		document.querySelector('.js-calculator-contribution-range'),
		document.querySelector('.js-calculator-contribution-number'),
	);
	syncPair(
		document.querySelector('.js-calculator-years-range'),
		document.querySelector('.js-calculator-years-number'),
	);
	syncPair(
		document.querySelector('.js-calculator-return-range'),
		document.querySelector('.js-calculator-return-number'),
	);

	document.querySelectorAll('.calculator-range, .calculator-control__number').forEach(($input) => {
		$input.addEventListener('input', recalculate);
	});

	document.querySelectorAll('.js-calculator-timing').forEach(($button) => {
		$button.addEventListener('click', () => {
			document.querySelectorAll('.js-calculator-timing').forEach(($option) => {
				$option.classList.toggle(
					'calculator-timing-toggle__option--active',
					$option === $button,
				);
			});
			recalculate();
		});
	});

	document.querySelectorAll('.js-calculator-horizon-pill').forEach(($pill) => {
		$pill.addEventListener('click', () => {
			const years = parseInt($pill.dataset.years ?? '10', 10);
			const $yearsRange = document.querySelector('.js-calculator-years-range');
			const $yearsNumber = document.querySelector('.js-calculator-years-number');

			if ($yearsRange && $yearsNumber) {
				$yearsRange.value = String(years);
				$yearsNumber.value = String(years);
			}

			recalculate();
		});
	});
};

bindControls();
restoreCalculatorState();
recalculate();
