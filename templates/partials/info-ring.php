<?php

require_once __DIR__ . '/../../src/goalInfoTooltips.php';

if (!function_exists('renderInfoRing')) {
function renderInfoRing(string $tooltipText): void {
	static $infoRingCounter = 0;
	$infoRingCounter++;
	$tooltipId = 'info-tip-' . $infoRingCounter;
	?>
	<span class="info-ring">
		<button
			type="button"
			class="info-ring__trigger"
			aria-label="More information"
			aria-describedby="<?php echo htmlspecialchars($tooltipId); ?>"
		>
			<span class="info-ring__icon" aria-hidden="true">i</span>
		</button>
		<span
			id="<?php echo htmlspecialchars($tooltipId); ?>"
			class="info-ring__tooltip"
			role="tooltip"
		><?php echo htmlspecialchars($tooltipText); ?></span>
	</span>
	<?php
}
}
