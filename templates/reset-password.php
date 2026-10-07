<div class="auth-page">
	<div class="auth-card">
		<h1>Reset password</h1>

		<?php if (!empty($error)): ?>
			<p class="auth-error"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>

		<?php if ($tokenValid): ?>
			<form class="auth-form" method="POST" action="/reset-password">
				<?php echo csrfField(); ?>
				<input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
				<label>
					New password
					<input type="password" name="password" required autocomplete="new-password" minlength="8">
				</label>
				<label>
					Confirm password
					<input type="password" name="password_confirm" required autocomplete="new-password" minlength="8">
				</label>
				<button class="btn btn-primary" type="submit">Update password</button>
			</form>
		<?php else: ?>
			<p class="auth-footer">
				<a href="/forgot-password">Request a new reset link</a>
			</p>
		<?php endif; ?>

		<p class="auth-footer">
			<a href="/login">Back to log in</a>
		</p>
	</div>
</div>
