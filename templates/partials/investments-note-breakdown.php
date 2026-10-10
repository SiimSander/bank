<?php
/** @var string $range
 * @var array{range: string, start: string|null, end: string} $dateRange
 * @var array<int, array{note: string, amount: float}> $investmentsNoteBreakdown */
?>
<div class="investments-note-breakdown bank-type--investments">
	<h2 class="investments-note-breakdown__title"><?php echo htmlspecialchars(bankDateRangeNoteBreakdownLabel($range, $dateRange)); ?></h2>
	<div class="investments-note-breakdown__list">
		<?php foreach ($investmentsNoteBreakdown as $item): ?>
			<?php
			$noteParts = splitInvestmentNote($item['note']);
			$noteLogo = isset($stockLogoFor) ? $stockLogoFor($item['note']) : null;
			?>
			<div class="investments-note-breakdown__row">
				<span class="investments-note-breakdown__note<?php echo $noteParts['ticker'] === '' ? ' investments-note-breakdown__note--wide' : ''; ?>"><?php echo stockLogoImage($noteLogo['url'] ?? null, 'stock-logo--inline'); ?><?php echo htmlspecialchars($noteParts['name']); ?></span>
				<?php if ($noteParts['ticker'] !== ''): ?>
					<span class="investments-note-breakdown__ticker"><?php echo htmlspecialchars($noteParts['ticker']); ?></span>
				<?php endif; ?>
				<span class="investments-note-breakdown__amount"><?php echo number_format($item['amount'], 2); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
</div>
