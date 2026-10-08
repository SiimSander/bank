<div class="auth-page">
	<div class="auth-card">
		<h1>Sign Up</h1>

		<?php if (!empty($error)): ?>
			<p class="auth-error"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>

		<form class="auth-form" method="POST" action="/signup">
			<?php echo csrfField(); ?>
			<label>
				Name
				<input type="text" name="name" required maxlength="100" pattern="\p{L}+([ '\-]\p{L}+)*" title="Letters only (spaces, hyphens and apostrophes allowed)" autocomplete="name">
			</label>
			<label>
				Username
				<input type="text" name="username" required maxlength="50" pattern="[A-Za-z0-9]+" title="Letters and numbers only" autocomplete="username">
			</label>
			<label>
				Email
				<input type="email" name="email" required autocomplete="email">
			</label>
			<label>
				Password
				<input type="password" name="password" required autocomplete="new-password" minlength="8">
			</label>
			<label class="auth-form__checkbox">
				<input type="checkbox" name="accept_terms" value="1" required>
				<span>I agree to the <a href="<?php echo htmlspecialchars(legalPageHref('/terms')); ?>" target="_blank" rel="noopener noreferrer">Terms of Use</a> and <a href="<?php echo htmlspecialchars(legalPageHref('/privacy')); ?>" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.</span>
			</label>
			<button class="btn btn-primary" type="submit">Sign Up</button>
		</form>

		<?php include __DIR__ . '/partials/legal-footer.php'; ?>

		<p class="auth-footer">
			Already have an account? <a href="/login">Log In</a>
		</p>
	</div>
</div>
