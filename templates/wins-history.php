<?php
/** @var array<int, array{id: int, card_date: string, items: array<int, array{id: int, title: string, status: string}>}> $history
 * @var string $uri */

require_once __DIR__ . '/partials/icons.php';
?>
<div class="app-shell">
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main">
		<?php
			$href = '/wins';
			$label = 'Back';
			include __DIR__ . '/partials/back-link.php';
		?>

		<h1 class="page-title">Wins History</h1>

		<?php if (empty($history)): ?>
			<p class="empty-state">No past cards yet — come back tomorrow!</p>
		<?php endif; ?>

		<div class="wins-history-grid">
		<?php foreach ($history as $card): ?>
			<div class="history-day-card">
				<div class="history-day-card__header">
					<h2 class="history-day-card__title"><?php echo date('l, F j', strtotime($card['card_date'])); ?></h2>
					<button type="button" class="btn-delete js-delete-card-btn" data-card-id="<?php echo $card['id']; ?>" title="Delete this card">
						<?php icon('trash', 'btn-delete__icon'); ?>
					</button>
				</div>

				<ul class="win-list">
					<?php foreach ($card['items'] as $item): ?>
						<li class="win-list-item card-list-item card-list-item--<?php echo htmlspecialchars($item['status']); ?>" data-item-id="<?php echo $item['id']; ?>">
							<button type="button" class="win-list-item__status js-status-btn" data-status="done" aria-label="Mark done">
								<?php if ($item['status'] === 'done' || $item['status'] === 'failed'): ?>
									<?php icon('check'); ?>
								<?php endif; ?>
							</button>
							<span class="win-list-item__title"><?php echo htmlspecialchars($item['title']); ?></span>
							<button type="button" class="js-fail-btn js-status-btn" data-status="failed" aria-label="Mark failed">
								<?php icon('x'); ?>
							</button>
						</li>
					<?php endforeach; ?>

					<?php if (empty($card['items'])): ?>
						<li class="win-list-item win-list-item--empty">No wins logged</li>
					<?php endif; ?>
				</ul>
			</div>
		<?php endforeach; ?>
		</div>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
