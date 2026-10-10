(() => {
	const CHEVRON = '<svg class="stock-select__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
	const LIST_MAX_HEIGHT = 320;
	const LIST_MIN_HEIGHT = 160;
	const LIST_GAP = 4;
	const VIEWPORT_MARGIN = 12;

	let openSelect = null;

	const fillStock = ($target, option) => {
		$target.replaceChildren();

		if (option.dataset.name === undefined) {
			$target.textContent = option.textContent;

			return;
		}

		if (option.dataset.logo) {
			const $logo = document.createElement('img');
			$logo.className = 'stock-logo stock-select__logo';
			$logo.src = option.dataset.logo;
			$logo.alt = '';
			$logo.width = 64;
			$logo.height = 64;
			$target.append($logo);
		}

		const $name = document.createElement('span');
		$name.className = 'stock-select__name';
		$name.textContent = option.dataset.name;
		$target.append($name);

		if (option.dataset.ticker) {
			const $ticker = document.createElement('span');
			$ticker.className = 'stock-select__ticker';
			$ticker.textContent = option.dataset.ticker;
			$target.append($ticker);
		}
	};

	const enhance = ($select) => {
		const $wrapper = document.createElement('div');
		$wrapper.className = 'stock-select';
		$select.before($wrapper);
		$wrapper.append($select);
		$select.classList.add('stock-select__native');
		$select.tabIndex = -1;
		$select.setAttribute('aria-hidden', 'true');

		const $button = document.createElement('button');
		$button.type = 'button';
		$button.className = 'stock-select__button';
		$button.setAttribute('aria-haspopup', 'listbox');
		$button.setAttribute('aria-expanded', 'false');
		const label = $select.getAttribute('aria-label') ?? $select.labels?.[0]?.textContent;

		if (label) {
			$button.setAttribute('aria-label', label);
		}

		const $current = document.createElement('span');
		$current.className = 'stock-select__current';
		$button.append($current);
		$button.insertAdjacentHTML('beforeend', CHEVRON);
		$wrapper.prepend($button);

		const $list = document.createElement('ul');
		$list.className = 'stock-select__list';
		$list.setAttribute('role', 'listbox');
		$list.hidden = true;

		const $items = [...$select.options].map((option, index) => {
			const $item = document.createElement('li');
			$item.className = 'stock-select__option';
			$item.setAttribute('role', 'option');
			$item.tabIndex = -1;
			fillStock($item, option);
			$item.addEventListener('click', () => choose(index));
			$list.append($item);

			return $item;
		});

		const syncHidden = () => {
			$wrapper.hidden = $select.hidden;
		};

		const render = () => {
			const selected = $select.options[$select.selectedIndex];

			if (selected) {
				fillStock($current, selected);
			}

			$items.forEach(($item, index) => {
				const isSelected = index === $select.selectedIndex;
				$item.classList.toggle('stock-select__option--selected', isSelected);
				$item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
			});
		};

		const position = () => {
			const rect = $button.getBoundingClientRect();
			const below = window.innerHeight - rect.bottom - VIEWPORT_MARGIN;
			const above = rect.top - VIEWPORT_MARGIN;
			const openAbove = below < LIST_MIN_HEIGHT && above > below;
			const available = openAbove ? above : below;

			$list.style.left = `${rect.left}px`;
			$list.style.width = `${rect.width}px`;
			$list.style.maxHeight = `${Math.min(LIST_MAX_HEIGHT, available - LIST_GAP)}px`;

			if (openAbove) {
				$list.style.top = 'auto';
				$list.style.bottom = `${window.innerHeight - rect.top + LIST_GAP}px`;
			} else {
				$list.style.bottom = 'auto';
				$list.style.top = `${rect.bottom + LIST_GAP}px`;
			}
		};

		const close = ({ restoreFocus = false } = {}) => {
			if (openSelect !== api) {
				return;
			}

			openSelect = null;
			$list.hidden = true;
			$list.remove();
			$button.setAttribute('aria-expanded', 'false');

			if (restoreFocus) {
				$button.focus();
			}
		};

		const open = () => {
			if (openSelect !== null) {
				openSelect.close();
			}

			openSelect = api;
			document.body.append($list);
			$list.hidden = false;
			position();
			$button.setAttribute('aria-expanded', 'true');
			($items[$select.selectedIndex] ?? $items[0])?.focus();
		};

		const choose = (index) => {
			const changed = index !== $select.selectedIndex;
			$select.selectedIndex = index;
			render();
			close({ restoreFocus: true });

			if (changed) {
				$select.dispatchEvent(new Event('change', { bubbles: true }));
			}
		};

		const api = { close, reposition: position };

		$button.addEventListener('click', () => (openSelect === api ? close() : open()));
		$button.addEventListener('keydown', (event) => {
			if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
				event.preventDefault();
				open();
			}
		});

		$list.addEventListener('keydown', (event) => {
			const current = $items.indexOf(document.activeElement);

			switch (event.key) {
				case 'ArrowDown':
					event.preventDefault();
					$items[Math.min(current + 1, $items.length - 1)].focus();
					break;
				case 'ArrowUp':
					event.preventDefault();
					$items[Math.max(current - 1, 0)].focus();
					break;
				case 'Home':
					event.preventDefault();
					$items[0].focus();
					break;
				case 'End':
					event.preventDefault();
					$items[$items.length - 1].focus();
					break;
				case 'Enter':
				case ' ':
					event.preventDefault();
					choose(current);
					break;
				case 'Escape':
					event.preventDefault();
					close({ restoreFocus: true });
					break;
				case 'Tab':
					close();
					break;
				default:
			}
		});

		$select.addEventListener('change', render);
		$select.addEventListener('stock-select-sync', render);
		$select.addEventListener('focus', () => $button.focus());
		new MutationObserver(syncHidden).observe($select, { attributes: true, attributeFilter: ['hidden'] });

		syncHidden();
		render();
	};

	document.querySelectorAll('select.js-bank-add-stock, select.js-bank-type-stock, select.js-bank-entry-stock').forEach(enhance);

	document.addEventListener('click', (event) => {
		if (openSelect === null) {
			return;
		}

		const insideList = event.target.closest('.stock-select__list');
		const insideButton = event.target.closest('.stock-select__button');

		if (!insideList && !insideButton) {
			openSelect.close();
		}
	});

	window.addEventListener('resize', () => openSelect?.close());
	window.addEventListener('scroll', (event) => {
		if (openSelect !== null && !event.target.closest?.('.stock-select__list')) {
			openSelect.close();
		}
	}, true);
})();
