<?php
/** @var string $pageTitle
 * @var string $content
 * @var string $uri */

$bankReservedRoutes = ['/bank', '/bank/history', '/bank/configure', '/bank/lhv'];
$isBankTypeRoute = preg_match('#^/bank/[a-z0-9_]+$#', $uri) === 1
	&& !in_array($uri, array_merge($bankReservedRoutes, ['/bank/update', '/bank/delete']), true)
	&& !str_starts_with($uri, '/bank/configure/')
	&& !str_starts_with($uri, '/bank/lhv');

$appRoutes = [
	'/landing', '/wins', '/wins/history',
	'/bank', '/bank/history', '/bank/configure', '/bank/lhv',
	'/stock-goals',
	'/calculator',
	'/settings',
];
$isAppPage = in_array($uri, $appRoutes, true) || $isBankTypeRoute;
$bankJsRoutes = ['/bank', '/bank/history', '/bank/configure'];
$loadBankJs = in_array($uri, $bankJsRoutes, true) || $isBankTypeRoute;

$showEmailVerificationBanner = false;
$showLegalReconsentBanner = false;
$flashMessage = null;

if (isset($_SESSION['user_id']) && $isAppPage) {
	$accountId = (int) $_SESSION['user_id'];
	$showEmailVerificationBanner = !isEmailVerified(db(), $accountId);
	$showLegalReconsentBanner = needsLegalReconsent(db(), $accountId)
		&& !in_array($uri, ['/settings', '/privacy', '/terms', '/about'], true);
}

if (isset($_SESSION['flash'])) {
	$flashMessage = $_SESSION['flash'];
	unset($_SESSION['flash']);
}
?>
<!DOCTYPE html>
<html lang="ee">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="<?php echo htmlspecialchars(csrfToken()); ?>">
	<title><?php echo htmlspecialchars($pageTitle); ?></title>
	<link rel="stylesheet" href="/assets/css/index.css">
</head>
<body>
	<?php if ($flashMessage !== null): ?>
		<div class="app-flash"><?php echo htmlspecialchars($flashMessage); ?></div>
	<?php endif; ?>
	<?php if ($showEmailVerificationBanner): ?>
		<?php include __DIR__ . '/partials/email-verify-banner.php'; ?>
	<?php endif; ?>
	<?php if ($showLegalReconsentBanner): ?>
		<?php include __DIR__ . '/partials/legal-reconsent-banner.php'; ?>
	<?php endif; ?>
	<?php echo $content; ?>
	<script src="/assets/js/numeric-inputs.js" defer></script>
	<script src="/assets/js/date-picker.js" defer></script>
	<?php if ($uri === '/bank' || str_starts_with($uri, '/bank/')): ?>
		<script src="/assets/js/range-picker.js" defer></script>
	<?php endif; ?>
	<?php if ($isAppPage): ?>
		<script src="/assets/js/info-ring.js" defer></script>
		<script src="/assets/js/app.js" defer></script>
	<?php endif; ?>
	<?php if ($uri === '/wins' || $uri === '/wins/history'): ?>
		<script src="/assets/js/wins.js" defer></script>
	<?php endif; ?>
	<?php if ($loadBankJs): ?>
		<script src="/assets/js/bank.js" defer></script>
	<?php endif; ?>
	<?php if ($uri === '/bank/configure'): ?>
		<script src="/assets/js/bank-configure.js" defer></script>
	<?php endif; ?>
	<?php if ($uri === '/onboarding'): ?>
		<script src="/assets/js/onboarding.js" defer></script>
	<?php endif; ?>
	<?php if ($uri === '/stock-goals'): ?>
		<script src="/assets/js/stock-goals.js" defer></script>
	<?php endif; ?>
	<?php if ($uri === '/calculator'): ?>
		<script src="/assets/js/calculator.js" defer></script>
	<?php endif; ?>
</body>
</html>
