const setEntryDirection = ($directionRoot, direction) => {
	const normalizedDirection = direction === 'out' ? 'out' : 'in';
	const $input = $directionRoot.querySelector('.js-entry-direction-input');

	if ($input) {
		$input.value = normalizedDirection;
	}

	$directionRoot.querySelectorAll('.js-entry-direction-option').forEach(($option) => {
		const isActive = $option.dataset.direction === normalizedDirection;
		$option.classList.toggle('entry-direction__option--active', isActive);
		$option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
	});

	const $form = $directionRoot.closest('form');
	const $submitBtn = $form?.querySelector('.js-bank-submit-btn');

	if ($submitBtn) {
		const labelKey = normalizedDirection === 'out' ? 'labelOut' : 'labelIn';
		const label = $directionRoot.dataset[labelKey];

		if (label) {
			$submitBtn.lastChild.textContent = ` ${label}`;
		}
	}
};

const initEntryDirection = ($directionRoot) => {
	if (!$directionRoot || $directionRoot.dataset.initialized === 'true') {
		return;
	}

	$directionRoot.dataset.initialized = 'true';

	$directionRoot.querySelectorAll('.js-entry-direction-option').forEach(($option) => {
		$option.addEventListener('click', () => {
			setEntryDirection($directionRoot, $option.dataset.direction);
		});
	});

	setEntryDirection($directionRoot, $directionRoot.querySelector('.js-entry-direction-input')?.value ?? 'in');
};

const supportsWithdrawOnMainForm = (balanceMode) => balanceMode === 'pot';

const updateDirectionFromOption = ($directionRoot, $option) => {
	if (!$directionRoot || !$option) {
		return;
	}

	$directionRoot.dataset.labelIn = $option.dataset.labelIn ?? 'Add';
	$directionRoot.dataset.labelOut = $option.dataset.labelOut ?? 'Withdraw';
	setEntryDirection($directionRoot, $directionRoot.querySelector('.js-entry-direction-input')?.value ?? 'in');
};

const toggleDirectionVisibility = ($directionRoot, show) => {
	if (!$directionRoot) {
		return;
	}

	$directionRoot.classList.toggle('entry-direction--hidden', !show);

	if (!show) {
		setEntryDirection($directionRoot, 'in');
	}
};

document.querySelectorAll('.js-bank-edit-btn').forEach((button) => {
	button.addEventListener('click', () => {
		const item = button.closest('[data-entry-id]');
		item.querySelector('.js-bank-entry-view').hidden = true;
		item.querySelector('.js-bank-entry-edit-form').hidden = false;
	});
});

document.querySelectorAll('.js-bank-edit-cancel').forEach((button) => {
	button.addEventListener('click', () => {
		const item = button.closest('[data-entry-id]');
		const $editForm = item.querySelector('.js-bank-entry-edit-form');
		const $method = $editForm.querySelector('.js-bank-entry-method');
		const $amount = $editForm.querySelector('.js-bank-entry-amount');
		const $note = $editForm.querySelector('.js-bank-entry-note');
		const $direction = $editForm.querySelector('.js-entry-direction');
		const $directionInput = $editForm.querySelector('.js-entry-direction-input');

		$method.selectedIndex = [...$method.options].findIndex((option) => option.defaultSelected);
		$amount.value = $amount.defaultValue;
		$note.value = $note.dataset.originalNote ?? $note.defaultValue;

		if ($direction && $directionInput) {
			setEntryDirection($direction, $directionInput.defaultValue);
		}

		$editForm.dispatchEvent(new CustomEvent('entry-edit-reset'));
		$editForm.hidden = true;
		item.querySelector('.js-bank-entry-view').hidden = false;
	});
});

