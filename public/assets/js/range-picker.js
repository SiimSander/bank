// Relies on the date helpers declared in date-picker.js, which must load first.

const formatShortRangeDate = (date) => {
	const day = String(date.getDate()).padStart(2, '0');
	const month = String(date.getMonth() + 1).padStart(2, '0');
	const year = String(date.getFullYear()).slice(-2);

	return `${day}.${month}.${year}`;
};

const closeRangePicker = ($picker) => {
	$picker.classList.remove('range-picker--open');
	$picker.querySelector('.js-range-picker-trigger')?.setAttribute('aria-expanded', 'false');

	const $popover = $picker.querySelector('.range-picker__popover');

	if ($popover) {
		$popover.hidden = true;
	}
};

const closeAllRangePickers = (except = null) => {
	document.querySelectorAll('.js-range-picker').forEach(($picker) => {
		if ($picker !== except) {
			closeRangePicker($picker);
		}
	});
};

const initRangePicker = ($picker) => {
	const $trigger = $picker.querySelector('.js-range-picker-trigger');

	if (!$trigger || $picker.dataset.initialized === 'true') {
		return;
	}

	$picker.dataset.initialized = 'true';

	const baseUrl = $picker.dataset.baseUrl;
	const extraParams = JSON.parse($picker.dataset.extraParams || '{}');
	const maxDate = parseIsoDate($picker.dataset.max) ?? new Date();
	let rangeStart = parseIsoDate($picker.dataset.from);
	let rangeEnd = parseIsoDate($picker.dataset.to);
	let hoverDate = null;
	let viewDate = new Date((rangeEnd ?? maxDate).getFullYear(), (rangeEnd ?? maxDate).getMonth(), 1);

	const $popover = document.createElement('div');
	$popover.className = 'date-picker__popover range-picker__popover';
	$popover.setAttribute('role', 'dialog');
	$popover.hidden = true;

	const $header = document.createElement('div');
	$header.className = 'date-picker__header';

	const $prevBtn = document.createElement('button');
	$prevBtn.type = 'button';
	$prevBtn.className = 'date-picker__nav';
	$prevBtn.setAttribute('aria-label', 'Previous month');
	$prevBtn.textContent = '‹';

	const $title = document.createElement('div');
	$title.className = 'date-picker__title';

	const $nextBtn = document.createElement('button');
	$nextBtn.type = 'button';
	$nextBtn.className = 'date-picker__nav';
	$nextBtn.setAttribute('aria-label', 'Next month');
	$nextBtn.textContent = '›';

	$header.append($prevBtn, $title, $nextBtn);

	const $weekdays = document.createElement('div');
	$weekdays.className = 'date-picker__weekdays';
	WEEKDAY_LABELS.forEach((label) => {
		const $cell = document.createElement('span');
		$cell.className = 'date-picker__weekday';
		$cell.textContent = label;
		$weekdays.appendChild($cell);
	});

	const $grid = document.createElement('div');
	$grid.className = 'date-picker__grid';

	const $hint = document.createElement('div');
	$hint.className = 'range-picker__hint';

	$popover.append($header, $weekdays, $grid, $hint);
	$picker.appendChild($popover);

	const effectiveEnd = () => rangeEnd ?? hoverDate;

	const updateHint = () => {
		if (rangeStart && !rangeEnd) {
			$hint.textContent = `From ${formatShortRangeDate(rangeStart)} - select end date`;
			return;
		}

		if (rangeStart && rangeEnd) {
			$hint.textContent = `${formatShortRangeDate(rangeStart)} - ${formatShortRangeDate(rangeEnd)}`;
			return;
		}

		$hint.textContent = 'Select start date';
	};

	const isInRange = (date) => {
		const end = effectiveEnd();

		if (!rangeStart || !end) {
			return false;
		}

		const low = rangeStart <= end ? rangeStart : end;
		const high = rangeStart <= end ? end : rangeStart;

		return date >= low && date <= high;
	};

	const navigate = (from, to) => {
		const low = from <= to ? from : to;
		const high = from <= to ? to : from;
		const params = new URLSearchParams({
			range: 'custom',
			from: formatIsoDate(low),
			to: formatIsoDate(high),
			...extraParams,
		});

		window.location.href = `${baseUrl}?${params.toString()}`;
	};

	let dayCells = [];

	const paintRange = () => {
		dayCells.forEach(({ date, $day }) => {
			$day.classList.toggle('range-picker__day--in-range', isInRange(date));
			$day.classList.toggle(
				'date-picker__day--selected',
				(rangeStart !== null && isSameDay(date, rangeStart)) || (rangeEnd !== null && isSameDay(date, rangeEnd))
			);
		});

		updateHint();
	};

	const render = () => {
		$title.textContent = `${MONTH_NAMES[viewDate.getMonth()]} ${viewDate.getFullYear()}`;
		$grid.innerHTML = '';
		dayCells = [];

		const gridStart = startOfMonthGrid(viewDate);
		const today = new Date();

		for (let index = 0; index < 42; index += 1) {
			const date = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + index);
			const $day = document.createElement('button');
			$day.type = 'button';
			$day.className = 'date-picker__day';
			$day.textContent = String(date.getDate());
			$day.setAttribute('aria-label', formatDisplayDate(date));

			if (date.getMonth() !== viewDate.getMonth()) {
				$day.classList.add('date-picker__day--outside');
			}

			if (isSameDay(date, today)) {
				$day.classList.add('date-picker__day--today');
			}

			if (date > maxDate) {
				$day.disabled = true;
				$day.classList.add('date-picker__day--disabled');
			} else {
				$day.addEventListener('mouseenter', () => {
					if (rangeStart && !rangeEnd) {
						hoverDate = date;
						paintRange();
					}
				});

				$day.addEventListener('click', (event) => {
					event.stopPropagation();

					if (!rangeStart || rangeEnd) {
						rangeStart = date;
						rangeEnd = null;
						hoverDate = null;
						paintRange();
						return;
					}

					rangeEnd = date;
					paintRange();
					navigate(rangeStart, rangeEnd);
				});
			}

			dayCells.push({ date, $day });
			$grid.appendChild($day);
		}

		paintRange();
	};

	$prevBtn.addEventListener('click', () => {
		viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
		render();
	});

	$nextBtn.addEventListener('click', () => {
		viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
		render();
	});

	$trigger.addEventListener('click', () => {
		if ($picker.classList.contains('range-picker--open')) {
			closeRangePicker($picker);
			return;
		}

		closeAllRangePickers($picker);
		closeAllDatePickers();
		render();
		$popover.hidden = false;
		$picker.classList.add('range-picker--open');
		$trigger.setAttribute('aria-expanded', 'true');
	});

	$popover.addEventListener('mouseleave', () => {
		if (rangeStart && !rangeEnd) {
			hoverDate = null;
			paintRange();
		}
	});
};

document.addEventListener('click', (event) => {
	if (event.target instanceof Element && !event.target.closest('.js-range-picker')) {
		closeAllRangePickers();
	}
});

document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape') {
		closeAllRangePickers();
	}
});

document.querySelectorAll('.js-range-picker').forEach(initRangePicker);
