<?php
require_once __DIR__ . '/icons.php';
/** @var string $href
 * @var string $label */
?>
<a class="back-link" href="<?php echo htmlspecialchars($href); ?>">
	<?php icon('arrow-left', 'back-link__icon'); ?>
	<?php echo htmlspecialchars($label); ?>
</a>
