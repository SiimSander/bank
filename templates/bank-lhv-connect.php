<?php
/** @var string $uri */
?>
<div class="app-shell">
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main">
		<?php
			$href = '/bank/lhv';
			$label = 'Back to LHV';
			include __DIR__ . '/partials/back-link.php';
		?>

		<h1 class="page-title">Connect your bank</h1>
		<p class="page-subtitle">Read-only access via Enable Banking</p>

		<div class="form-card legal-connect-card">
			<?php include __DIR__ . '/partials/legal-disclaimer.php'; ?>

			<div class="legal-connect-card__body">
				<p>Before continuing to LHV, you agree that:</p>
				<ul class="legal-connect-card__list">
					<li>You will authenticate with your bank through Enable Banking.</li>
					<li>We may import transaction date, amount, and description (note truncated to 150 characters) into <?php echo htmlspecialchars(appDisplayName()); ?>.</li>
					<li>We store your IBAN and connection expiry to manage sync.</li>
					<li>Automatic sync runs at most four times per 24 hours per connection.</li>
					<li>You can disconnect anytime in Settings and optionally keep imported entries.</li>
				</ul>
				<p>See our <a href="<?php echo htmlspecialchars(legalPageHref('/privacy', null, 'bank-data')); ?>">Privacy Policy — bank account data</a> for details.</p>
				<p class="legal-connect-card__attribution">Bank connection powered by Enable Banking.</p>
			</div>

			<form method="POST" action="/bank/lhv/connect" class="legal-connect-card__form">
				<?php echo csrfField(); ?>
				<label class="legal-connect-card__checkbox">
					<input type="checkbox" name="accept_bank_ais" value="1" required>
					<span>I consent to bank account information access as described above.</span>
				</label>
				<button type="submit" class="btn btn-primary">Continue to LHV</button>
			</form>
		</div>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
