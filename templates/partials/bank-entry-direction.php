<?php
/** @var array $bankEntryType
 * @var bool $showToggle
 * @var string $selectedDirection */

$selectedDirection = ($selectedDirection ?? 'in') === 'out' ? 'out' : 'in';
$directionLabels = bankEntryDirectionLabels($bankEntryType);
$showToggle = ($showToggle ?? false) && bankTypeSupportsWithdraw($bankEntryType);
?>
<div class="entry-direction js-entry-direction<?php echo $showToggle ? '' : ' entry-direction--hidden'; ?>"
	data-balance-mode="<?php echo htmlspecialchars($bankEntryType['balance_mode']); ?>"
	data-label-in="<?php echo htmlspecialchars($directionLabels['in']); ?>"
	data-label-out="<?php echo htmlspecialchars($directionLabels['out']); ?>">
	<div class="entry-direction__options" role="group" aria-label="Direction">
		<button type="button"
			class="entry-direction__option js-entry-direction-option<?php echo $selectedDirection === 'in' ? ' entry-direction__option--active' : ''; ?>"
			data-direction="in"
			aria-pressed="<?php echo $selectedDirection === 'in' ? 'true' : 'false'; ?>">
			+ Add
		</button>
		<button type="button"
			class="entry-direction__option js-entry-direction-option<?php echo $selectedDirection === 'out' ? ' entry-direction__option--active' : ''; ?>"
			data-direction="out"
			aria-pressed="<?php echo $selectedDirection === 'out' ? 'true' : 'false'; ?>">
			− Withdraw
		</button>
	</div>
	<input type="hidden" name="direction" class="js-entry-direction-input" value="<?php echo htmlspecialchars($selectedDirection); ?>">
</div>
