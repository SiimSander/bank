<?php
/** @var array<int, array{id: int, title: string, status: string}> $items
 * @var array{id: int, card_date: string} $winCard
 * @var string $uri */

require_once __DIR__ . '/partials/icons.php';
?>
<div class="app-shell">
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main">
		<div class="wins-page__content">
		<div class="wins-header">
			<div>
				<h1 class="page-title">Today's Wins</h1>
				<span class="wins-header__date"><?php echo date('l, F j', strtotime($winCard['card_date'])); ?></span>
			</div>
			<button type="button" class="btn-delete js-delete-card-btn" title="Delete today's card">
				<?php icon('trash', 'btn-delete__icon'); ?>
			</button>
		</div>

		<a class="btn-history" href="/wins/history">
			<?php icon('history'); ?>
			History
		</a>

		<ul class="win-list card-list js-win-list-reorderable" data-card-id="<?php echo $winCard['id']; ?>">
			<?php foreach ($items as $item): ?>
				<li class="win-list-item card-list-item card-list-item--<?php echo htmlspecialchars($item['status']); ?>" data-item-id="<?php echo $item['id']; ?>">
					<span class="win-list-item__drag-handle js-drag-handle" role="button" aria-label="Drag to reorder" tabindex="-1">
						<?php icon('grip-vertical'); ?>
					</span>
					<button type="button" class="win-list-item__status js-status-toggle" data-status="done" aria-label="Toggle done">
						<?php if ($item['status'] === 'done' || $item['status'] === 'failed'): ?>
							<?php icon('check'); ?>
						<?php endif; ?>
					</button>
					<span class="win-list-item__title card-list-item__title js-editable-title"><?php echo htmlspecialchars($item['title']); ?></span>
					<button type="button" class="js-fail-btn js-status-btn" data-status="failed" aria-label="Mark failed">
						<?php icon('x'); ?>
					</button>
					<button type="button" class="win-list-item__delete js-delete-item-btn" aria-label="Delete win">
						<?php icon('trash'); ?>
					</button>
					<button type="button" class="js-status-btn status-btn status-btn--done" data-status="done" hidden></button>
				</li>
			<?php endforeach; ?>

			<?php if (empty($items)): ?>
				<li class="win-list-item win-list-item--empty card-list-item card-list-item--empty">No wins yet today — add one!</li>
			<?php endif; ?>
		</ul>

		<form class="win-add-bar" method="POST" action="/wins">
			<?php echo csrfField(); ?>
			<input type="text" name="title" placeholder="Add a win..." maxlength="255" required autocomplete="off">
			<button type="submit" class="btn-icon btn-icon--add" aria-label="Add win">
				<?php icon('plus'); ?>
			</button>
		</form>
		</div>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
