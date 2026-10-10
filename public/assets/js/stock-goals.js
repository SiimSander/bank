const SVG_NS = 'http://www.w3.org/2000/svg';
const CHART_HEIGHT = 328;
const CHART_PADDING = { top: 44, right: 20, bottom: 32, left: 44 };
const MIN_MONTH_WIDTH = 56;
const tickerLabel = (ticker) => ticker.replace(/^\(|\)$/g, '');

const formatMoney = (value) => `€${Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const formatMonthLabel = (month, withYear) => {
	const date = new Date(`${month}T00:00:00`);
	const label = date.toLocaleDateString('en-US', { month: 'short' });

	return withYear ? `${label} ${String(date.getFullYear()).slice(2)}` : label;
};

const formatMonthLong = (month) => new Date(`${month}T00:00:00`).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

const createSvgElement = (tag, attributes = {}) => {
	const element = document.createElementNS(SVG_NS, tag);
	Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value));

	return element;
};

const formatBarLabel = (point) => {
	const invested = Math.round(point.invested);

	return point.goal > 0 ? `${invested}/${Math.round(point.goal)}` : `${invested}`;
};

const TOUCH_SCROLL_LOCK_PX = 28;
const CHART_READOUT_BOTTOM = 38;
const CHART_READOUT_LINE_HEIGHT = 16;
const CHART_READOUT_MARKER_GAP = 14;
const CHART_READOUT_MIN_AMOUNT_Y = 30;
const CHART_READOUT_HALF_WIDTH = 48;

const formatReadoutDate = (date) => date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

const CHART_VIEWS = ['bars', 'cumulative'];
const CHART_MODES = ['percent', 'euro'];
const CHART_STATE_KEY = 'stockGoalsChart';
const CHART_BAR_GAP = 2;
const CHART_BAND_PADDING = 16;
const CHART_HINTS = {
	bars: {
		percent: "Each bar is the share of that stock's monthly goal you invested. The dashed line is 100%, the goal.",
		euro: "Each bar is the amount you invested in that stock that month. The dashed line in the stock's color is its monthly goal.",
	},
	cumulative: {
		percent: 'The line steps up on every entry day. Height is what you have invested so far as a share of the goals planned so far, so 100% means on track. Pick a stock below, then move across the chart to read any day.',
		euro: 'The line steps up on every entry day and shows the total invested in the stock so far, with the lowest and highest total marked. Pick a stock below, then move across the chart to read any day.',
	},
};

const formatEntryDay = (date) => new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

const formatAxisValue = (value, mode) => (mode === 'percent'
	? `${value}%`
	: `€${Number(value).toLocaleString('en-US', { maximumFractionDigits: 0 })}`);

const getNiceScale = (maxValue) => {
	const rough = Math.max(maxValue, 1) / 4;
	const magnitude = 10 ** Math.floor(Math.log10(rough));
	const step = [1, 2, 2.5, 5, 10].map((factor) => factor * magnitude).find((candidate) => candidate >= rough);

	return { step, max: Math.max(Math.ceil(maxValue / step), 1) * step };
};

const seriesLabel = (series) => (series.ticker !== '' ? `${series.name} · ${tickerLabel(series.ticker)}` : series.name);

const loadChartState = () => {
	try {
		const saved = JSON.parse(window.localStorage.getItem(CHART_STATE_KEY) ?? '{}');

		return {
			view: CHART_VIEWS.includes(saved.view) ? saved.view : CHART_VIEWS[0],
			mode: CHART_MODES.includes(saved.mode) ? saved.mode : CHART_MODES[0],
		};
	} catch {
		return { view: CHART_VIEWS[0], mode: CHART_MODES[0] };
	}
};

const saveChartState = (state) => {
	try {
		window.localStorage.setItem(CHART_STATE_KEY, JSON.stringify(state));
	} catch {
		// Storage can be unavailable in private mode; the choice then only lasts for this page view.
	}
};

const initStockGoalsChart = () => {
	const $chart = document.querySelector('.js-stock-chart');
	const $legend = document.querySelector('.js-stock-chart-legend');

	if (!$chart || !$legend) {
		return;
	}

	const $hint = document.querySelector('.js-stock-chart-hint');
	const $viewButtons = [...document.querySelectorAll('.js-stock-chart-view')];
	const $modeButtons = [...document.querySelectorAll('.js-stock-chart-mode')];
	const data = JSON.parse($chart.dataset.chart);
	const hiddenSeries = new Set();
	let selectedIndex = 0;
	const state = loadChartState();
	const $tooltip = document.createElement('div');
	$tooltip.className = 'stock-chart__tooltip';
	$tooltip.hidden = true;

	const $scroll = document.createElement('div');
	$scroll.className = 'stock-chart__scroll';
	$chart.append($scroll, $tooltip);

	const showTooltip = (event, { title, color, lines }) => {
		const rect = $chart.getBoundingClientRect();
		$tooltip.replaceChildren();

		const $title = document.createElement('strong');
		$title.textContent = title;
		$title.style.color = color;
		$tooltip.append($title);

		lines.forEach((line) => {
			const $line = document.createElement('span');
			$line.textContent = line;
			$tooltip.append($line);
		});

		$tooltip.hidden = false;

		const tooltipWidth = $tooltip.offsetWidth;
		const left = Math.min(Math.max(event.clientX - rect.left - tooltipWidth / 2, 0), rect.width - tooltipWidth);
		$tooltip.style.left = `${left}px`;
		const anchorY = event.clientY - rect.top;
		const above = anchorY - $tooltip.offsetHeight - 12;
		$tooltip.style.top = `${above >= 0 ? above : anchorY + 16}px`;
	};

	const hideTooltip = () => {
		$tooltip.hidden = true;
	};

	const bindTooltip = ($element, content) => {
		const show = (event) => showTooltip(event, content);
		const showForMouse = (event) => {
			if (event.pointerType !== 'touch') {
				show(event);
			}
		};
		$element.addEventListener('pointerenter', showForMouse);
		$element.addEventListener('pointermove', showForMouse);
		$element.addEventListener('click', show);
		$element.addEventListener('pointerleave', (event) => {
			if (event.pointerType !== 'touch') {
				hideTooltip();
			}
		});
	};

	const summaryLines = (point) => [
		formatMonthLong(point.month),
		`${formatMoney(point.invested)} of ${formatMoney(point.goal)}${point.percent === null ? '' : ` (${point.percent}%)`}`,
	];

	const getVisibleSeries = () => data.series
		.map((series, index) => ({ series, color: series.color, index }))
		.filter(({ index }) => (state.view === 'cumulative' ? index === selectedIndex : !hiddenSeries.has(index)));

	const getLayout = (bandCount, minBand) => {
		const innerWidth = Math.max($chart.clientWidth - CHART_PADDING.left - CHART_PADDING.right, bandCount * minBand);

		return {
			innerWidth,
			width: innerWidth + CHART_PADDING.left + CHART_PADDING.right,
			band: innerWidth / bandCount,
			innerHeight: CHART_HEIGHT - CHART_PADDING.top - CHART_PADDING.bottom,
		};
	};

	const createChartSvg = (width, height) => {
		const $svg = createSvgElement('svg', { class: 'stock-chart__svg', width, height, viewBox: `0 0 ${width} ${height}` });
		$svg.style.width = `${width}px`;

		return $svg;
	};

	const addMonthLabel = ($svg, month, index, x, y) => {
		const showYear = index === 0 || month.slice(5, 7) === '01';
		const $label = createSvgElement('text', { class: 'calculator-chart__axis-label', x, y, 'text-anchor': 'middle' });
		$label.textContent = formatMonthLabel(month, showYear);
		$svg.append($label);
	};

	const createValueAxis = ($svg, layout, maxValue, mode) => {
		const scale = getNiceScale(mode === 'percent' ? Math.max(maxValue, 100) * 1.05 : Math.max(maxValue * 1.05, 20));
		const yFor = (value) => CHART_PADDING.top + layout.innerHeight - (value / scale.max) * layout.innerHeight;

		for (let value = 0; value <= scale.max + 1e-9; value += scale.step) {
			const y = yFor(value);
			$svg.append(createSvgElement('line', { class: 'calculator-chart__grid-line', x1: CHART_PADDING.left, x2: layout.width - CHART_PADDING.right, y1: y, y2: y }));

			const $label = createSvgElement('text', { class: 'calculator-chart__axis-label', x: CHART_PADDING.left - 8, y: y + 4, 'text-anchor': 'end' });
			$label.textContent = formatAxisValue(Math.round(value * 100) / 100, mode);
			$svg.append($label);
		}

		if (mode === 'percent') {
			$svg.append(createSvgElement('line', {
				class: 'stock-chart__goal-line',
				x1: CHART_PADDING.left,
				x2: layout.width - CHART_PADDING.right,
				y1: yFor(100),
				y2: yFor(100),
			}));
		}

		return yFor;
	};

	const drawBars = (visible, mode) => {
		const months = data.months;
		const count = Math.max(visible.length, 1);
		const layout = getLayout(months.length, Math.max(MIN_MONTH_WIDTH, count * 16 + CHART_BAND_PADDING));
		const valueOf = (point) => {
			if (mode === 'percent') {
				return point.percent;
			}

			return point.goal > 0 ? point.invested : null;
		};

		const maxValue = Math.max(0, ...visible.flatMap(({ series }) => series.points.flatMap((point) => [valueOf(point) ?? 0, mode === 'euro' ? point.goal : 0])));
		const $svg = createChartSvg(layout.width, CHART_HEIGHT);
		const yFor = createValueAxis($svg, layout, maxValue, mode);

		const barWidth = Math.min(24, (layout.band - CHART_BAND_PADDING) / count - CHART_BAR_GAP);
		const goalPaths = new Map();

		const focusGoalLine = (seriesKey) => {
			$svg.classList.toggle('stock-chart__svg--focus-goal', seriesKey !== null);
			goalPaths.forEach(($paths, key) => $paths.forEach(($path) => $path.classList.toggle('stock-chart__goal-path--active', key === seriesKey)));
		};

		$svg.addEventListener('click', (event) => {
			if (event.target.closest('.stock-chart__bar') === null) {
				hideTooltip();
				focusGoalLine(null);
			}
		});

		months.forEach((month, monthIndex) => {
			addMonthLabel($svg, month, monthIndex, CHART_PADDING.left + layout.band * (monthIndex + 0.5), CHART_HEIGHT - 10);

			const monthSeries = visible
				.filter(({ series }) => (valueOf(series.points[monthIndex]) ?? 0) > 0)
				.sort((a, b) => b.series.points[monthIndex].invested - a.series.points[monthIndex].invested);
			const monthGroupWidth = monthSeries.length * barWidth + Math.max(monthSeries.length - 1, 0) * CHART_BAR_GAP;
			const groupStart = CHART_PADDING.left + layout.band * monthIndex + (layout.band - monthGroupWidth) / 2;

			monthSeries.forEach(({ series, color, index }, seriesIndex) => {
				const point = series.points[monthIndex];
				const value = valueOf(point);
				const x = groupStart + seriesIndex * (barWidth + CHART_BAR_GAP);

				const $bar = createSvgElement('rect', {
					class: 'stock-chart__bar',
					x,
					y: yFor(value),
					width: barWidth,
					height: yFor(0) - yFor(value),
					rx: 3,
					fill: color,
				});
				bindTooltip($bar, { title: seriesLabel(series), color, lines: summaryLines(point) });

				if (mode === 'euro') {
					$bar.addEventListener('pointerenter', (event) => {
						if (event.pointerType !== 'touch') {
							focusGoalLine(index);
						}
					});
					$bar.addEventListener('pointerleave', (event) => {
						if (event.pointerType !== 'touch') {
							focusGoalLine(null);
						}
					});
					$bar.addEventListener('click', () => focusGoalLine(index));
				}

				$svg.append($bar);

				const labelX = x + barWidth / 2;
				const $label = createSvgElement('text', {
					class: 'stock-chart__bar-label',
					x: labelX,
					y: yFor(value) - 5,
					transform: `rotate(-90 ${labelX} ${yFor(value) - 5})`,
					fill: color,
				});
				$label.textContent = formatBarLabel(point);
				$svg.append($label);
			});
		});

		if (mode === 'euro') {
			const buildLevelPath = (levels) => levels
				.map((level, monthIndex) => {
					if (level === null) {
						return '';
					}

					const x0 = CHART_PADDING.left + layout.band * monthIndex;
					const y = yFor(level);
					const continues = monthIndex > 0 && levels[monthIndex - 1] !== null;

					return `${continues ? `V ${y}` : `M ${x0} ${y}`} H ${x0 + layout.band}`;
				})
				.join(' ');

			visible.forEach(({ series, color, index }, seriesIndex) => {
				const goals = series.points.map((point) => point.goal);
				const firstGoal = goals.find((goal) => goal > 0) ?? null;
				let lastGoal = null;

				const goalLevels = goals.map((goal) => (goal > 0 ? goal : null));
				const idleLevels = goals.map((goal) => {
					if (goal > 0) {
						lastGoal = goal;

						return null;
					}

					return lastGoal ?? firstGoal;
				});

				const paths = [
					{ d: buildLevelPath(goalLevels), className: 'stock-chart__goal-path', stroke: color },
					{ d: buildLevelPath(idleLevels), className: 'stock-chart__goal-path stock-chart__goal-path--idle', stroke: null },
				];

				paths.forEach(({ d, className, stroke }) => {
					if (d.trim() === '') {
						return;
					}

					const $goalPath = createSvgElement('path', {
						class: className,
						d,
						'stroke-dashoffset': seriesIndex * 3,
						...(stroke === null ? {} : { stroke }),
					});
					goalPaths.set(index, [...(goalPaths.get(index) ?? []), $goalPath]);
					$svg.append($goalPath);
				});
			});
		}

		return $svg;
	};

	const drawCumulative = (visible, mode) => {
		const months = data.months;
		const layout = getLayout(months.length, MIN_MONTH_WIDTH);
		const toTime = (date) => new Date(`${date}T00:00:00`).getTime();
		const domainStart = toTime(months[0]);
		const lastMonth = new Date(`${months[months.length - 1]}T00:00:00`);
		const domainEnd = new Date(lastMonth.getFullYear(), lastMonth.getMonth() + 1, 1).getTime();
		const xFor = (time) => CHART_PADDING.left + ((time - domainStart) / (domainEnd - domainStart)) * layout.innerWidth;

		const lines = visible.map(({ series, color }) => {
			const events = [];
			let invested = 0;
			let planned = 0;

			series.points.forEach((point) => {
				planned += point.goal;
				events.push({ time: toTime(point.month), invested, planned, entry: null });

				point.entries.forEach((entry) => {
					invested = Math.round((invested + entry.amount) * 100) / 100;
					events.push({ time: toTime(entry.date), invested, planned, entry });
				});
			});

			const valueOf = (event) => {
				if (mode === 'euro') {
					return event.invested;
				}

				return event.planned > 0 ? (event.invested / event.planned) * 100 : null;
			};
			const firstActive = events.findIndex((event) => (mode === 'euro' ? event.planned > 0 || event.invested > 0 : event.planned > 0));
			const active = firstActive === -1 ? [] : events.slice(firstActive).map((event) => ({ ...event, value: valueOf(event) }));
			const lastEntryTime = Math.max(0, ...active.filter((event) => event.entry !== null).map((event) => event.time));

			if (active.length > 0) {
				const last = active[active.length - 1];
				active.push({ ...last, time: Math.max(Math.min(Date.now(), domainEnd), lastEntryTime), entry: null });
			}

			return { series, color, events: active };
		});

		const maxValue = Math.max(0, ...lines.flatMap((line) => line.events.map((event) => event.value ?? 0)));
		const $svg = createChartSvg(layout.width, CHART_HEIGHT);
		const yFor = createValueAxis($svg, layout, maxValue, mode);

		months.forEach((month, monthIndex) => {
			const start = toTime(month);
			const end = monthIndex + 1 < months.length ? toTime(months[monthIndex + 1]) : domainEnd;
			addMonthLabel($svg, month, monthIndex, xFor((start + end) / 2), CHART_HEIGHT - 10);
		});

		const line = lines.find((candidate) => candidate.events.length > 0);

		if (!line) {
			return $svg;
		}

		const { series, color, events } = line;
		const path = events
			.map((event, index) => {
				const x = xFor(event.time);
				const y = yFor(event.value ?? 0);

				return index === 0 ? `M ${x} ${y}` : `H ${x} V ${y}`;
			})
			.join(' ');

		$svg.append(createSvgElement('path', { class: 'stock-chart__step', d: path, stroke: color }));

		const highest = events.reduce((best, event) => (event.value > best.value ? event : best), events[0]);

		if (mode === 'euro') {
			const lowest = events.find((event) => event.value > 0);
			const extremes = [{ label: 'High', event: highest, above: true }];

			if (lowest && lowest !== highest) {
				extremes.push({ label: 'Low', event: lowest, above: false });
			}

			extremes.forEach(({ label, event, above }) => {
				const x = xFor(event.time);
				const y = yFor(event.value);
				const nearRightEdge = x > layout.width - CHART_PADDING.right - 90;
				const $label = createSvgElement('text', {
					class: 'stock-chart__extreme-label',
					x: above ? x + (nearRightEdge ? 6 : 0) : x + 12,
					y: above ? Math.max(y - 14, CHART_PADDING.top + 10) : Math.min(y + 22, CHART_HEIGHT - CHART_PADDING.bottom - 6),
					'text-anchor': above ? (nearRightEdge ? 'end' : 'middle') : 'start',
					fill: color,
				});
				$label.textContent = `${label} ${formatMoney(event.value)}`;
				$svg.append($label);
			});
		}

		const $cursor = createSvgElement('line', {
			class: 'stock-chart__cursor',
			y1: mode === 'euro' ? CHART_READOUT_BOTTOM : CHART_PADDING.top,
			y2: CHART_HEIGHT - CHART_PADDING.bottom,
		});
		const $marker = createSvgElement('circle', { class: 'stock-chart__marker', r: 5, fill: color });
		const $readoutDate = createSvgElement('text', { class: 'stock-chart__readout stock-chart__readout--date', y: 14, 'text-anchor': 'middle' });
		const $readoutAmount = createSvgElement('text', { class: 'stock-chart__readout stock-chart__readout--amount', y: 30, 'text-anchor': 'middle', style: `fill: ${color}` });
		const $scrub = createSvgElement('rect', {
			class: 'stock-chart__scrub',
			x: CHART_PADDING.left,
			y: CHART_PADDING.top,
			width: layout.innerWidth,
			height: layout.innerHeight,
		});
		$svg.append($cursor, $marker, $readoutDate, $readoutAmount, $scrub);

		const firstTime = events[0].time;
		const lastTime = events[events.length - 1].time;

		const showAt = (event) => {
			const rect = $svg.getBoundingClientRect();
			const pointerTime = domainStart + ((event.clientX - rect.left - CHART_PADDING.left) / layout.innerWidth) * (domainEnd - domainStart);
			const time = Math.min(Math.max(pointerTime, firstTime), lastTime);
			const current = events.filter((candidate) => candidate.time <= time).pop();
			const lastEntry = events.filter((candidate) => candidate.entry !== null && candidate.time <= time).pop();
			const hoveredDate = new Date(time);
			const day = [hoveredDate.getFullYear(), hoveredDate.getMonth() + 1, hoveredDate.getDate()]
				.map((part) => String(part).padStart(2, '0'))
				.join('-');
			const tooltipLines = [formatEntryDay(day), `${formatMoney(current.invested)} invested so far`];

			tooltipLines.push(current.planned > 0
				? `${Math.round((current.invested / current.planned) * 1000) / 10}% of ${formatMoney(current.planned)} planned so far`
				: 'No goal yet');

			if (lastEntry) {
				tooltipLines.push(`Last entry: ${formatEntryDay(lastEntry.entry.date)} · ${formatMoney(lastEntry.entry.amount)}`);
			}

			const x = xFor(time);
			const y = yFor(current.value ?? 0);
			$cursor.setAttribute('x1', x);
			$cursor.setAttribute('x2', x);
			$marker.setAttribute('cx', x);
			$marker.setAttribute('cy', y);
			$svg.classList.add('stock-chart__svg--hovering');

			if (mode === 'euro') {
				const readoutX = Math.min(Math.max(x, CHART_READOUT_HALF_WIDTH), layout.width - CHART_READOUT_HALF_WIDTH);
				const amountY = Math.max(yFor(highest.value) - CHART_READOUT_MARKER_GAP, CHART_READOUT_MIN_AMOUNT_Y);
				$readoutDate.setAttribute('x', readoutX);
				$readoutDate.setAttribute('y', amountY - CHART_READOUT_LINE_HEIGHT);
				$readoutAmount.setAttribute('x', readoutX);
				$readoutAmount.setAttribute('y', amountY);
				$cursor.setAttribute('y1', amountY + 8);
				$readoutDate.textContent = formatReadoutDate(hoveredDate);
				$readoutAmount.textContent = formatMoney(current.invested);

				return;
			}

			showTooltip({ clientX: rect.left + x, clientY: rect.top + y }, { title: seriesLabel(series), color, lines: tooltipLines });
		};

		const stopHovering = () => {
			$svg.classList.remove('stock-chart__svg--hovering');
			hideTooltip();
		};

		const showForMouse = (event) => {
			if (event.pointerType !== 'touch') {
				showAt(event);
			}
		};

		$scrub.addEventListener('pointerenter', showForMouse);
		$scrub.addEventListener('pointermove', showForMouse);
		$scrub.addEventListener('click', showAt);
		$scrub.addEventListener('pointerleave', (event) => {
			if (event.pointerType === 'mouse') {
				stopHovering();
			}
		});

		let touch = null;

		$svg.addEventListener('pointerdown', (event) => {
			if (event.pointerType !== 'touch') {
				return;
			}

			touch = { startX: event.clientX, startY: event.clientY, lastY: event.clientY, scrolling: false };
			showAt(event);
		});

		$svg.addEventListener('pointermove', (event) => {
			if (event.pointerType !== 'touch' || touch === null) {
				return;
			}

			if (!touch.scrolling) {
				const movedX = Math.abs(event.clientX - touch.startX);
				const movedY = Math.abs(event.clientY - touch.startY);

				if (movedY > TOUCH_SCROLL_LOCK_PX && movedY > movedX * 2) {
					touch.scrolling = true;
					stopHovering();
				}
			}

			if (touch.scrolling) {
				window.scrollBy(0, touch.lastY - event.clientY);
				touch.lastY = event.clientY;

				return;
			}

			showAt(event);
		});

		const endTouch = () => {
			touch = null;
		};

		$svg.addEventListener('pointerup', endTouch);
		$svg.addEventListener('pointercancel', endTouch);

		return $svg;
	};

	const renderers = { bars: drawBars, cumulative: drawCumulative };

	const draw = () => {
		hideTooltip();
		$scroll.replaceChildren(renderers[state.view](getVisibleSeries(), state.mode));
		$scroll.scrollLeft = $scroll.scrollWidth;
		$scroll.classList.toggle('stock-chart__scroll--lock-touch', state.view === 'cumulative' && $scroll.scrollWidth <= $scroll.clientWidth + 1);
	};

	const syncControls = () => {
		$viewButtons.forEach(($button) => $button.setAttribute('aria-pressed', $button.dataset.view === state.view ? 'true' : 'false'));
		$modeButtons.forEach(($button) => $button.setAttribute('aria-pressed', $button.dataset.mode === state.mode ? 'true' : 'false'));

		if ($hint) {
			$hint.textContent = CHART_HINTS[state.view][state.mode];
		}
	};

	$viewButtons.forEach(($button) => $button.addEventListener('click', () => {
		state.view = $button.dataset.view;
		saveChartState(state);
		syncControls();
		drawLegend();
		draw();
	}));

	$modeButtons.forEach(($button) => $button.addEventListener('click', () => {
		state.mode = $button.dataset.mode;
		saveChartState(state);
		syncControls();
		draw();
	}));

	const drawLegend = () => {
		$legend.replaceChildren();

		data.series.forEach((series, index) => {
			const $button = document.createElement('button');
			$button.type = 'button';
			$button.className = 'stock-chart__legend-item';
			const isActive = state.view === 'cumulative' ? index === selectedIndex : !hiddenSeries.has(index);
			$button.setAttribute('aria-pressed', isActive ? 'true' : 'false');

			const $dot = document.createElement('span');
			$dot.className = 'calculator-chart__legend-dot';
			$dot.style.backgroundColor = series.color;

			const $name = document.createElement('span');
			$name.textContent = series.ticker !== '' ? tickerLabel(series.ticker) : series.name;
			$name.title = series.note;

			$button.append($dot, $name);
			$button.addEventListener('click', () => {
				if (state.view === 'cumulative') {
					selectedIndex = index;
				} else if (hiddenSeries.has(index)) {
					hiddenSeries.delete(index);
				} else {
					hiddenSeries.add(index);
				}

				drawLegend();
				draw();
			});

			$legend.append($button);
		});
	};

	let lastWidth = $chart.clientWidth;
	window.addEventListener('resize', () => {
		if ($chart.clientWidth !== lastWidth) {
			lastWidth = $chart.clientWidth;
			draw();
		}
	});

	drawLegend();
	syncControls();
	draw();
};

const initStockGoalModal = () => {
	const $modal = document.querySelector('.js-stock-goal-modal');

	if (!$modal) {
		return;
	}

	const $form = $modal.querySelector('.js-stock-goal-form');
	const $title = $modal.querySelector('#stock-goal-modal-title');
	const $subtitle = $modal.querySelector('.js-stock-goal-subtitle');
	const $amount = $modal.querySelector('.js-stock-goal-amount');
	const $error = $modal.querySelector('.js-stock-goal-error');
	const $remove = $modal.querySelector('.js-stock-goal-remove');
	const $save = $modal.querySelector('.js-stock-goal-save');
	const $nameField = $modal.querySelector('.js-stock-goal-name-field');
	const $name = $modal.querySelector('.js-stock-goal-name');

	let editedNote = null;
	let isAddingStock = false;

	const showError = (message) => {
		$error.textContent = message;
		$error.hidden = message === '';
	};

	const open = () => {
		showError('');
		$modal.classList.add('modal-overlay--open');
		document.body.classList.add('modal-open');
	};

	const close = () => {
		$modal.classList.remove('modal-overlay--open');
		document.body.classList.remove('modal-open');
	};

	const openForAdd = () => {
		isAddingStock = true;
		editedNote = null;
		$title.textContent = 'Add stock';
		$subtitle.textContent = 'Plan a stock before you invest in it. Applies from this month on.';
		$nameField.hidden = false;
		$name.value = '';
		$amount.value = '';
		$remove.hidden = true;
		open();
		$name.focus();
	};

	const openForEdit = ({ note, name, goal }) => {
		isAddingStock = false;
		$nameField.hidden = true;
		editedNote = note;
		$title.textContent = Number(goal) > 0 ? `Edit goal: ${name}` : `Set goal: ${name}`;
		$subtitle.textContent = 'Applies from this month on. Past months keep their old goal.';
		$amount.value = Number(goal) > 0 ? goal : '';
		$remove.hidden = Number(goal) <= 0;
		open();
		$amount.focus();
	};

	const submit = async (amount, note) => {
		$save.disabled = true;
		$remove.disabled = true;

		try {
			const response = await fetch('/stock-goals/save', {
				method: 'POST',
				headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
				body: `note=${encodeURIComponent(note)}&amount=${encodeURIComponent(amount)}${isAddingStock ? '&new_stock=1' : ''}`,
			});
			const result = await response.json().catch(() => ({}));

			if (response.ok && result.success) {
				window.location.reload();

				return;
			}

			showError(result.error ?? 'Could not save the goal. Please try again.');
		} catch {
			showError('Could not save the goal. Please try again.');
		}

		$save.disabled = false;
		$remove.disabled = false;
	};

	document.querySelectorAll('.js-stock-goal-edit').forEach(($button) => {
		$button.addEventListener('click', () => openForEdit($button.dataset));
	});

	document.querySelectorAll('.js-stock-goal-add').forEach(($button) => {
		$button.addEventListener('click', openForAdd);
	});

	$form.addEventListener('submit', (event) => {
		event.preventDefault();

		if (isAddingStock && $name.value.trim() === '') {
			showError('Enter the stock name.');

			return;
		}

		const amount = parseFloat($amount.value);

		if (Number.isNaN(amount) || amount <= 0) {
			showError('Enter a monthly goal above 0.');

			return;
		}

		submit(amount, isAddingStock ? $name.value.trim() : editedNote);
	});

	$remove.addEventListener('click', () => {
		submit(0, editedNote);
	});

	$modal.querySelector('.js-stock-goal-close').addEventListener('click', close);
	$modal.addEventListener('click', (event) => {
		if (event.target === $modal) {
			close();
		}
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && $modal.classList.contains('modal-overlay--open')) {
			close();
		}
	});
};

const initStockRing = () => {
	const $ring = document.querySelector('.js-stock-ring');
	const $tooltip = document.querySelector('.js-stock-ring-tooltip');

	if (!$ring || !$tooltip) {
		return;
	}

	const $sectors = [...$ring.querySelectorAll('.js-stock-ring-sector')];
	let activeSector = null;

	const formatPercent = (value) => `${Number(value).toLocaleString('en-US', { maximumFractionDigits: 1 })}%`;

	const fillTooltip = ({ name, ticker, color, invested, goal, percent, contribution, overall }) => {
		const $title = document.createElement('strong');
		$title.textContent = ticker !== '' ? `${name} · ${ticker}` : name;
		$title.style.color = color;

		const $amounts = document.createElement('span');
		$amounts.className = 'stock-ring__tooltip-amounts';
		$amounts.textContent = `${formatMoney(invested)} / ${formatMoney(goal)}`;

		const $own = document.createElement('span');
		$own.className = 'stock-ring__tooltip-note';
		$own.textContent = `${formatPercent(percent)} of its goal`;

		const $share = document.createElement('span');
		$share.className = 'stock-ring__tooltip-note';
		$share.textContent = `${formatPercent(contribution)} of the overall ${formatPercent(overall)}`;

		$tooltip.replaceChildren($title, $amounts, $own, $share);
	};

	const placeTooltip = (x, y) => {
		const width = $tooltip.offsetWidth;
		const height = $tooltip.offsetHeight;
		const left = Math.min(Math.max(x - width / 2, 8), window.innerWidth - width - 8);
		const above = y - height - 16;

		$tooltip.style.left = `${left}px`;
		$tooltip.style.top = `${above >= 8 ? above : y + 20}px`;
	};

	const activate = ($sector, x, y) => {
		if (activeSector && activeSector !== $sector) {
			activeSector.classList.remove('stock-ring__sector--active');
		}

		activeSector = $sector;
		$sector.classList.add('stock-ring__sector--active');
		fillTooltip($sector.dataset);
		$tooltip.hidden = false;

		if (x === undefined) {
			const rect = $sector.getBoundingClientRect();
			placeTooltip(rect.left + rect.width / 2, rect.top + rect.height / 2);

			return;
		}

		placeTooltip(x, y);
	};

	const deactivate = () => {
		if (activeSector) {
			activeSector.classList.remove('stock-ring__sector--active');
		}

		activeSector = null;
		$tooltip.hidden = true;
	};

	$sectors.forEach(($sector) => {
		$sector.addEventListener('pointerenter', (event) => {
			if (event.pointerType === 'mouse') {
				activate($sector, event.clientX, event.clientY);
			}
		});
		$sector.addEventListener('pointermove', (event) => {
			if (event.pointerType === 'mouse' && activeSector === $sector) {
				placeTooltip(event.clientX, event.clientY);
			}
		});
		$sector.addEventListener('pointerleave', (event) => {
			if (event.pointerType === 'mouse' && activeSector === $sector) {
				deactivate();
			}
		});
		$sector.addEventListener('click', (event) => {
			event.stopPropagation();

			if (['touch', 'pen'].includes(event.pointerType) && activeSector === $sector) {
				deactivate();

				return;
			}

			activate($sector, event.clientX || undefined, event.clientY || undefined);
		});
		$sector.addEventListener('focus', () => {
			if ($sector.matches(':focus-visible')) {
				activate($sector);
			}
		});
		$sector.addEventListener('blur', deactivate);
	});

	document.addEventListener('click', deactivate);
	document.addEventListener('scroll', deactivate, { passive: true });
};

const initStockCoverage = () => {
	const $bars = [...document.querySelectorAll('.js-stock-coverage')];

	if ($bars.length === 0) {
		return;
	}

	const $tooltip = document.createElement('div');
	$tooltip.className = 'stock-coverage-tooltip';
	$tooltip.hidden = true;
	document.body.append($tooltip);

	let activeBar = null;

	const place = (x, y) => {
		const width = $tooltip.offsetWidth;
		const height = $tooltip.offsetHeight;
		const above = y - height - 16;

		$tooltip.style.left = `${Math.min(Math.max(x - width / 2, 8), window.innerWidth - width - 8)}px`;
		$tooltip.style.top = `${above >= 8 ? above : y + 20}px`;
	};

	const activate = ($bar, x, y) => {
		const $title = document.createElement('strong');
		$title.textContent = $bar.dataset.title;

		const $lines = JSON.parse($bar.dataset.lines).map((text) => {
			const $line = document.createElement('span');
			$line.textContent = text;

			return $line;
		});

		$tooltip.style.setProperty('--stock-color', getComputedStyle($bar).getPropertyValue('--stock-color'));
		$tooltip.replaceChildren($title, ...$lines);
		$tooltip.hidden = false;
		activeBar = $bar;

		if (x === undefined) {
			const rect = $bar.getBoundingClientRect();
			place(rect.left + rect.width / 2, rect.top);

			return;
		}

		place(x, y);
	};

	const deactivate = () => {
		activeBar = null;
		$tooltip.hidden = true;
	};

	$bars.forEach(($bar) => {
		$bar.addEventListener('pointerenter', (event) => {
			if (event.pointerType === 'mouse') {
				activate($bar, event.clientX, event.clientY);
			}
		});
		$bar.addEventListener('pointermove', (event) => {
			if (event.pointerType === 'mouse' && activeBar === $bar) {
				place(event.clientX, event.clientY);
			}
		});
		$bar.addEventListener('pointerleave', (event) => {
			if (event.pointerType === 'mouse') {
				deactivate();
			}
		});
		$bar.addEventListener('click', (event) => {
			event.stopPropagation();

			if (['touch', 'pen'].includes(event.pointerType) && activeBar === $bar) {
				deactivate();

				return;
			}

			activate($bar, event.clientX || undefined, event.clientY || undefined);
		});
		$bar.addEventListener('focus', () => {
			if ($bar.matches(':focus-visible')) {
				activate($bar);
			}
		});
		$bar.addEventListener('blur', deactivate);
	});

	document.addEventListener('click', deactivate);
	document.addEventListener('scroll', deactivate, { passive: true });
};

const initStockColorModal = () => {
	const $modal = document.querySelector('.js-stock-color-modal');

	if (!$modal) {
		return;
	}

	const $form = $modal.querySelector('.js-stock-color-form');
	const $title = $modal.querySelector('#stock-color-modal-title');
	const $swatches = [...$modal.querySelectorAll('.js-stock-color-swatch')];
	const $custom = $modal.querySelector('.js-stock-color-custom');
	const $error = $modal.querySelector('.js-stock-color-error');
	const $save = $modal.querySelector('.js-stock-color-save');

	let editedNote = null;
	let selectedColor = null;

	const showError = (message) => {
		$error.textContent = message;
		$error.hidden = message === '';
	};

	const select = (color) => {
		selectedColor = color.toLowerCase();
		$custom.value = selectedColor;

		let matchesSwatch = false;
		$swatches.forEach(($swatch) => {
			const isSelected = $swatch.dataset.color === selectedColor;
			matchesSwatch = matchesSwatch || isSelected;
			$swatch.classList.toggle('stock-color-picker__swatch--selected', isSelected);
			$swatch.setAttribute('aria-checked', isSelected ? 'true' : 'false');
		});

		$custom.closest('.stock-color-picker__custom').classList.toggle('stock-color-picker__custom--selected', !matchesSwatch);
	};

	const close = () => {
		$modal.classList.remove('modal-overlay--open');
		document.body.classList.remove('modal-open');
	};

	const openForEdit = ({ note, name, color }) => {
		editedNote = note;
		$title.textContent = `Colour: ${name}`;
		select(color);
		showError('');
		$modal.classList.add('modal-overlay--open');
		document.body.classList.add('modal-open');
	};

	document.querySelectorAll('.js-stock-color-edit').forEach(($button) => {
		$button.addEventListener('click', () => openForEdit($button.dataset));
	});

	$swatches.forEach(($swatch) => {
		$swatch.addEventListener('click', () => select($swatch.dataset.color));
	});

	$custom.addEventListener('input', () => select($custom.value));

	$form.addEventListener('submit', async (event) => {
		event.preventDefault();
		$save.disabled = true;

		try {
			const response = await fetch('/stock-goals/color', {
				method: 'POST',
				headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
				body: `note=${encodeURIComponent(editedNote)}&color=${encodeURIComponent(selectedColor)}`,
			});
			const result = await response.json().catch(() => ({}));

			if (response.ok && result.success) {
				window.location.reload();

				return;
			}

			showError(result.error ?? 'Could not save the colour. Please try again.');
		} catch {
			showError('Could not save the colour. Please try again.');
		}

		$save.disabled = false;
	});

	$modal.querySelector('.js-stock-color-close').addEventListener('click', close);
	$modal.addEventListener('click', (event) => {
		if (event.target === $modal) {
			close();
		}
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && $modal.classList.contains('modal-overlay--open')) {
			close();
		}
	});
};

const MONTH_SLIDE_DURATION = 450;
const MONTH_WINDOW_SIZE = 3;

const initStockMonthSwitcher = () => {
	const $strip = document.querySelector('.js-stock-month-strip');
	const $track = document.querySelector('.js-stock-month-track');

	if (!$strip || !$track) {
		return;
	}

	const $items = [...$track.querySelectorAll('.stock-month-switcher__item')];
	const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	let isNavigating = false;

	const windowStartFor = (index) => Math.max(0, Math.min(index - Math.floor(MONTH_WINDOW_SIZE / 2), $items.length - MONTH_WINDOW_SIZE));

	document.querySelectorAll('.js-stock-month-link').forEach(($link) => {
		$link.addEventListener('click', (event) => {
			if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}

			event.preventDefault();

			const index = Number($link.dataset.index);
			const isStripVisible = $strip.offsetParent !== null;

			if (isNavigating || !$items[index]) {
				return;
			}

			isNavigating = true;

			if (!isStripVisible || prefersReducedMotion) {
				window.location.href = $link.href;

				return;
			}

			$items.forEach(($item, itemIndex) => {
				$item.classList.toggle('stock-month-switcher__item--active', itemIndex === index);
			});
			$strip.classList.add('stock-month-switcher__strip--moving');
			$track.style.setProperty('--stock-month-start', windowStartFor(index));

			window.setTimeout(() => {
				window.location.href = $link.href;
			}, MONTH_SLIDE_DURATION);
		});
	});

	window.addEventListener('pageshow', (event) => {
		if (event.persisted) {
			window.location.reload();
		}
	});
};

initStockMonthSwitcher();
initStockGoalsChart();
initStockGoalModal();
initStockColorModal();
initStockRing();
initStockCoverage();
