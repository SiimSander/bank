<?php
/** @var string $legalTitle
 * @var string $legalVersion
 * @var string $legalUpdated
 * @var string $legalBodyHtml
 * @var string $uri */

$legalBackUrl = legalReturnPath($uri);
$legalBackLabel = legalReturnLabel($legalBackUrl);
?>
<div class="legal-page">
	<div class="legal-page__inner">
		<p class="legal-page__back"><a href="<?php echo htmlspecialchars($legalBackUrl); ?>">&larr; <?php echo htmlspecialchars($legalBackLabel); ?></a></p>
		<h1 class="legal-page__title"><?php echo htmlspecialchars($legalTitle); ?></h1>
		<p class="legal-page__meta">Version <?php echo htmlspecialchars($legalVersion); ?> · Last updated <?php echo htmlspecialchars($legalUpdated); ?></p>
		<div class="legal-prose">
			<?php echo $legalBodyHtml; ?>
		</div>
		<?php include __DIR__ . '/../partials/legal-footer.php'; ?>
	</div>
</div>