document.querySelectorAll('.js-bank-entry-save').forEach((button) => {
	button.addEventListener('click', async () => {
		const item = button.closest('[data-entry-id]');
		const entryId = item.dataset.entryId;
		const type = item.querySelector('.js-bank-entry-type')?.value ?? item.dataset.entryType;
		const entryMethod = item.querySelector('.js-bank-entry-method').value;
		const amount = item.querySelector('.js-bank-entry-amount').value;
		const note = item.querySelector('.js-bank-entry-note').value;
		const $directionInput = item.querySelector('.js-entry-direction-input');
		const direction = $directionInput ? $directionInput.value : 'in';

		const response = await fetch('/bank/update', {
			method: 'POST',
			headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
			body: `entryId=${encodeURIComponent(entryId)}&type=${encodeURIComponent(type)}&method=${encodeURIComponent(entryMethod)}&amount=${encodeURIComponent(amount)}&direction=${encodeURIComponent(direction)}&note=${encodeURIComponent(note)}`,
		});

		if (response.ok) {
			reloadKeepingTodayEntriesExpanded();
		}
	});
});

const $bankAddForm = document.querySelector('.js-bank-add-form');
const $bankAddTypeSelect = document.querySelector('.js-bank-add-type');
const $bankAddMethodSelect = document.querySelector('.js-bank-add-method');

if ($bankAddForm && $bankAddTypeSelect && $bankAddMethodSelect) {
	const $addDirection = $bankAddForm.querySelector('.js-entry-direction');

	const syncMainFormDirection = () => {
		const $selectedOption = $bankAddTypeSelect.options[$bankAddTypeSelect.selectedIndex];
		const balanceMode = $selectedOption?.dataset.balanceMode ?? '';
		const showDirection = supportsWithdrawOnMainForm(balanceMode);

		toggleDirectionVisibility($addDirection, showDirection);
		updateDirectionFromOption($addDirection, $selectedOption);
	};

	const updateAddTypeLabels = () => {
		const isCash = $bankAddMethodSelect.value === 'cash';

		$bankAddTypeSelect.querySelectorAll('option').forEach((option) => {
			option.textContent = isCash ? option.dataset.labelCash : option.dataset.label;
		});
	};

	const $stockField = $bankAddForm.querySelector('.js-bank-add-stock-field');
	const $stockSelect = $bankAddForm.querySelector('.js-bank-add-stock');
	const $noteField = $bankAddForm.querySelector('.js-bank-add-note-field');
	const $noteInput = $bankAddForm.querySelector('input[name="note"]');
	let noteFilledFromStock = false;

	const clearAutoFilledNote = () => {
		if (noteFilledFromStock) {
			$noteInput.value = '';
			noteFilledFromStock = false;
		}
	};

	const setNotePlaceholder = (key) => {
		$noteInput.placeholder = $noteInput.dataset[key] ?? $noteInput.dataset.placeholderDefault ?? '';
	};

	const $noteHint = $noteField?.querySelector('.js-bank-note-hint');

	const syncInvestmentStockField = () => {
		if (!$noteField || !$noteInput) {
			return;
		}

		const isInvestments = $bankAddTypeSelect.value === 'investments';

		if ($stockField) {
			$stockField.hidden = !isInvestments;
		}

		if (!isInvestments) {
			clearAutoFilledNote();
			$noteField.hidden = false;
			setNotePlaceholder('placeholderDefault');

			if ($noteHint) {
				$noteHint.hidden = true;
			}

			return;
		}

		const $selectedStock = $stockSelect?.options[$stockSelect.selectedIndex];
		const isNewStock = !$stockSelect || $selectedStock?.dataset.newStock === '1';

		$noteField.hidden = !isNewStock;

		if ($noteHint) {
			$noteHint.hidden = !isNewStock;
		}

		if (isNewStock) {
			clearAutoFilledNote();
			setNotePlaceholder('placeholderNewStock');
			return;
		}

		$noteInput.value = $stockSelect.value;
		noteFilledFromStock = true;
	};

	$bankAddMethodSelect.addEventListener('change', updateAddTypeLabels);
	$bankAddTypeSelect.addEventListener('change', () => {
		syncMainFormDirection();
		syncInvestmentStockField();
	});

	if ($stockSelect) {
		$stockSelect.addEventListener('change', () => {
			syncInvestmentStockField();

			if (!$noteField.hidden) {
				$noteInput.focus();
			}
		});
	}

	if ($addDirection) {
		initEntryDirection($addDirection);
	}

	updateAddTypeLabels();
	syncMainFormDirection();
	syncInvestmentStockField();
}

