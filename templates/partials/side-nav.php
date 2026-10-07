<?php
require_once __DIR__ . '/icons.php';
/** @var string $uri */

$isHome = $uri === '/landing';
$isWins = str_starts_with($uri, '/wins');
$isBank = str_starts_with($uri, '/bank');
$isStockGoals = $uri === '/stock-goals';
$isCalculator = $uri === '/calculator';
?>
<nav class="side-nav" aria-label="Main navigation">
	<a class="side-nav__link<?php echo $isHome ? ' side-nav__link--active' : ''; ?>" href="/landing">
		<?php icon('home', 'side-nav__icon'); ?>
		<span class="side-nav__label">Home</span>
	</a>
	<a class="side-nav__link<?php echo $isWins ? ' side-nav__link--active' : ''; ?>" href="/wins">
		<?php icon('trophy', 'side-nav__icon'); ?>
		<span class="side-nav__label">Wins</span>
	</a>
	<a class="side-nav__link<?php echo $isBank ? ' side-nav__link--active' : ''; ?>" href="/bank">
		<?php icon('wallet', 'side-nav__icon'); ?>
		<span class="side-nav__label">Bank</span>
	</a>
	<a class="side-nav__link<?php echo $isStockGoals ? ' side-nav__link--active' : ''; ?>" href="/stock-goals">
		<?php icon('stock-goals', 'side-nav__icon'); ?>
		<span class="side-nav__label">Stock Goals</span>
	</a>
	<a class="side-nav__link<?php echo $isCalculator ? ' side-nav__link--active' : ''; ?>" href="/calculator">
		<?php icon('calculator', 'side-nav__icon'); ?>
		<span class="side-nav__label">Calculator</span>
	</a>
</nav>
