const incomeInput = document.querySelector('#onboarding-income');
const submitButton = document.querySelector('.js-onboarding-submit');
const planCards = document.querySelectorAll('.js-plan-card');

const isValidIncome = (income) => Number.isFinite(income) && income >= 0;

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

const createProjectionCard = (label, colorHex, yearly) => {
	const el = document.createElement('span');
	el.className = 'projection-card';
	el.style.setProperty('--type-color', colorHex);
	el.style.setProperty('--type-bg', hexToRgba(colorHex, 0.15));
	el.innerHTML = `<span class="projection-card__label">${label}</span><span class="projection-card__value">${formatYearly(yearly)}</span>`;

	return el;
};

const renderProjection = (card) => {
	const projectionEl = card.querySelector('.js-plan-projection');
	const income = parseFloat(incomeInput.value);

	projectionEl.innerHTML = '';

	if (!isValidIncome(income)) {
		return;
	}

	const types = JSON.parse(projectionEl.dataset.types || '[]');

	projectionEl.appendChild(createProjectionCard(
		projectionEl.dataset.incomeLabel,
		projectionEl.dataset.incomeColor,
		income * 12
	));

	types.forEach((type) => {
		projectionEl.appendChild(createProjectionCard(type.label, type.color_hex, income * type.income_percent * 12));
	});
};

const renderAllProjections = () => {
	planCards.forEach(renderProjection);
};

const updateSubmitState = () => {
	const income = parseFloat(incomeInput.value);
	const hasSelection = document.querySelector('.js-plan-radio:checked') !== null;

	submitButton.disabled = !(isValidIncome(income) && hasSelection);
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
