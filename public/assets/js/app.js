window.csrfHeaders = (extraHeaders = {}) => ({
	'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
	...extraHeaders,
});

const setupModal = (modalSelector, openSelector, closeSelector) => {
	const modal = document.querySelector(modalSelector);

	if (!modal) {
		return;
	}

	const openBtn = document.querySelector(openSelector);
	const closeBtn = modal.querySelector(closeSelector);

	const closeModal = () => {
		modal.classList.remove('modal-overlay--open');
		document.body.classList.remove('modal-open');
	};

	openBtn?.addEventListener('click', () => {
		modal.classList.add('modal-overlay--open');
		document.body.classList.add('modal-open');
	});

	closeBtn?.addEventListener('click', closeModal);

	modal.addEventListener('click', (event) => {
		if (event.target === modal) {
			closeModal();
		}
	});

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && modal.classList.contains('modal-overlay--open')) {
			closeModal();
		}
	});
};

setupModal('.js-goals-modal', '.js-goals-open', '.js-goals-close');

const spendGoalsTitles = {
	week: 'Spend daily this week',
	month: 'Spend daily this month',
};

const initSpendGoalsPeriodToggle = () => {
	const card = document.querySelector('.js-spend-goals-card');

	if (!card) {
		return;
	}

	const title = card.querySelector('.js-spend-goals-title');
	const options = card.querySelectorAll('.js-spend-period-option');
	const panels = card.querySelectorAll('.js-spend-panel');

	const setPeriod = (period) => {
		const normalizedPeriod = period === 'month' ? 'month' : 'week';

		if (title) {
			title.textContent = spendGoalsTitles[normalizedPeriod];
		}

		options.forEach(($option) => {
			const isActive = $option.dataset.period === normalizedPeriod;
			$option.classList.toggle('goals-card__period-option--active', isActive);
			$option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
		});

		panels.forEach(($panel) => {
			const isActive = $panel.dataset.period === normalizedPeriod;
			$panel.classList.toggle('goals-card__panel--hidden', !isActive);
			$panel.hidden = !isActive;
		});
	};

	options.forEach(($option) => {
		$option.addEventListener('click', () => {
			setPeriod($option.dataset.period);
		});
	});
};

initSpendGoalsPeriodToggle();
