<?php

/**
 * Plain decimal numbers only: digits with an optional fractional part.
 * Rejects exponent notation ("12e12"), signs, hex, whitespace and anything else is_numeric() or a (float) cast accepts.
 */
function isPlainDecimal(mixed $value): bool {
	if (is_int($value)) {
		return true;
	}

	if (is_float($value)) {
		return is_finite($value);
	}

	return is_string($value) && preg_match('/^\d+(\.\d+)?$/', $value) === 1;
}

function parsePlainDecimal(mixed $value): ?float {
	if (is_string($value)) {
		$value = trim($value);
	}

	return isPlainDecimal($value) ? (float) $value : null;
}

// Must match DEFAULT_MAX in public/assets/js/numeric-inputs.js.
// Keeps values (and 12-month projections of them) short enough to fit the layout.
const MAX_MONEY_AMOUNT = 1000000000;

function parseMoneyAmount(mixed $value): ?float {
	$amount = parsePlainDecimal($value);

	return $amount !== null && $amount <= MAX_MONEY_AMOUNT ? $amount : null;
}
