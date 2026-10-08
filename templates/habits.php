<?php
/** @var array $month
 * @var string $selectedDate
 * @var string $today
 * @var list<array{id: int, title: string, status: string}> $dayItems
 * @var list<string> $editableDates
 * @var list<array{id: int, title: string}> $boardHabits
 * @var string $uri */

require_once __DIR__ . '/partials/icons.php';

$isTodaySelected = $selectedDate === $today;
?>
<div class="app-shell">
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main">
		<div class="habits-page__content js-habits-page" data-today="<?php echo htmlspecialchars($today); ?>">
			<div class="habits-header">
				<div>
					<h1 class="page-title">Habits</h1>
					<span class="habits-header__date"><?php echo date('l, F j', strtotime($selectedDate)); ?></span>
				</div>
			</div>

			<a class="btn-history" href="/habits/history">
				<?php icon('history'); ?>
				History
			</a>

			<div class="habit-chart-card">
				<?php if ($month['habits'] === []): ?>
					<p class="habit-chart-card__empty">Add your first habit below to start the chart.</p>
				<?php endif; ?>
				<div class="js-habit-chart">
					<?php echo renderHabitChartSvg($month, true); ?>
				</div>
			</div>

			<nav class="habit-days" aria-label="Choose a day">
				<?php foreach ($editableDates as $date): ?>
					<a class="habit-days__link<?php echo $date === $selectedDate ? ' habit-days__link--active' : ''; ?>" href="/habits<?php echo $date === $today ? '' : '?date=' . urlencode($date); ?>">
						<span class="habit-days__weekday"><?php echo $date === $today ? 'Today' : date('D', strtotime($date)); ?></span>
						<span class="habit-days__number"><?php echo date('j', strtotime($date)); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>

			<h2 class="habits-section-title"><?php echo $isTodaySelected ? "Today's habits" : 'Habits on ' . date('M j', strtotime($selectedDate)); ?></h2>

			<ul class="habit-list js-habit-day-list" data-date="<?php echo htmlspecialchars($selectedDate); ?>">
				<?php foreach ($dayItems as $item): ?>
					<li class="habit-list-item habit-list-item--<?php echo htmlspecialchars($item['status']); ?>" data-habit-id="<?php echo $item['id']; ?>" data-status="<?php echo htmlspecialchars($item['status']); ?>">
						<span class="habit-list-item__title"><?php echo htmlspecialchars($item['title']); ?></span>
						<button type="button" class="habit-list-item__button habit-list-item__button--done js-habit-status" data-status="done" aria-label="Mark done">
							<?php icon('check'); ?>
						</button>
						<button type="button" class="habit-list-item__button habit-list-item__button--failed js-habit-status" data-status="failed" aria-label="Mark failed">
							<?php icon('x'); ?>
						</button>
					</li>
				<?php endforeach; ?>

				<?php if ($dayItems === []): ?>
					<li class="habit-list-item habit-list-item--empty">No habits for this day.</li>
				<?php endif; ?>
			</ul>

			<h2 class="habits-section-title">Your habits <span class="habits-section-title__count"><?php echo count($boardHabits); ?>/<?php echo HABIT_MAX_COUNT; ?></span></h2>

			<ul class="habit-board js-habit-board">
				<?php foreach ($boardHabits as $index => $habit): ?>
					<li class="habit-board-item" data-habit-id="<?php echo $habit['id']; ?>">
						<span class="habit-board-item__number"><?php echo $index + 1; ?></span>
						<input class="habit-board-item__input js-habit-title" type="text" value="<?php echo htmlspecialchars($habit['title']); ?>" maxlength="<?php echo HABIT_TITLE_MAX_LENGTH; ?>" aria-label="Habit name" autocomplete="off">
						<button type="button" class="habit-board-item__delete js-habit-delete" aria-label="Delete habit">
							<?php icon('trash'); ?>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if (count($boardHabits) < HABIT_MAX_COUNT): ?>
				<form class="habit-add-bar" method="POST" action="/habits">
					<?php echo csrfField(); ?>
					<input type="text" name="title" placeholder="Add a habit..." maxlength="<?php echo HABIT_TITLE_MAX_LENGTH; ?>" required autocomplete="off">
					<button type="submit" class="btn-icon btn-icon--add" aria-label="Add habit">
						<?php icon('plus'); ?>
					</button>
				</form>
			<?php else: ?>
				<p class="habits-limit-note">You have reached the limit of <?php echo HABIT_MAX_COUNT; ?> habits.</p>
			<?php endif; ?>
		</div>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
