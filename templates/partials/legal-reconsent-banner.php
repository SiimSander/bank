<div class="legal-reconsent-banner">
	<p>
		Our <?php echo htmlspecialchars(appDisplayName()); ?> Terms or Privacy Policy were updated.
		Please review and accept to continue using bank sync.
	</p>
	<div class="legal-reconsent-banner__actions">
		<a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(legalPageHref('/privacy')); ?>">Privacy Policy</a>
		<a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(legalPageHref('/terms')); ?>">Terms of Use</a>
		<form method="POST" action="/legal/accept-updated">
			<?php echo csrfField(); ?>
			<button type="submit" class="btn btn-primary btn-sm">I accept the updated documents</button>
		</form>
	</div>
</div>
