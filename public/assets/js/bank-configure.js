const slugifyLabel = (label) => label
	.toLowerCase()
	.trim()
	.replace(/[^a-z0-9_]+/g, '_')
	.replace(/^_+|_+$/g, '') || 'type';

const debounce = (fn, delay) => {
	let timer;

	return (...args) => {
		clearTimeout(timer);
		timer = setTimeout(() => fn(...args), delay);
	};
};

const parsePercentInput = (value) => {
	if (value === '' || value === null || value === undefined) {
		return '';
	}

	return (parseFloat(value) / 100).toFixed(4);
};

const computeAllocationTotal = () => {
	let total = 0;
	let hasPercent = false;

	document.querySelectorAll('.js-configure-row').forEach((row) => {
		const isSystem = row.dataset.isSystem === '1';
		const activeInput = row.querySelector('.js-configure-active');
		const isActive = isSystem || (activeInput ? activeInput.checked : true);
		const percentInput = row.querySelector('[name="income_percent"]');

		if (!isActive || !percentInput || percentInput.value === '') {
			return;
		}

		hasPercent = true;
		total += parseFloat(percentInput.value);
	});

	return { total, hasPercent };
};

const isAllocationOverLimit = () => {
	const { total, hasPercent } = computeAllocationTotal();

	return hasPercent && total > 100.01;
};

const updateAllocationSummary = () => {
	const { total, hasPercent } = computeAllocationTotal();
	const totalEl = document.querySelector('.js-allocation-total');
	const summary = document.querySelector('.configure-summary');
	const errorEl = document.querySelector('.js-allocation-error');

	if (!totalEl || !summary) {
		return;
	}

	totalEl.textContent = `${total.toFixed(1)}%`;
	const isOver = hasPercent && total > 100.01;
	const isUnder = hasPercent && total < 99.99;
	summary.classList.toggle('configure-summary--invalid', isOver);
	summary.classList.toggle('configure-summary--warn', isUnder && !isOver);
	summary.classList.toggle('configure-summary--valid', !hasPercent || (!isOver && !isUnder));

	if (errorEl) {
		errorEl.hidden = !isOver;
	}

	const warnEl = document.querySelector('.js-allocation-warn');
	if (warnEl) {
		warnEl.hidden = !isUnder || isOver;
	}
};

