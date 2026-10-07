<div class="email-verify-banner">
	<p class="email-verify-banner__text">Verify your email to connect and sync your bank.</p>
	<form class="email-verify-banner__form" method="POST" action="/verify-email/resend">
		<?php echo csrfField(); ?>
		<button type="submit" class="btn-pill">Resend verification email</button>
	</form>
</div>
