<?php
/** @var string $uri */
$legal = legalConfig();
?>
<footer class="legal-footer">
	<a href="<?php echo htmlspecialchars(legalPageHref('/privacy')); ?>">Privacy Policy</a>
	<span class="legal-footer__sep">·</span>
	<a href="<?php echo htmlspecialchars(legalPageHref('/terms')); ?>">Terms of Use</a>
	<span class="legal-footer__sep">·</span>
	<a href="mailto:<?php echo htmlspecialchars($legal['email']); ?>">Contact</a>
</footer>
