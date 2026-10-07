<div class="auth-page">
	<div class="auth-card">
		<h1>Forgot password</h1>

		<?php if (!empty($message)): ?>
			<p class="auth-message"><?php echo htmlspecialchars($message); ?></p>
		<?php endif; ?>

		<?php if (!empty($error)): ?>
			<p class="auth-error"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>

		<form class="auth-form" method="POST" action="/forgot-password">
			<?php echo csrfField(); ?>
			<label>
				Email
				<input type="email" name="email" required autocomplete="email" value="<?php echo htmlspecialchars($email ?? ''); ?>">
			</label>
			<button class="btn btn-primary" type="submit">Send reset link</button>
		</form>

		<p class="auth-footer">
			<a href="/login">Back to log in</a>
		</p>
	</div>
</div>
