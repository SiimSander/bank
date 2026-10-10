<?php
/** @var array<int, array{slug: string, label: string, url: string}> $stockLogoOptions */

if ($stockLogoOptions === []) {
	return;
}
?>
<div class="form-field stock-logo-picker js-stock-logo-picker" data-slugs="<?php echo htmlspecialchars(json_encode(array_column($stockLogoOptions, 'slug')), ENT_QUOTES); ?>" data-aliases="<?php echo htmlspecialchars(json_encode(STOCK_LOGO_ALIASES), ENT_QUOTES); ?>">
	<label>Logo</label>
	<div class="stock-logo-picker__grid" role="radiogroup" aria-label="Logo">
		<button type="button" class="stock-logo-picker__tile stock-logo-picker__tile--none js-stock-logo-tile" role="radio" aria-checked="false"
			data-logo="none" title="No logo" aria-label="No logo">
			<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-dasharray="3 3"/><path d="M6.5 17.5l11-11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
		</button>
		<?php foreach ($stockLogoOptions as $option): ?>
			<button type="button" class="stock-logo-picker__tile js-stock-logo-tile" role="radio" aria-checked="false"
				data-logo="<?php echo htmlspecialchars($option['slug'], ENT_QUOTES); ?>"
				title="<?php echo htmlspecialchars($option['label'], ENT_QUOTES); ?>"
				aria-label="<?php echo htmlspecialchars($option['label'], ENT_QUOTES); ?>">
				<?php echo stockLogoImage($option['url']); ?>
			</button>
		<?php endforeach; ?>
	</div>
	<p class="form-field__hint js-stock-logo-hint" hidden></p>
</div>
