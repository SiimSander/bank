// Browsers let <input type="number"> accept "e", "E", "+" and "-" ("12e12" is a valid number to them).
// Every number field in the app is a non-negative plain decimal, so those characters are blocked.
(() => {
	const SELECTOR = 'input[type="number"]';
	const BLOCKED_KEYS = new Set(['e', 'E', '+', '-']);
	const ALLOWED_TEXT = /^\d*[.,]?\d*$/;
	const ALLOWED_PASTE = /^\s*\d+([.,]\d+)?\s*$/;

	const isNumberInput = (target) => target instanceof Element && target.matches(SELECTOR);

	document.addEventListener('keydown', (event) => {
		if (!isNumberInput(event.target) || event.ctrlKey || event.metaKey || event.altKey) {
			return;
		}

		if (BLOCKED_KEYS.has(event.key)) {
			event.preventDefault();
		}
	});

	document.addEventListener('beforeinput', (event) => {
		if (!isNumberInput(event.target) || typeof event.data !== 'string') {
			return;
		}

		if (!ALLOWED_TEXT.test(event.data)) {
			event.preventDefault();
		}
	});

	// Must match MAX_MONEY_AMOUNT in src/numericInput.php. Inputs with their own max keep it.
	const DEFAULT_MAX = 1000000000;
	const MAX_LENGTH = 15;
	const lastValidValues = new WeakMap();

	const maxFor = (input) => {
		const max = Number(input.max);

		return input.max !== '' && Number.isFinite(max) ? max : DEFAULT_MAX;
	};

	// A number input exposes no caret position, so an oversized value is undone after the fact.
	// Capture phase runs before the page scripts' own input listeners, which then see the restored value.
	document.addEventListener('input', (event) => {
		if (!isNumberInput(event.target)) {
			return;
		}

		const input = event.target;
		const value = input.value;

		if (value.length > MAX_LENGTH || (value !== '' && Number(value) > maxFor(input))) {
			input.value = lastValidValues.get(input) ?? '';

			return;
		}

		lastValidValues.set(input, value);
	}, true);

	document.addEventListener('focusin', (event) => {
		if (isNumberInput(event.target) && !lastValidValues.has(event.target)) {
			lastValidValues.set(event.target, event.target.value);
		}
	});

	document.addEventListener('paste', (event) => {
		if (!isNumberInput(event.target)) {
			return;
		}

		const text = event.clipboardData ? event.clipboardData.getData('text') : '';

		if (!ALLOWED_PASTE.test(text)) {
			event.preventDefault();
		}
	});

	document.addEventListener('drop', (event) => {
		if (!isNumberInput(event.target)) {
			return;
		}

		const text = event.dataTransfer ? event.dataTransfer.getData('text') : '';

		if (!ALLOWED_PASTE.test(text)) {
			event.preventDefault();
		}
	});
})();
