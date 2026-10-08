<?php
require_once __DIR__ . '/partials/icons.php';
/** @var string $uri */
?>
<div class="app-shell">
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main">
		<div class="landing-hero">
			<?php if (isset($_SESSION['username'])): ?>
				<h1 class="landing-hero__title">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
			<?php endif; ?>
			<p class="landing-hero__subtitle">What would you like to track today?</p>
		</div>

		<div class="landing-cards">
			<a class="landing-card" href="/habits">
				<span class="landing-card__icon-wrap">
					<?php icon('target'); ?>
				</span>
				<span class="landing-card__content">
					<span class="landing-card__title">Habits</span>
					<span class="landing-card__desc">Track your daily habits on a monthly chart</span>
				</span>
			</a>
			<a class="landing-card" href="/bank">
				<span class="landing-card__icon-wrap landing-card__icon-wrap--bank">
					<?php icon('wallet'); ?>
				</span>
				<span class="landing-card__content">
					<span class="landing-card__title">Bank</span>
					<span class="landing-card__desc">Income, expenses, savings &amp; more</span>
				</span>
			</a>
		</div>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
