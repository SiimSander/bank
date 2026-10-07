<?php
/** @var string $rangeFilterBaseUrl
 * @var array<string, string> $rangeFilterExtraParams
 * @var string $range
 * @var array{range: string, start: string|null, end: string} $dateRange */

require_once __DIR__ . '/icons.php';

$rangeFilterExtraParams = $rangeFilterExtraParams ?? [];
$rangePresets = ['all' => 'All time', 'week' => 'This week', 'month' => 'This month'];
$isCustomRange = $range === 'custom' && $dateRange['start'] !== null;
?>
<div class="bank-range-filter js-range-filter">
	<?php foreach ($rangePresets as $presetRange => $presetLabel): ?>
		<a class="bank-range-filter__link<?php echo $range === $presetRange ? ' bank-range-filter__link--active' : ''; ?>" href="<?php echo htmlspecialchars($rangeFilterBaseUrl . '?' . http_build_query(['range' => $presetRange] + $rangeFilterExtraParams)); ?>"><?php echo htmlspecialchars($presetLabel); ?></a>
	<?php endforeach; ?>
	<div class="range-picker js-range-picker"
		data-base-url="<?php echo htmlspecialchars($rangeFilterBaseUrl); ?>"
		data-extra-params="<?php echo htmlspecialchars(json_encode($rangeFilterExtraParams === [] ? new stdClass() : $rangeFilterExtraParams)); ?>"
		data-from="<?php echo $isCustomRange ? htmlspecialchars($dateRange['start']) : ''; ?>"
		data-to="<?php echo $isCustomRange ? htmlspecialchars($dateRange['end']) : ''; ?>"
		data-max="<?php echo date('Y-m-d'); ?>">
		<button type="button" class="bank-range-filter__link range-picker__trigger js-range-picker-trigger<?php echo $isCustomRange ? ' bank-range-filter__link--active' : ''; ?>" aria-haspopup="dialog" aria-expanded="false" aria-label="Select custom date range">
			<?php icon('calendar', 'range-picker__icon'); ?>
			<?php if ($isCustomRange): ?>
				<span class="range-picker__label"><?php echo htmlspecialchars(formatBankDateRangeShort($dateRange['start'], $dateRange['end'])); ?></span>
			<?php endif; ?>
		</button>
	</div>
</div>