document.querySelectorAll('.js-bank-type-form').forEach(($form) => {
	const $direction = $form.querySelector('.js-entry-direction');

	if ($direction) {
		initEntryDirection($direction);
	}

	const $stockSelect = $form.querySelector('.js-bank-type-stock');
	const $noteField = $form.querySelector('.js-bank-type-note-field');
	const $noteInput = $form.querySelector('input[name="note"]');

	if ($stockSelect && $noteField && $noteInput) {
		const syncStockNote = () => {
			const $selectedStock = $stockSelect.options[$stockSelect.selectedIndex];
			const isNewStock = $selectedStock?.dataset.newStock === '1';

			$noteField.hidden = !isNewStock;
			$noteInput.value = isNewStock ? '' : $stockSelect.value;
			$noteInput.placeholder = isNewStock
				? ($noteInput.dataset.placeholderNewStock ?? $noteInput.dataset.placeholderDefault ?? '')
				: ($noteInput.dataset.placeholderDefault ?? '');

			return isNewStock;
		};

		$stockSelect.addEventListener('change', () => {
			if (syncStockNote()) {
				$noteInput.focus();
			}
		});

		syncStockNote();
	}
});

document.querySelectorAll('.js-bank-entry-edit-form').forEach(($editForm) => {
	const $direction = $editForm.querySelector('.js-entry-direction');
	const $typeSelect = $editForm.querySelector('.js-bank-entry-type');

	if ($direction) {
		initEntryDirection($direction);
	}

	if ($typeSelect && $direction) {
		const syncEditDirection = () => {
			const $selectedOption = $typeSelect.options[$typeSelect.selectedIndex];
			const balanceMode = $selectedOption?.dataset.balanceMode ?? '';
			const showDirection = supportsWithdrawOnMainForm(balanceMode);

			toggleDirectionVisibility($direction, showDirection);
			updateDirectionFromOption($direction, $selectedOption);
		};

		$typeSelect.addEventListener('change', syncEditDirection);
		syncEditDirection();
	}

	const $stockSelect = $editForm.querySelector('.js-bank-entry-stock');
	const $noteInput = $editForm.querySelector('.js-bank-entry-note');

	if ($stockSelect && $noteInput) {
		const entryType = () => $typeSelect?.value ?? $editForm.closest('[data-entry-type]')?.dataset.entryType;

		const syncEditStockField = () => {
			const isInvestments = entryType() === 'investments';

			$stockSelect.hidden = !isInvestments;

			if (!isInvestments) {
				$noteInput.hidden = false;
				$noteInput.value = $noteInput.dataset.originalNote ?? '';
				return;
			}

			const $selectedStock = $stockSelect.options[$stockSelect.selectedIndex];
			const isNewStock = $selectedStock?.dataset.newStock === '1';

			$noteInput.hidden = !isNewStock;

			if (!isNewStock) {
				$noteInput.value = $stockSelect.value;
			}
		};

		$typeSelect?.addEventListener('change', syncEditStockField);
		$editForm.addEventListener('entry-edit-reset', () => {
			$stockSelect.selectedIndex = [...$stockSelect.options].findIndex((option) => option.defaultSelected);
			$noteInput.value = $noteInput.dataset.originalNote ?? '';
			syncEditStockField();
		});
		$stockSelect.addEventListener('change', () => {
			const $selectedStock = $stockSelect.options[$stockSelect.selectedIndex];

			if ($selectedStock?.dataset.newStock === '1') {
				$noteInput.value = '';
			}

			syncEditStockField();

			if (!$noteInput.hidden) {
				$noteInput.focus();
			}
		});

		syncEditStockField();
	}
});

const TODAY_ENTRIES_STACK_LIMIT = 5;
const TODAY_ENTRIES_PEEK_STEP_PX = 20;
const TODAY_ENTRIES_EXPANDED_KEY = 'todayEntriesExpanded';

const reloadKeepingTodayEntriesExpanded = () => {
	const $stack = document.querySelector('.js-today-entries-stack');

	if ($stack?.classList.contains('today-entries-stack--expanded')) {
		sessionStorage.setItem(TODAY_ENTRIES_EXPANDED_KEY, '1');
	}

	window.location.reload();
};

