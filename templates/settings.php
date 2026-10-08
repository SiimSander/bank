<?php
/** @var array $account
 * @var string|null $error
 * @var string|null $success
 * @var array<int, array{bank_account_uid: string, iban: string, valid_until: string, is_main_account: int, last_synced_at: string|null}> $bankConnections
 * @var bool $needsLegalReconsent
 * @var string $uri */
$bankConnections = $bankConnections ?? [];
$needsLegalReconsent = $needsLegalReconsent ?? false;

require_once __DIR__ . '/partials/icons.php';

$emailVerified = $account['email_verified_at'] !== null;
$settingsReturnPath = settingsReturnPath();
?>
<div class="app-shell">
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main">
		<?php
			$href = $settingsReturnPath;
			$label = settingsReturnLabel($settingsReturnPath);
			include __DIR__ . '/partials/back-link.php';
		?>

		<h1 class="page-title">Settings</h1>
		<p class="page-subtitle">Account, security, and your data.</p>

		<?php if ($success !== null): ?>
			<p class="settings-message settings-message--success"><?php echo htmlspecialchars($success); ?></p>
		<?php endif; ?>

		<?php if ($error !== null): ?>
			<p class="settings-message settings-message--error"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>

		<?php if ($needsLegalReconsent): ?>
			<?php include __DIR__ . '/partials/legal-reconsent-banner.php'; ?>
		<?php endif; ?>

		<section class="settings-card">
			<h2 class="settings-card__title">Account</h2>
			<dl class="settings-dl">
				<div class="settings-dl__row">
					<dt>Name</dt>
					<dd><?php echo htmlspecialchars($account['name']); ?></dd>
				</div>
				<div class="settings-dl__row">
					<dt>Username</dt>
					<dd><?php echo htmlspecialchars($account['username']); ?></dd>
				</div>
				<div class="settings-dl__row">
					<dt>Email</dt>
					<dd>
						<?php echo htmlspecialchars($account['email']); ?>
						<?php if ($emailVerified): ?>
							<span class="settings-badge settings-badge--verified">Verified</span>
						<?php else: ?>
							<span class="settings-badge settings-badge--pending">Not verified</span>
						<?php endif; ?>
					</dd>
				</div>
			</dl>
			<?php if (!$emailVerified): ?>
				<form method="POST" action="/verify-email/resend" class="settings-inline-form">
					<?php echo csrfField(); ?>
					<button type="submit" class="btn btn-secondary">Resend verification email</button>
				</form>
			<?php endif; ?>
		</section>

		<section class="settings-card">
			<h2 class="settings-card__title">Security</h2>
			<p class="settings-card__hint">
				<a href="/forgot-password">Forgot your password?</a> You can reset it by email.
			</p>
			<form class="settings-form" method="POST" action="/settings/change-password">
				<input type="hidden" name="return" value="<?php echo htmlspecialchars($settingsReturnPath); ?>">
				<?php echo csrfField(); ?>
				<label>
					Current password
					<input type="password" name="current_password" required autocomplete="current-password">
				</label>
				<label>
					New password
					<input type="password" name="new_password" required autocomplete="new-password" minlength="8">
				</label>
				<label>
					Confirm new password
					<input type="password" name="new_password_confirm" required autocomplete="new-password" minlength="8">
				</label>
				<button type="submit" class="btn btn-primary">Change password</button>
			</form>
		</section>

		<section class="settings-card">
			<h2 class="settings-card__title">Connected banks</h2>
			<?php if ($bankConnections === []): ?>
				<p class="settings-card__hint">No bank connections. You can connect LHV from the Bank page SOON!</p>
			<?php else: ?>
				<ul class="settings-bank-list">
					<?php foreach ($bankConnections as $connection): ?>
						<li class="settings-bank-list__item">
							<div class="settings-bank-list__meta">
								<strong><?php echo htmlspecialchars($connection['iban']); ?></strong>
								<?php if ($connection['is_main_account']): ?>
									<span class="settings-badge settings-badge--verified">Main</span>
								<?php endif; ?>
								<span class="settings-card__hint">Valid until <?php echo date('Y-m-d', strtotime($connection['valid_until'])); ?></span>
								<?php if (isBankConnectionExpired($connection)): ?>
									<span class="settings-badge settings-badge--pending">Expired</span>
								<?php endif; ?>
							</div>
							<form method="POST" action="/settings/disconnect-bank" class="settings-bank-list__form">
								<input type="hidden" name="return" value="<?php echo htmlspecialchars($settingsReturnPath); ?>">
								<?php echo csrfField(); ?>
								<input type="hidden" name="bank_account_uid" value="<?php echo htmlspecialchars($connection['bank_account_uid']); ?>">
								<label class="settings-form__checkbox">
									<input type="checkbox" name="remove_imported_entries" value="1">
									<span>Also remove imported transactions</span>
								</label>
								<button type="submit" class="btn btn-secondary">Disconnect</button>
							</form>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p class="legal-connect-card__attribution">Bank connection powered by Enable Banking.</p>
		</section>

		<section class="settings-card">
			<h2 class="settings-card__title">Your data</h2>
			<p class="settings-card__hint">
				Download a JSON copy of your profile, bank entries, habits, types, monthly stats, consent history, and bank connection metadata.
				Passwords and bank session tokens are never included.
				See our <a href="<?php echo htmlspecialchars(legalPageHref('/privacy')); ?>">Privacy Policy</a> for details.
			</p>
			<a class="btn btn-secondary" href="/settings/export">Download my data</a>
			<p class="settings-card__hint">
				Deleting your account permanently removes all data, including bank connections. This cannot be undone.
			</p>
		</section>

		<section class="settings-card">
			<h2 class="settings-card__title">Legal</h2>
			<p class="settings-card__hint">
				<a href="<?php echo htmlspecialchars(legalPageHref('/privacy')); ?>">Privacy Policy</a> ·
				<a href="<?php echo htmlspecialchars(legalPageHref('/terms')); ?>">Terms of Use</a> ·
				<a href="<?php echo htmlspecialchars(legalPageHref('/about')); ?>">About</a>
			</p>
		</section>

		<section class="settings-card">
			<h2 class="settings-card__title">How allocations work</h2>
			<div class="settings-faq">
				<p><strong>Mid-month changes:</strong> When you change a type&rsquo;s income % in Configure, the new rate applies from that day forward. Income you already logged keeps the rate that was in effect on each entry date.</p>
				<p><strong>Guaranteed monthly income:</strong> The number in Configure is for projections only. Goals use the income entries you actually add.</p>
				<p><strong>Opening balances:</strong> Setting balances at onboarding does not split money by your plan. Percentages start applying with the next income you record.</p>
			</div>
		</section>

		<section class="settings-card settings-card--danger">
			<h2 class="settings-card__title">Danger zone</h2>
			<p class="settings-card__hint">
				Permanently delete your account and all associated data. This cannot be undone.
			</p>
			<form class="settings-form settings-form--danger" method="POST" action="/settings/delete-account">
				<input type="hidden" name="return" value="<?php echo htmlspecialchars($settingsReturnPath); ?>">
				<?php echo csrfField(); ?>
				<label>
					Confirm username
					<input type="text" name="username_confirm" required autocomplete="off" placeholder="<?php echo htmlspecialchars($account['username']); ?>">
				</label>
				<label>
					Password
					<input type="password" name="password" required autocomplete="current-password">
				</label>
				<button type="submit" class="btn btn-danger">Delete my account</button>
			</form>
		</section>

		<p class="settings-logout">
			<a href="/logout">Log out</a>
		</p>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
