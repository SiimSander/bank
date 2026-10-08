<?php
/** @var list<array> $historyMonths
 * @var string $uri */

require_once __DIR__ . '/partials/icons.php';
?>
<div class="app-shell">
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main">
		<?php
			$href = '/habits';
			$label = 'Back';
			include __DIR__ . '/partials/back-link.php';
		?>

		<h1 class="page-title">Habits History</h1>

		<?php if (empty($historyMonths)): ?>
			<p class="empty-state">No habits yet — add one on the Habits page.</p>
		<?php endif; ?>

		<div class="habits-history-grid">
		<?php foreach ($historyMonths as $month): ?>
			<section class="habit-history-card">
				<?php $isCurrentMonth = $month['month_start'] === Clock::monthStart(); ?>
				<h2 class="habit-history-card__title"><?php echo date('F Y', strtotime($month['month_start'])); ?><?php if ($isCurrentMonth): ?> <span class="habit-history-card__badge">Current</span><?php endif; ?></h2>

				<?php echo renderHabitChartSvg($month, $isCurrentMonth); ?>

				<div class="habit-counts">
					<p class="habit-counts__total">
						<span class="habit-counts__label">Overall</span>
						<span class="habit-counts__numbers">
							<span class="habit-counts__done"><?php echo $month['done']; ?></span>
							/
							<span class="habit-counts__failed"><?php echo $month['failed']; ?></span>
						</span>
					</p>
					<?php foreach ($month['habits'] as $index => $habit): ?>
						<p class="habit-counts__row">
							<span class="habit-counts__label"><?php echo $index + 1; ?>. <?php echo htmlspecialchars($habit['title']); ?></span>
							<span class="habit-counts__numbers">
								<span class="habit-counts__done"><?php echo $habit['done']; ?></span>
								/
								<span class="habit-counts__failed"><?php echo $habit['failed']; ?></span>
							</span>
						</p>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
		</div>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
