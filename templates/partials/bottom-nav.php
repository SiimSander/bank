<?php
require_once __DIR__ . '/icons.php';
/** @var string $uri */

$isHome = $uri === '/landing';
$isHabits = str_starts_with($uri, '/habits');
$isBank = str_starts_with($uri, '/bank');
$isStockGoals = $uri === '/stock-goals';
$isCalculator = $uri === '/calculator';
?>
<nav class="bottom-nav" aria-label="Main navigation">
	<a class="bottom-nav__link<?php echo $isHome ? ' bottom-nav__link--active' : ''; ?>" href="/landing">
		<?php icon('home', 'bottom-nav__icon'); ?>
		<span class="bottom-nav__label">Home</span>
	</a>
	<a class="bottom-nav__link<?php echo $isHabits ? ' bottom-nav__link--active' : ''; ?>" href="/habits">
		<?php icon('target', 'bottom-nav__icon'); ?>
		<span class="bottom-nav__label">Habits</span>
	</a>
	<a class="bottom-nav__link<?php echo $isBank ? ' bottom-nav__link--active' : ''; ?>" href="/bank">
		<?php icon('wallet', 'bottom-nav__icon'); ?>
		<span class="bottom-nav__label">Bank</span>
	</a>
	<a class="bottom-nav__link<?php echo $isStockGoals ? ' bottom-nav__link--active' : ''; ?>" href="/stock-goals">
		<?php icon('stock-goals', 'bottom-nav__icon'); ?>
		<span class="bottom-nav__label">Goals</span>
	</a>
	<a class="bottom-nav__link<?php echo $isCalculator ? ' bottom-nav__link--active' : ''; ?>" href="/calculator">
		<?php icon('calculator', 'bottom-nav__icon'); ?>
		<span class="bottom-nav__label">Calculator</span>
	</a>
</nav>
