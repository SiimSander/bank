<?php
require_once __DIR__ . '/icons.php';
/** @var string $uri */
?>
<header class="app-header">
	<span class="app-header__title">Wins &amp; Bank</span>
	<a class="app-header__profile" href="<?php echo htmlspecialchars(settingsPageHref($uri)); ?>" title="Settings">
		<?php icon('user', 'app-header__profile-icon'); ?>
	</a>
</header>
