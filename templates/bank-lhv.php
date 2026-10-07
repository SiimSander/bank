<?php
/** @var array<int, array{bank_account_uid: string, iban: string, session_id: string, valid_until: string, is_main_account: int, last_synced_at: string|null, last_sync_attempt_at: string|null}> $connections
 * @var string|null $flash
 * @var string $uri */
?>
<div class="app-shell">
	<?php include __DIR__ . '/partials/app-header.php'; ?>
	<?php include __DIR__ . '/partials/side-nav.php'; ?>

	<main class="app-main">
		<?php
			$href = '/bank';
			$label = 'Back to Bank';
			include __DIR__ . '/partials/back-link.php';
		?>

		<h1 class="page-title">Connect LHV</h1>

		<div class="form-card">
			<?php include __DIR__ . '/partials/legal-disclaimer.php'; ?>

			<?php if ($flash !== null): ?>
				<p class="empty-state"><?php echo htmlspecialchars($flash); ?></p>
			<?php endif; ?>

			<?php if (empty($connections)): ?>
				<p class="empty-state">No LHV accounts connected yet.</p>
			<?php else: ?>
				<p class="page-subtitle">Only your main current account is auto-synced. Internal LHV pots are already reflected as transfers on the main account.</p>
				<ul class="card-list">
					<?php foreach ($connections as $connection): ?>
						<?php $connectionExpired = isBankConnectionExpired($connection); ?>
						<li class="card-list-item">
							<div class="bank-entry-item__view">
								<span class="bank-entry-item__type">
									<?php echo htmlspecialchars($connection['iban']); ?>
									<?php if ($connection['is_main_account']): ?>
										(Main account)
									<?php endif; ?>
								</span>
								<span class="bank-entry-item__note">Valid until <?php echo date('Y-m-d', strtotime($connection['valid_until'])); ?></span>
								<?php if ($connectionExpired): ?>
									<span class="bank-entry-item__pending">Connection expired — reconnect to continue syncing.</span>
								<?php elseif ($connection['is_main_account']): ?>
									<span class="bank-entry-item__note">
										<?php echo $connection['last_synced_at'] !== null
											? 'Last synced ' . date('Y-m-d H:i', strtotime($connection['last_synced_at']))
											: 'Not synced yet'; ?>
									</span>
									<?php if ($connection['last_sync_attempt_at'] !== null && strtotime($connection['last_sync_attempt_at']) > strtotime($connection['last_synced_at'] ?? '1970-01-01')): ?>
										<span class="bank-entry-item__pending">Last attempt failed (<?php echo date('H:i', strtotime($connection['last_sync_attempt_at'])); ?>)</span>
									<?php endif; ?>
									<form method="POST" action="/bank/lhv">
										<?php echo csrfField(); ?>
										<input type="hidden" name="bank_account_uid" value="<?php echo htmlspecialchars($connection['bank_account_uid']); ?>">
										<button class="btn btn-primary" type="submit">Sync now</button>
									</form>
								<?php else: ?>
									<form method="POST" action="/bank/lhv">
										<?php echo csrfField(); ?>
										<input type="hidden" name="do" value="set_main">
										<input type="hidden" name="bank_account_uid" value="<?php echo htmlspecialchars($connection['bank_account_uid']); ?>">
										<button class="btn btn-primary" type="submit">Set as main account</button>
									</form>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<a class="btn btn-primary form-card__action" href="/bank/lhv/connect">Connect another LHV account</a>
			<p class="legal-connect-card__attribution">Bank connection powered by Enable Banking.</p>
		</div>
	</main>
</div>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