const updateTodayEntriesToggleLabel = ($stack, $label, expanded) => {
	const total = Number.parseInt($stack.dataset.totalCount ?? '0', 10);
	const stackedBelowCount = Math.max(0, total - 1);

	if (expanded) {
		$label.textContent = 'Show less';
		return;
	}

	if (stackedBelowCount > 0) {
		$label.textContent = `+${stackedBelowCount} more`;
		return;
	}

	$label.textContent = 'More';
};

const measureTodayEntriesStack = ($stack, $items) => {
	const $firstItem = $items[0];

	if (!$firstItem) {
		return;
	}

	const cardHeight = $firstItem.getBoundingClientRect().height;
	const peekStep = TODAY_ENTRIES_PEEK_STEP_PX;
	const total = Number.parseInt($stack.dataset.totalCount ?? '0', 10);
	const peekCount = Math.min(Math.max(total - 1, 0), TODAY_ENTRIES_STACK_LIMIT - 1);
	const collapsedHeight = cardHeight + peekCount * peekStep;

	$stack.style.setProperty('--stack-front-height', `${cardHeight}px`);
	$stack.style.setProperty('--stack-peek-height', `${peekStep}px`);
	$stack.style.setProperty('--stack-collapsed-height', `${collapsedHeight}px`);
};

const initTodayEntriesStack = () => {
	const restoreExpanded = sessionStorage.getItem(TODAY_ENTRIES_EXPANDED_KEY) === '1';
	sessionStorage.removeItem(TODAY_ENTRIES_EXPANDED_KEY);

	const $stack = document.querySelector('.js-today-entries-stack');

	if (!$stack) {
		return;
	}

	const total = Number.parseInt($stack.dataset.totalCount ?? '0', 10);

	if (total < 2) {
		return;
	}

	const $list = $stack.querySelector('.bank-entry-list--stacked');
	const $toggles = [...$stack.querySelectorAll('.js-today-entries-toggle')];
	const $labels = [...$stack.querySelectorAll('.js-today-entries-toggle-label')];
	const $items = $list ? [...$list.querySelectorAll('[data-stack-index]')] : [];

	const setExpanded = (expanded) => {
		$stack.classList.toggle('today-entries-stack--expanded', expanded);

		$toggles.forEach(($toggle) => {
			$toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		});

		$labels.forEach(($label) => {
			updateTodayEntriesToggleLabel($stack, $label, expanded);
		});

		if (!expanded) {
			requestAnimationFrame(() => measureTodayEntriesStack($stack, $items));
		}
	};

	const remeasure = () => {
		if ($stack.classList.contains('today-entries-stack--expanded')) {
			return;
		}

		measureTodayEntriesStack($stack, $items);
	};

	measureTodayEntriesStack($stack, $items);
	requestAnimationFrame(remeasure);
	window.addEventListener('resize', remeasure);

	$toggles.forEach(($toggle) => {
		$toggle.addEventListener('click', (event) => {
			event.stopPropagation();
			setExpanded(!$stack.classList.contains('today-entries-stack--expanded'));
		});
	});

	$labels.forEach(($label) => {
		updateTodayEntriesToggleLabel($stack, $label, false);
	});

	const $frontItem = $items[0];

	if ($frontItem) {
		const $editForm = $frontItem.querySelector('.js-bank-entry-edit-form');
		const observer = new MutationObserver(() => {
			if ($editForm !== null && $editForm.hidden === false) {
				requestAnimationFrame(() => measureTodayEntriesStack($stack, $items));
			}
		});

		if ($editForm) {
			observer.observe($editForm, { attributes: true, attributeFilter: ['hidden'] });
		}
	}

	if (restoreExpanded) {
		setExpanded(true);
	}
};

initTodayEntriesStack();

document.querySelectorAll('.js-bank-delete-btn').forEach((button) => {
	button.addEventListener('click', async () => {
		if (!confirm('Delete this entry?')) {
			return;
		}

		const item = button.closest('[data-entry-id]');
		const entryId = item.dataset.entryId;

		const response = await fetch('/bank/delete', {
			method: 'POST',
			headers: window.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
			body: `entryId=${encodeURIComponent(entryId)}`,
		});

		if (response.ok) {
			reloadKeepingTodayEntriesExpanded();
		}
	});
});
