<?php
/** @var string $uri */

$legalBackUrl = legalReturnPath($uri);
$legalBackLabel = legalReturnLabel($legalBackUrl);
?>
<div class="legal-page">
	<div class="legal-page__inner">
		<p class="legal-page__back"><a href="<?php echo htmlspecialchars($legalBackUrl); ?>">&larr; <?php echo htmlspecialchars($legalBackLabel); ?></a></p>
		<h1 class="legal-page__title">About <?php echo htmlspecialchars(appDisplayName()); ?></h1>
		<div class="legal-prose">
			<p><?php echo htmlspecialchars(appDisplayName()); ?> helps you track daily wins, income allocation, and savings goals.</p>
			<p>It is a personal finance tracker — not a bank and not financial advice. You can record entries manually or import LHV transactions via Enable Banking.</p>
			<p>Read our <a href="<?php echo htmlspecialchars(legalPageHref('/privacy')); ?>">Privacy Policy</a> and <a href="<?php echo htmlspecialchars(legalPageHref('/terms')); ?>">Terms of Use</a> before signing up.</p>
		</div>
		<?php include __DIR__ . '/partials/legal-footer.php'; ?>
	</div>
</div>
