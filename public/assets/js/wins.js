const CHECK_ICON_SVG = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

const updateWinStatusIcon = (toggleBtn, status) => {
	if (!toggleBtn) {
		return;
	}

	toggleBtn.innerHTML = (status === 'done' || status === 'failed') ? CHECK_ICON_SVG : '';
};

const getWinDragAfterElement = (list, clientY) => {
	const draggableItems = [...list.querySelectorAll('[data-item-id]:not(.win-list-item--dragging)')];

	return draggableItems.reduce((closest, child) => {
		const box = child.getBoundingClientRect();
		const offset = clientY - box.top - box.height / 2;

		if (offset < 0 && offset > closest.offset) {
			return { offset, element: child };
		}

		return closest;
	}, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
};

const ensureWinListEmptyState = (list) => {
	if (list.querySelector('[data-item-id]')) {
		return;
	}

	if (list.querySelector('.win-list-item--empty')) {
		return;
	}

	const emptyItem = document.createElement('li');
	emptyItem.className = 'win-list-item win-list-item--empty card-list-item card-list-item--empty';
	emptyItem.textContent = 'No wins yet today — add one!';
	list.appendChild(emptyItem);
};

document.querySelectorAll('.js-status-btn').forEach((button) => {
	button.addEventListener('click', async () => {
		const item = button.closest('[data-item-id]');
		const itemId = item.dataset.itemId;
		const status = button.dataset.status;

		const response = await fetch('/wins/status', {
			method: 'POST',
			headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
			body: `itemId=${encodeURIComponent(itemId)}&status=${encodeURIComponent(status)}`,
		});

		if (!response.ok) {
			return;
		}

		const data = await response.json();

		if (!data.success) {
			return;
		}

		item.classList.remove('card-list-item--pending', 'card-list-item--done', 'card-list-item--failed');
		item.classList.add(`card-list-item--${data.status}`);
		updateWinStatusIcon(item.querySelector('.win-list-item__status'), data.status);
	});
});

document.querySelectorAll('.js-status-toggle').forEach((button) => {
	button.addEventListener('click', async () => {
		const item = button.closest('[data-item-id]');
		const itemId = item.dataset.itemId;
		const isDone = item.classList.contains('card-list-item--done');
		const status = isDone ? 'pending' : 'done';

		const response = await fetch('/wins/status', {
			method: 'POST',
			headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
			body: `itemId=${encodeURIComponent(itemId)}&status=${encodeURIComponent(status)}`,
		});

		if (!response.ok) {
			return;
		}

		const data = await response.json();

		if (!data.success) {
			return;
		}

		item.classList.remove('card-list-item--pending', 'card-list-item--done', 'card-list-item--failed');
		item.classList.add(`card-list-item--${data.status}`);
		updateWinStatusIcon(button, data.status);
	});
});

document.querySelectorAll('.js-editable-title').forEach((titleEl) => {
	titleEl.addEventListener('click', () => {
		if (titleEl.querySelector('input')) {
			return;
		}

		const currentTitle = titleEl.textContent;
		const input = document.createElement('input');
		input.type = 'text';
		input.className = 'card-list-item__title-input';
		input.value = currentTitle;

		titleEl.textContent = '';
		titleEl.appendChild(input);
		input.focus();

		const saveTitle = async () => {
			const newTitle = input.value.trim();

			if (newTitle === '' || newTitle === currentTitle) {
				titleEl.textContent = currentTitle;
				return;
			}

			const item = titleEl.closest('[data-item-id]');
			const response = await fetch('/wins/title', {
				method: 'POST',
				headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
				body: `itemId=${encodeURIComponent(item.dataset.itemId)}&title=${encodeURIComponent(newTitle)}`,
			});

			const data = response.ok ? await response.json() : { success: false };
			titleEl.textContent = data.success ? data.title : currentTitle;
		};

		input.addEventListener('blur', saveTitle);
		input.addEventListener('keydown', (event) => {
			if (event.key === 'Enter') {
				input.blur();
			}
		});
	});
});

document.querySelectorAll('.js-delete-item-btn').forEach((button) => {
	button.addEventListener('click', async () => {
		const item = button.closest('[data-item-id]');
		const list = item.closest('.win-list');

		const response = await fetch('/wins/item/delete', {
			method: 'POST',
			headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
			body: `itemId=${encodeURIComponent(item.dataset.itemId)}`,
		});

		if (!response.ok) {
			return;
		}

		const data = await response.json();

		if (!data.success) {
			return;
		}

		item.remove();
		ensureWinListEmptyState(list);
	});
});

document.querySelectorAll('.js-delete-card-btn').forEach((button) => {
	button.addEventListener('click', async () => {
		if (!confirm('Delete this whole card? This removes all of its wins.')) {
			return;
		}

		const cardId = button.dataset.cardId;
		const body = cardId ? `cardId=${encodeURIComponent(cardId)}` : '';

		const response = await fetch('/wins/delete', {
			method: 'POST',
			headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
			body,
		});

		if (response.ok) {
			window.location.reload();
		}
	});
});

const saveWinListOrder = async (list) => {
	const itemIds = [...list.querySelectorAll('[data-item-id]')].map((item) => item.dataset.itemId);
	const body = new URLSearchParams();
	itemIds.forEach((itemId) => body.append('itemIds[]', itemId));

	const response = await fetch('/wins/reorder', {
		method: 'POST',
		headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
		body: body.toString(),
	});

	if (!response.ok) {
		window.location.reload();
		return;
	}

	const data = await response.json();

	if (!data.success) {
		window.location.reload();
	}
};

const reorderableWinList = document.querySelector('.js-win-list-reorderable');

if (reorderableWinList) {
	let draggedItem = null;
	let activePointerId = null;

	const finishWinDrag = async () => {
		if (!draggedItem) {
			return;
		}

		draggedItem.classList.remove('win-list-item--dragging');
		reorderableWinList.classList.remove('win-list--dragging');

		const list = reorderableWinList;
		draggedItem = null;
		activePointerId = null;

		await saveWinListOrder(list);
	};

	const onWinPointerMove = (event) => {
		if (!draggedItem || event.pointerId !== activePointerId) {
			return;
		}

		event.preventDefault();

		const afterElement = getWinDragAfterElement(reorderableWinList, event.clientY);

		if (afterElement === null) {
			reorderableWinList.appendChild(draggedItem);
			return;
		}

		reorderableWinList.insertBefore(draggedItem, afterElement);
	};

	const onWinPointerEnd = (event) => {
		if (!draggedItem || event.pointerId !== activePointerId) {
			return;
		}

		document.removeEventListener('pointermove', onWinPointerMove);
		document.removeEventListener('pointerup', onWinPointerEnd);
		document.removeEventListener('pointercancel', onWinPointerEnd);

		finishWinDrag();
	};

	reorderableWinList.querySelectorAll('.js-drag-handle').forEach((handle) => {
		handle.addEventListener('pointerdown', (event) => {
			if (event.button !== 0) {
				return;
			}

			const item = handle.closest('[data-item-id]');

			if (!item) {
				return;
			}

			event.preventDefault();

			draggedItem = item;
			activePointerId = event.pointerId;
			item.classList.add('win-list-item--dragging');
			reorderableWinList.classList.add('win-list--dragging');

			document.addEventListener('pointermove', onWinPointerMove);
			document.addEventListener('pointerup', onWinPointerEnd);
			document.addEventListener('pointercancel', onWinPointerEnd);
		});
	});
}
