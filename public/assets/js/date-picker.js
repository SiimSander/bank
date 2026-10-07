const CALENDAR_ICON = '<svg class="date-picker__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';

const MONTH_NAMES = [
	'January', 'February', 'March', 'April', 'May', 'June',
	'July', 'August', 'September', 'October', 'November', 'December',
];

const WEEKDAY_LABELS = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

const parseIsoDate = (value) => {
	if (!value) {
		return null;
	}

	const parts = value.split('-').map(Number);
	if (parts.length !== 3 || parts.some(Number.isNaN)) {
		return null;
	}

	return new Date(parts[0], parts[1] - 1, parts[2]);
};

const formatIsoDate = (date) => {
	const year = date.getFullYear();
	const month = String(date.getMonth() + 1).padStart(2, '0');
	const day = String(date.getDate()).padStart(2, '0');

	return `${year}-${month}-${day}`;
};

const formatDisplayDate = (date) => date.toLocaleDateString(undefined, {
	month: 'short',
	day: 'numeric',
	year: 'numeric',
});

const isSameDay = (left, right) => left.getFullYear() === right.getFullYear()
	&& left.getMonth() === right.getMonth()
	&& left.getDate() === right.getDate();

const startOfMonthGrid = (viewDate) => {
	const firstOfMonth = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
	const weekday = firstOfMonth.getDay();
	const mondayOffset = weekday === 0 ? 6 : weekday - 1;

	return new Date(viewDate.getFullYear(), viewDate.getMonth(), 1 - mondayOffset);
};

const closeDatePicker = (picker) => {
	picker.classList.remove('date-picker--open');
	picker.querySelector('.date-picker__trigger')?.setAttribute('aria-expanded', 'false');
	const popoverEl = picker.querySelector('.date-picker__popover');
	if (popoverEl) {
		popoverEl.hidden = true;
	}
};

const closeAllDatePickers = (except = null) => {
	document.querySelectorAll('.date-picker').forEach((picker) => {
		if (picker !== except) {
			closeDatePicker(picker);
		}
	});
};