const hexToRgba = (hex, alpha) => {
	const clean = hex.replace('#', '');
	const r = parseInt(clean.substring(0, 2), 16);
	const g = parseInt(clean.substring(2, 4), 16);
	const b = parseInt(clean.substring(4, 6), 16);

	return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

const formatYearly = (value) => value.toLocaleString(undefined, {
	minimumFractionDigits: 2,
	maximumFractionDigits: 2,
});

const getExpensesConfigureType = () => {
	const row = [...document.querySelectorAll('.js-configure-row')].find(
		(configureRow) => configureRow.querySelector('[name="slug"]')?.value.trim() === 'expenses'
	);

	if (!row) {
		return { label: 'Expenses', colorHex: '#f472b6' };
	}

	return {
		label: row.querySelector('[name="label"]').value.trim() || 'Expenses',
		colorHex: row.querySelector('[name="color_hex"]').value,
	};
};

const appendProjectionCard = (grid, label, colorHex, yearly) => {
	const el = document.createElement('span');
	el.className = 'projection-card';
	el.style.setProperty('--type-color', colorHex);
	el.style.setProperty('--type-bg', hexToRgba(colorHex, 0.15));
	el.innerHTML = `<span class="projection-card__label">${label}</span><span class="projection-card__value">${formatYearly(yearly)}</span>`;
	grid.appendChild(el);
};

const updateProjection = () => {
	const incomeInput = document.querySelector('.js-projection-income');
	const grid = document.querySelector('.js-projection-grid');
	const hint = document.querySelector('.js-projection-hint');
	const explainer = document.querySelector('.js-projection-explainer');

	if (!incomeInput || !grid || !hint) {
		return;
	}

	const income = parseFloat(incomeInput.value) || 0;
	const candidates = [];

	document.querySelectorAll('.js-configure-row').forEach((row) => {
		const isSystem = row.dataset.isSystem === '1';
		const activeInput = row.querySelector('.js-configure-active');
		const isActive = isSystem || (activeInput ? activeInput.checked : true);
		const percentInput = row.querySelector('[name="income_percent"]');
		const slug = row.querySelector('[name="slug"]').value.trim();

		if (!isActive || !percentInput || percentInput.value === '' || slug === 'expenses') {
			return;
		}

		candidates.push({
			label: row.querySelector('[name="label"]').value.trim(),
			colorHex: row.querySelector('[name="color_hex"]').value,
			percent: parseFloat(percentInput.value) / 100,
		});
	});

	grid.innerHTML = '';

	if (candidates.length === 0) {
		hint.hidden = false;
		if (explainer) {
			explainer.hidden = true;
		}
		return;
	}

	hint.hidden = true;
	if (explainer) {
		explainer.hidden = false;
	}

	let allocatedPercent = 0;

	candidates.forEach((type) => {
		allocatedPercent += type.percent;
		appendProjectionCard(grid, type.label, type.colorHex, income * type.percent * 12);
	});

	if (allocatedPercent < 0.9999) {
		const expensesType = getExpensesConfigureType();
		const leftoverYearly = income * (1 - allocatedPercent) * 12;
		appendProjectionCard(grid, expensesType.label, expensesType.colorHex, leftoverYearly);
	}
};

const postConfigure = async (url, body) => {
	const response = await fetch(url, {
		method: 'POST',
		headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
		body: new URLSearchParams(body).toString(),
	});

	return response.json();
};

const showRowError = (row, message) => {
	const errorEl = row.querySelector('.js-configure-error');
	if (!errorEl) {
		return;
	}

	errorEl.textContent = message;
	errorEl.hidden = message === '';
};

const collectRowData = (row) => ({
	typeId: row.dataset.typeId,
	label: row.querySelector('[name="label"]').value.trim(),
	slug: row.querySelector('[name="slug"]').value.trim(),
	colorHex: row.querySelector('[name="color_hex"]').value,
	percentValue: row.querySelector('[name="income_percent"]').value,
	isActive: row.querySelector('.js-configure-active')?.checked ?? true,
});

const rowPayloadKey = (row) => JSON.stringify(collectRowData(row));

const savingRows = new Set();

const saveRow = async (row) => {
	if (isAllocationOverLimit()) {
		showRowError(row, '');
		return;
	}

	const data = collectRowData(row);

	if (data.label === '') {
		return;
	}

	if (savingRows.has(data.typeId)) {
		return;
	}

	const saveKey = rowPayloadKey(row);
	savingRows.add(data.typeId);
	showRowError(row, '');

	try {
		const result = await postConfigure('/bank/configure/save', {
			typeId: data.typeId,
			label: data.label,
			slug: data.slug,
			color_hex: data.colorHex,
			income_percent: parsePercentInput(data.percentValue),
			is_active: data.isActive ? '1' : '0',
		});

		if (!result.success) {
			showRowError(row, result.error || 'Could not save type.');
			return;
		}

		if (result.slug && result.slug !== data.slug) {
			row.querySelector('[name="slug"]').value = result.slug;
		}
	} finally {
		savingRows.delete(data.typeId);

		if (rowPayloadKey(row) !== saveKey && !isAllocationOverLimit()) {
			saveRow(row);
		}
	}
};

const debouncedSaveRow = debounce(saveRow, 500);

const scheduleRowSave = (row, immediate = false) => {
	updateAllocationSummary();
	updateProjection();

	if (immediate) {
		saveRow(row);
		return;
	}

	debouncedSaveRow(row);
};

document.querySelectorAll('.js-configure-row').forEach((row) => {
	row.querySelectorAll('[name="label"], [name="slug"], [name="income_percent"]').forEach((input) => {
		input.addEventListener('input', () => scheduleRowSave(row));
	});

	const colorInput = row.querySelector('[name="color_hex"]');
	if (colorInput) {
		colorInput.addEventListener('change', () => scheduleRowSave(row, true));
	}

	const activeInput = row.querySelector('.js-configure-active');
	if (activeInput) {
		activeInput.addEventListener('change', () => scheduleRowSave(row, true));
	}

	row.addEventListener('submit', (event) => {
		event.preventDefault();
	});

	const deleteBtn = row.querySelector('.js-configure-delete');
	if (deleteBtn) {
		deleteBtn.addEventListener('click', async () => {
			if (!confirm('Are you sure you want to delete this type?')) {
				return;
			}

			showRowError(row, '');
			const result = await postConfigure('/bank/configure/delete', {
				typeId: row.dataset.typeId,
			});

			if (!result.success) {
				showRowError(row, result.error || 'Could not delete type.');
				return;
			}

			row.remove();
			updateAllocationSummary();
			updateProjection();
		});
	}
});

const addForm = document.querySelector('.js-configure-add-form');
const newLabelInput = document.querySelector('#new-type-label');
const newSlugInput = document.querySelector('#new-type-slug');

if (newLabelInput && newSlugInput) {
	newLabelInput.addEventListener('input', () => {
		if (newSlugInput.dataset.manual === '1') {
			return;
		}

		newSlugInput.value = slugifyLabel(newLabelInput.value);
	});

	newSlugInput.addEventListener('input', () => {
		newSlugInput.dataset.manual = newSlugInput.value === '' ? '0' : '1';
	});
}

if (addForm) {
	const addError = addForm.querySelector('.js-configure-add-error');

	addForm.addEventListener('submit', async (event) => {
		event.preventDefault();

		if (addError) {
			addError.hidden = true;
			addError.textContent = '';
		}

		if (isAllocationOverLimit()) {
			if (addError) {
				addError.textContent = 'Cannot exceed 100%. Reduce other types first.';
				addError.hidden = false;
			}
			return;
		}

		const formData = new FormData(addForm);
		const result = await postConfigure('/bank/configure/save', {
			typeId: '0',
			label: formData.get('label'),
			slug: formData.get('slug') || slugifyLabel(formData.get('label')),
			color_hex: formData.get('color_hex'),
			income_percent: parsePercentInput(formData.get('income_percent')),
			balance_mode: formData.get('balance_mode'),
			is_active: '1',
		});

		if (!result.success) {
			if (addError) {
				addError.textContent = result.error || 'Could not add type.';
				addError.hidden = false;
			}
			return;
		}

		window.location.reload();
	});
}

const projectionIncomeInput = document.querySelector('.js-projection-income');
const projectionIncomeError = document.querySelector('.js-projection-income-error');

if (projectionIncomeInput) {
	projectionIncomeInput.addEventListener('input', updateProjection);

	const saveGuaranteedIncome = async () => {
		const value = projectionIncomeInput.value.trim();

		if (projectionIncomeError) {
			projectionIncomeError.hidden = true;
			projectionIncomeError.textContent = '';
		}

		if (value === '' || !Number.isFinite(parseFloat(value)) || parseFloat(value) <= 0) {
			if (projectionIncomeError) {
				projectionIncomeError.textContent = 'Enter a positive number for guaranteed monthly income.';
				projectionIncomeError.hidden = false;
			}
			return;
		}

		const result = await postConfigure('/bank/configure/income', {
			guaranteed_monthly_income: value,
		});

		if (!result.success && projectionIncomeError) {
			projectionIncomeError.textContent = result.error || 'Could not save guaranteed monthly income.';
			projectionIncomeError.hidden = false;
		}
	};

	projectionIncomeInput.addEventListener('change', saveGuaranteedIncome);
	projectionIncomeInput.addEventListener('blur', saveGuaranteedIncome);
}

updateAllocationSummary();
updateProjection();
