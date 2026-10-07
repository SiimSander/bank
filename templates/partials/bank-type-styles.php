<?php
/** @var array<int, array{slug: string, color_hex: string}> $bankTypes */
if (empty($bankTypes)) {
	return;
}
?>
<style>
.bank-type-vars {
<?php echo renderBankTypeCssVariables($bankTypes); ?>
}
.bank-type-colored {
	color: var(--type-color);
	background-color: var(--type-bg);
}
.bank-type-dot {
	background-color: var(--type-color);
}
.bank-entry-item--dynamic,
.entry-card--dynamic {
	--type-color: var(--type-color-fallback, #888888);
	--type-bg: var(--type-bg-fallback, rgba(136, 136, 136, 0.12));
}
<?php foreach ($bankTypes as $bankType): ?>
.bank-type--<?php echo htmlspecialchars($bankType['slug']); ?> {
	--type-color: var(--type-color-<?php echo htmlspecialchars($bankType['slug']); ?>);
	--type-bg: var(--type-bg-<?php echo htmlspecialchars($bankType['slug']); ?>);
}
<?php endforeach; ?>
</style>