const initDatePicker = (input) => {
	if (input.dataset.datePickerInit === 'true') {
		return;
	}

	input.dataset.datePickerInit = 'true';

	const wrapper = document.createElement('div');
	wrapper.className = 'date-picker';
	input.parentNode.insertBefore(wrapper, input);
	wrapper.appendChild(input);

	input.classList.add('date-picker__native');
	input.tabIndex = -1;

	const minDate = parseIsoDate(input.getAttribute('min'));
	const maxDate = parseIsoDate(input.getAttribute('max'));
	const isRequired = input.required;

	const trigger = document.createElement('button');
	trigger.type = 'button';
	trigger.className = 'date-picker__trigger';
	trigger.setAttribute('aria-haspopup', 'dialog');
	trigger.setAttribute('aria-expanded', 'false');

	if (input.id) {
		trigger.id = input.id;
		input.removeAttribute('id');
	}

	const valueEl = document.createElement('span');
	valueEl.className = 'date-picker__value';
	trigger.appendChild(valueEl);
	trigger.insertAdjacentHTML('beforeend', CALENDAR_ICON);

	const popover = document.createElement('div');
	popover.className = 'date-picker__popover';
	popover.setAttribute('role', 'dialog');
	popover.hidden = true;

	const header = document.createElement('div');
	header.className = 'date-picker__header';

	const prevBtn = document.createElement('button');
	prevBtn.type = 'button';
	prevBtn.className = 'date-picker__nav';
	prevBtn.setAttribute('aria-label', 'Previous month');
	prevBtn.textContent = '‹';

	const title = document.createElement('div');
	title.className = 'date-picker__title';

	const nextBtn = document.createElement('button');
	nextBtn.type = 'button';
	nextBtn.className = 'date-picker__nav';
	nextBtn.setAttribute('aria-label', 'Next month');
	nextBtn.textContent = '›';

	header.append(prevBtn, title, nextBtn);

	const weekdays = document.createElement('div');
	weekdays.className = 'date-picker__weekdays';
	WEEKDAY_LABELS.forEach((label) => {
		const cell = document.createElement('span');
		cell.className = 'date-picker__weekday';
		cell.textContent = label;
		weekdays.appendChild(cell);
	});

	const grid = document.createElement('div');
	grid.className = 'date-picker__grid';

	const footer = document.createElement('div');
	footer.className = 'date-picker__footer';

	const clearBtn = document.createElement('button');
	clearBtn.type = 'button';
	clearBtn.className = 'date-picker__footer-btn';
	clearBtn.textContent = 'Clear';

	const todayBtn = document.createElement('button');
	todayBtn.type = 'button';
	todayBtn.className = 'date-picker__footer-btn date-picker__footer-btn--accent';
	todayBtn.textContent = 'Today';

	footer.append(clearBtn, todayBtn);
	popover.append(header, weekdays, grid, footer);
	wrapper.append(trigger, popover);

	let viewDate = parseIsoDate(input.value) ?? new Date();
	viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);

	const isDateDisabled = (date) => {
		if (minDate && date < minDate) {
			return true;
		}

		if (maxDate && date > maxDate) {
			return true;
		}

		return false;
	};

	const syncTrigger = () => {
		const selected = parseIsoDate(input.value);

		if (selected) {
			valueEl.textContent = formatDisplayDate(selected);
			trigger.classList.remove('date-picker__trigger--empty');
		} else {
			valueEl.textContent = 'Select date';
			trigger.classList.add('date-picker__trigger--empty');
		}
	};

	const render = () => {
		title.textContent = `${MONTH_NAMES[viewDate.getMonth()]} ${viewDate.getFullYear()}`;
		grid.innerHTML = '';

		const selected = parseIsoDate(input.value);
		const today = new Date();
		const gridStart = startOfMonthGrid(viewDate);

		for (let index = 0; index < 42; index += 1) {
			const date = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + index);
			const dayBtn = document.createElement('button');
			dayBtn.type = 'button';
			dayBtn.className = 'date-picker__day';
			dayBtn.textContent = String(date.getDate());
			dayBtn.setAttribute('aria-label', formatDisplayDate(date));

			if (date.getMonth() !== viewDate.getMonth()) {
				dayBtn.classList.add('date-picker__day--outside');
			}

			if (selected && isSameDay(date, selected)) {
				dayBtn.classList.add('date-picker__day--selected');
			}

			if (isSameDay(date, today)) {
				dayBtn.classList.add('date-picker__day--today');
			}

			if (isDateDisabled(date)) {
				dayBtn.disabled = true;
				dayBtn.classList.add('date-picker__day--disabled');
			} else {
				dayBtn.addEventListener('click', (event) => {
					event.stopPropagation();
					input.value = formatIsoDate(date);
					input.dispatchEvent(new Event('change', { bubbles: true }));
					syncTrigger();
					render();
					close();
				});
			}

			grid.appendChild(dayBtn);
		}
	};

	const open = () => {
		closeAllDatePickers(wrapper);
		const selected = parseIsoDate(input.value);
		viewDate = selected
			? new Date(selected.getFullYear(), selected.getMonth(), 1)
			: new Date(new Date().getFullYear(), new Date().getMonth(), 1);
		render();
		popover.hidden = false;
		wrapper.classList.add('date-picker--open');
		trigger.setAttribute('aria-expanded', 'true');
	};

	const close = () => {
		closeDatePicker(wrapper);
	};

	trigger.addEventListener('click', () => {
		if (wrapper.classList.contains('date-picker--open')) {
			close();
			return;
		}

		open();
	});

	prevBtn.addEventListener('click', () => {
		viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
		render();
	});

	nextBtn.addEventListener('click', () => {
		viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
		render();
	});

	todayBtn.addEventListener('click', () => {
		const today = new Date();

		if (isDateDisabled(today)) {
			return;
		}

		input.value = formatIsoDate(today);
		input.dispatchEvent(new Event('change', { bubbles: true }));
		syncTrigger();
		close();
	});

	clearBtn.addEventListener('click', () => {
		if (isRequired) {
			return;
		}

		input.value = '';
		input.dispatchEvent(new Event('change', { bubbles: true }));
		syncTrigger();
		close();
	});

	if (isRequired) {
		clearBtn.hidden = true;
	}

	input.addEventListener('change', syncTrigger);
	syncTrigger();
};

document.addEventListener('click', (event) => {
	if (!(event.target instanceof Element)) {
		return;
	}

	if (!event.target.closest('.date-picker')) {
		closeAllDatePickers();
	}
});

document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape') {
		closeAllDatePickers();
	}
});

document.querySelectorAll('input[type="date"]').forEach(initDatePicker);
