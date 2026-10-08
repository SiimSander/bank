const postHabitForm = async (url, fields) => {
	const body = new URLSearchParams(fields).toString();
	const response = await fetch(url, {
		method: 'POST',
		headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
		body,
	});

	if (!response.ok) {
		return { success: false };
	}

	return response.json();
};

const updateChartCell = (habitId, date, status, today) => {
	const cell = document.querySelector(`.js-habit-chart [data-habit-id="${habitId}"][data-date="${date}"]`);

	if (!cell) {
		return;
	}

	const cellStatus = status === 'pending' && date > today ? 'future' : status;
	cell.setAttribute('class', `habit-chart__cell habit-chart__cell--${cellStatus}`);
};

const initHabitDayList = (page) => {
	const list = page.querySelector('.js-habit-day-list');

	if (!list) {
		return;
	}

	const date = list.dataset.date;
	const today = page.dataset.today;

	list.querySelectorAll('.js-habit-status').forEach((button) => {
		button.addEventListener('click', async () => {
			const item = button.closest('[data-habit-id]');
			const habitId = item.dataset.habitId;
			const currentStatus = item.dataset.status;
			let status = button.dataset.status;

			if (status === currentStatus) {
				if (date !== today) {
					return;
				}

				status = 'pending';
			}

			const data = await postHabitForm('/habits/status', { habitId, date, status });

			if (!data.success) {
				return;
			}

			item.classList.remove('habit-list-item--pending', 'habit-list-item--done', 'habit-list-item--failed');
			item.classList.add(`habit-list-item--${data.status}`);
			item.dataset.status = data.status;
			updateChartCell(habitId, date, data.status, today);
		});
	});
};

const initHabitBoard = (page) => {
	page.querySelectorAll('.js-habit-title').forEach((input) => {
		let savedValue = input.value;

		input.addEventListener('keydown', (event) => {
			if (event.key === 'Enter') {
				event.preventDefault();
				input.blur();
			}
		});

		input.addEventListener('blur', async () => {
			const title = input.value.trim();

			if (title === savedValue) {
				return;
			}

			if (title === '') {
				input.value = savedValue;

				return;
			}

			const habitId = input.closest('[data-habit-id]').dataset.habitId;
			const data = await postHabitForm('/habits/rename', { habitId, title });

			if (!data.success) {
				input.value = savedValue;

				return;
			}

			savedValue = data.title;
			input.value = data.title;
			window.location.reload();
		});
	});

	page.querySelectorAll('.js-habit-delete').forEach((button) => {
		button.addEventListener('click', async () => {
			const item = button.closest('[data-habit-id]');
			const title = item.querySelector('.js-habit-title').value;

			if (!window.confirm(`Delete "${title}"? It disappears from this month onward; past months keep it.`)) {
				return;
			}

			const data = await postHabitForm('/habits/delete', { habitId: item.dataset.habitId });

			if (data.success) {
				window.location.reload();
			}
		});
	});
};

const habitsPage = document.querySelector('.js-habits-page');

if (habitsPage) {
	initHabitDayList(habitsPage);
	initHabitBoard(habitsPage);
}
