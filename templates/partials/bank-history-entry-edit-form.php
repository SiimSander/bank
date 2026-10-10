<?php
/** @var array{id: int, method: string, amount: float, note: string|null} $entry
 * @var string $type
 * @var array $currentType
 * @var array<int, array{note: string, label: string}> $investmentNotes */

$entryNote = (string) $entry['note'];
$historyStockNotes = $type === 'investments' ? ($investmentNotes ?? []) : [];

if ($historyStockNotes !== [] && $entryNote !== '' && !in_array($entryNote, array_column($historyStockNotes, 'note'), true)) {
	$historyStockNotes[] = ['note' => $entryNote, 'label' => investmentNoteTickerLabel($entryNote)];
}

$entryUsesStock = $entryNote !== '' && in_array($entryNote, array_column($historyStockNotes, 'note'), true);
?>
<div class="entry-card__edit js-bank-entry-edit-form" hidden>
	<select class="js-bank-entry-method" aria-label="Method">
		<?php foreach (BANK_ENTRY_METHODS as $method): ?>
			<option value="<?php echo $method; ?>"<?php echo $method === $entry['method'] ? ' selected' : ''; ?>><?php echo bankEntryMethodLabel($method); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
		$bankEntryType = $currentType;
		$showToggle = bankTypeSupportsWithdraw($currentType);
		$selectedDirection = $entry['amount'] < 0 ? 'out' : 'in';
		include __DIR__ . '/bank-entry-direction.php';
	?>
	<input type="number" step="0.01" min="0.01" class="js-bank-entry-amount" value="<?php echo abs((float) $entry['amount']); ?>" aria-label="Amount">
	<?php if ($historyStockNotes !== []): ?>
		<select class="js-bank-entry-stock" aria-label="Stock">
			<?php foreach ($historyStockNotes as $stockNote): ?>
				<option value="<?php echo htmlspecialchars($stockNote['note']); ?>"<?php echo stockOptionAttributes($stockNote['note'], $stockLogoFor ?? null); ?><?php echo ($entryUsesStock && $stockNote['note'] === $entryNote) ? ' selected' : ''; ?>><?php echo htmlspecialchars($stockNote['note']); ?></option>
			<?php endforeach; ?>
			<option value="" data-new-stock="1"<?php echo $entryUsesStock ? '' : ' selected'; ?>>+ New stock...</option>
		</select>
	<?php endif; ?>
	<input type="text" class="js-bank-entry-note" maxlength="150" value="<?php echo htmlspecialchars($entryNote); ?>" data-original-note="<?php echo htmlspecialchars($entryNote); ?>" placeholder="Note" aria-label="Note">
	<div class="entry-card__edit-actions">
		<button type="button" class="btn btn-save js-bank-entry-save">Save</button>
		<button type="button" class="btn btn-secondary js-bank-edit-cancel">Cancel</button>
	</div>
</div>
