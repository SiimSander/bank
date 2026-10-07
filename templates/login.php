<div class="auth-page">
	<div class="auth-card">
		<h1>Log In</h1>

		<?php if (!empty($message)): ?>
			<p class="auth-message"><?php echo htmlspecialchars($message); ?></p>
		<?php endif; ?>

		<?php if (!empty($error)): ?>
			<p class="auth-error"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>

		<form class="auth-form" method="POST" action="/login">
			<?php echo csrfField(); ?>
			<label>
				Username
				<input type="text" name="username" required autocomplete="username">
			</label>
			<label>
				Password
				<input type="password" name="password" required autocomplete="current-password">
			</label>
			<button class="btn btn-primary" type="submit">Log In</button>
		</form>

		<p class="auth-footer">
			<a href="/forgot-password">Forgot password?</a>
		</p>
		<p class="auth-footer">
			No account? <a href="/signup">Sign Up</a>
		</p>

		<?php include __DIR__ . '/partials/legal-footer.php'; ?>
	</div>
</div>
