const incomeInput = document.querySelector('#onboarding-income');
const submitButton = document.querySelector('.js-onboarding-submit');
const planCards = document.querySelectorAll('.js-plan-card');

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

const renderProjection = (card) => {
	const projectionEl = card.querySelector('.js-plan-projection');
	const income = parseFloat(incomeInput.value);

	projectionEl.innerHTML = '';

	if (!income || income <= 0) {
		return;
	}

	const types = JSON.parse(projectionEl.dataset.types || '[]');
	let allocatedPercent = 0;

	types.forEach((type) => {
		allocatedPercent += type.income_percent;

		const yearly = income * type.income_percent * 12;
		const el = document.createElement('span');
		el.className = 'projection-card';
		el.style.setProperty('--type-color', type.color_hex);
		el.style.setProperty('--type-bg', hexToRgba(type.color_hex, 0.15));
		el.innerHTML = `<span class="projection-card__label">${type.label}</span><span class="projection-card__value">${formatYearly(yearly)}</span>`;
		projectionEl.appendChild(el);
	});

	if (allocatedPercent < 0.9999) {
		const leftoverYearly = income * (1 - allocatedPercent) * 12;
		const el = document.createElement('span');
		el.className = 'projection-card projection-card--unallocated';
		el.innerHTML = `<span class="projection-card__label">Unallocated</span><span class="projection-card__value">${formatYearly(leftoverYearly)}</span>`;
		projectionEl.appendChild(el);
	}
};

const renderAllProjections = () => {
	planCards.forEach(renderProjection);
};

const updateSubmitState = () => {
	const income = parseFloat(incomeInput.value);
	const hasSelection = document.querySelector('.js-plan-radio:checked') !== null;

	submitButton.disabled = !(income > 0 && hasSelection);
};

incomeInput.addEventListener('input', () => {
	renderAllProjections();
	updateSubmitState();
});

document.querySelectorAll('.js-plan-radio').forEach((radio) => {
	radio.addEventListener('change', () => {
		planCards.forEach((card) => card.classList.remove('plan-card--selected'));
		radio.closest('.js-plan-card').classList.add('plan-card--selected');
		updateSubmitState();
	});
});

renderAllProjections();
updateSubmitState();
