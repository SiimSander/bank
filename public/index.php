<?php

require_once __DIR__ . '/../src/bootstrap.php';
registerProductionErrorHandlers();

require_once __DIR__ . '/../src/session.php';
initSecureSession();

/** @var string $title */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Served from a top-level path without extension: `php -S` started without public/router.php answers 404 for missing files under /assets and for missing .css paths.
if ($uri === '/styles') {
	require_once __DIR__ . '/../src/cssBundle.php';

	header('Content-Type: text/css; charset=utf-8');
	header('Cache-Control: ' . (isset($_GET['v']) ? 'public, max-age=31536000, immutable' : 'no-cache'));
	echo buildCssBundle();
	exit;
}

if (str_starts_with($uri, '/assets/')) {
	$assetPath = __DIR__ . $uri;

	if (is_file($assetPath)) {
		$extension = pathinfo($assetPath, PATHINFO_EXTENSION);
		$contentTypes = [
			'css' => 'text/css; charset=utf-8',
			'js' => 'application/javascript; charset=utf-8',
		];

		header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
		readfile($assetPath);
		exit;
	}

	http_response_code(404);
	exit;
}

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/numericInput.php';
require_once __DIR__ . '/../src/cssBundle.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/mail.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/habits.php';
require_once __DIR__ . '/../src/habitChart.php';
require_once __DIR__ . '/../src/bank.php';
require_once __DIR__ . '/../src/stockGoals.php';
require_once __DIR__ . '/../src/enableBanking.php';
require_once __DIR__ . '/../src/lhvSync.php';
require_once __DIR__ . '/../src/plans.php';
require_once __DIR__ . '/../src/compoundInterest.php';
require_once __DIR__ . '/../src/account.php';
require_once __DIR__ . '/../src/legal.php';
require_once __DIR__ . '/../src/legalContent.php';

if (isset($_SESSION['user_id'])) {
	$onboardingAllowlist = [
		'/onboarding', '/onboarding/balances', '/login', '/signup', '/logout',
		'/forgot-password', '/reset-password', '/verify-email', '/verify-email/resend',
		'/about', '/privacy', '/terms', '/legal/accept-updated',
		'/', '/index.php',
	];

	if (!in_array($uri, $onboardingAllowlist, true)) {
		$pdo = db();
		$accountId = $_SESSION['user_id'];

		if (accountNeedsOnboarding($pdo, $accountId)) {
			header('Location: /onboarding');
			exit;
		}

		if (accountNeedsOpeningBalances($pdo, $accountId)) {
			header('Location: /onboarding/balances');
			exit;
		}
	}
}

switch ($uri) {
	case '/health':
		header('Content-Type: application/json; charset=UTF-8');

		$checks = [
			'status' => 'ok',
			'database' => 'ok',
			'log_writable' => filter_var(AppConfig::get('LOG_TO_STDERR', 'false'), FILTER_VALIDATE_BOOLEAN)
				|| is_writable(dirname(AppConfig::resolvePath(AppConfig::get('LOG_PATH', 'storage/logs/app.log') ?? 'storage/logs/app.log'))),
		];

		try {
			db()->query('SELECT 1');
		} catch (Throwable $e) {
			$checks['status'] = 'degraded';
			$checks['database'] = 'error';
			logger()?->error('health', 'Health check database failure', [
				'message' => $e->getMessage(),
			]);
		}

		if (!$checks['log_writable']) {
			$checks['status'] = 'degraded';
		}

		http_response_code($checks['status'] === 'ok' ? 200 : 503);
		echo json_encode($checks, JSON_UNESCAPED_SLASHES);
		exit;
	case '/':
	case '/index.php':
		require __DIR__ . '/../src/config.php';
		$pageTitle = $title;
		$homeMessage = isset($_GET['account_deleted'])
			? 'Your account has been deleted.'
			: null;
		ob_start();
		include __DIR__ . '/../templates/home.php';
		$content = ob_get_clean();
		break;
	case '/signup':
		$pageTitle = 'Sign Up';
		$error = null;

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			requireCsrfToken();
			$name = $_POST['name'] ?? '';
			$username = $_POST['username'] ?? '';
			$email = $_POST['email'] ?? '';
			$password = $_POST['password'] ?? '';
			$acceptedTerms = filter_var($_POST['accept_terms'] ?? false, FILTER_VALIDATE_BOOLEAN);

			if (!$acceptedTerms) {
				$error = 'You must agree to the Terms of Use and Privacy Policy.';
			} else {
				$pdo = db();
				$result = signup($pdo, $name, $username, $email, $password);

				if ($result === true) {
					$account = getAccountByUsername($pdo, $username);
					if ($account !== null) {
						recordSignupConsents($pdo, (int) $account['id']);
						regenerateSessionOnLogin();
						$_SESSION['user_id'] = $account['id'];
						$_SESSION['username'] = $account['username'];
						$_SESSION['name'] = $account['name'];
						$_SESSION['flash'] = 'Account created. We sent a verification email to ' . $account['email'] . ' - open the link in it to verify your address.';
						header('Location: /onboarding');
						exit;
					}

					header('Location: /login');
					exit;
				}

				$error = $result;
			}
		}

		ob_start();
		include __DIR__ . '/../templates/signup.php';
		$content = ob_get_clean();
		break;
	case '/login':
		$pageTitle = 'Log In';
		$error = null;
		$message = null;

		if (isset($_GET['reset'])) {
			$message = 'Your password has been updated. You can log in now.';
		}
		if (isset($_GET['verified'])) {
			$message = 'Email verified. You can log in now.';
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			requireCsrfToken();
			$username = $_POST['username'] ?? '';
			$password = $_POST['password'] ?? '';

			$result = login(db(), $username, $password);

			if (is_array($result)) {
				regenerateSessionOnLogin();
				$_SESSION['user_id'] = $result['id'];
				$_SESSION['username'] = $result['username'];
				$_SESSION['name'] = $result['name'];
				header('Location: /landing');
				exit;
			}

			$error = is_string($result) ? $result : 'Invalid username or password';
		}

		ob_start();
		include __DIR__ . '/../templates/login.php';
		$content = ob_get_clean();
		break;
	case '/forgot-password':
		$pageTitle = 'Forgot Password';
		$error = null;
		$message = null;
		$email = '';

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			requireCsrfToken();
			$email = trim($_POST['email'] ?? '');
			requestPasswordReset(db(), $email);
			$message = 'If an account exists for that email, a reset link has been sent.';
		}

		ob_start();
		include __DIR__ . '/../templates/forgot-password.php';
		$content = ob_get_clean();
		break;
	case '/reset-password':
		$pageTitle = 'Reset Password';
		$error = null;
		$token = $_GET['token'] ?? $_POST['token'] ?? '';
		$tokenValid = $token !== '' && isPasswordResetTokenValid(db(), $token);

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			requireCsrfToken();
			$password = $_POST['password'] ?? '';
			$passwordConfirm = $_POST['password_confirm'] ?? '';

			if ($password !== $passwordConfirm) {
				$error = 'Passwords do not match.';
			} else {
				$result = resetPassword(db(), $token, $password);

				if ($result === true) {
					header('Location: /login?reset=1');
					exit;
				}

				$error = $result;
				$tokenValid = false;
			}
		}

		ob_start();
		include __DIR__ . '/../templates/reset-password.php';
		$content = ob_get_clean();
		break;
	case '/verify-email':
		if (verifyEmail(db(), $_GET['token'] ?? '')) {
			header('Location: /login?verified=1');
			exit;
		}

		http_response_code(400);
		$pageTitle = 'Verification Failed';
		$content = '<div class="auth-page"><div class="auth-card"><h1>Verification failed</h1><p class="auth-error">This verification link is invalid or has expired.</p><p class="auth-footer"><a href="/login">Back to log in</a></p></div></div>';
		break;
	case '/verify-email/resend':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			requireCsrfToken();
			sendEmailVerificationForAccount(db(), (int) $_SESSION['user_id']);
			$_SESSION['flash'] = 'Verification email sent.';
		}

		header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/bank'));
		exit;
	case '/landing':
		$pageTitle = 'Welcome';
		ob_start();
		include __DIR__ . '/../templates/landing.php';
		$content = ob_get_clean();
		break;
	case '/stock-goals':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		$pdo = db();
		$accountId = (int) $_SESSION['user_id'];
		$monthBounds = getStockGoalMonthBounds($pdo, $accountId);
		$selectedMonth = clampStockGoalMonth($_GET['month'] ?? null, $monthBounds);
		$isCurrentStockMonth = $selectedMonth === $monthBounds['latest'];
		$stockGoalRows = getStockGoalRows($pdo, $accountId, $selectedMonth);
		$stockGoalSummary = summarizeStockGoalRows($stockGoalRows);
		$stockGoalChart = getStockGoalChartSeries($pdo, $accountId);

		$pageTitle = 'Stock Goals';
		ob_start();
		include __DIR__ . '/../templates/stock-goals.php';
		$content = ob_get_clean();
		break;
	case '/stock-goals/save':
		header('Content-Type: application/json');

		if (!isset($_SESSION['user_id'])) {
			http_response_code(401);
			echo json_encode(['success' => false]);
			exit;
		}

		requireCsrfToken(true);

		$result = setStockGoal(
			db(),
			(int) $_SESSION['user_id'],
			(string) ($_POST['note'] ?? ''),
			parseMoneyAmount($_POST['amount'] ?? null) ?? 0.0,
			($_POST['new_stock'] ?? '') === '1'
		);

		if ($result !== true) {
			http_response_code(422);
			echo json_encode(['success' => false, 'error' => $result]);
			exit;
		}

		echo json_encode(['success' => true]);
		exit;
	case '/stock-goals/color':
		header('Content-Type: application/json');

		if (!isset($_SESSION['user_id'])) {
			http_response_code(401);
			echo json_encode(['success' => false]);
			exit;
		}

		requireCsrfToken(true);

		$result = setStockColor(
			db(),
			(int) $_SESSION['user_id'],
			(string) ($_POST['note'] ?? ''),
			(string) ($_POST['color'] ?? '')
		);

		if ($result !== true) {
			http_response_code(422);
			echo json_encode(['success' => false, 'error' => $result]);
			exit;
		}

		echo json_encode(['success' => true]);
		exit;
	case '/calculator':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		$pageTitle = 'Calculator';
		ob_start();
		include __DIR__ . '/../templates/calculator.php';
		$content = ob_get_clean();
		break;
	case '/onboarding':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		$pdo = db();
		$accountId = $_SESSION['user_id'];

		if (!accountNeedsOnboarding($pdo, $accountId) && accountNeedsOpeningBalances($pdo, $accountId)) {
			header('Location: /onboarding/balances');
			exit;
		}

		if (!accountNeedsOnboarding($pdo, $accountId) && !accountNeedsOpeningBalances($pdo, $accountId)) {
			header('Location: /bank');
			exit;
		}

		$pageTitle = 'Get Started';
		$plans = presetPlanDefinitions();
		$error = null;
		$incomeValue = '';
		$selectedPlan = '';

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			requireCsrfToken();
			$incomeValue = trim($_POST['guaranteed_monthly_income'] ?? '');
			$selectedPlan = $_POST['plan'] ?? '';

			if (parseMoneyAmount($incomeValue) === null) {
				$error = 'Enter your guaranteed monthly income as 0 or a positive number up to 1,000,000,000.';
			} elseif (!isValidPlanKey($selectedPlan)) {
				$error = 'Choose a plan to continue.';
			} else {
				setAccountGuaranteedIncome($pdo, $accountId, (float) $incomeValue);
				applyPlanToAccount($pdo, $accountId, $selectedPlan);
				header('Location: /onboarding/balances');
				exit;
			}
		}

		ob_start();
		include __DIR__ . '/../templates/onboarding.php';
		$content = ob_get_clean();
		break;
	case '/onboarding/balances':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		$pdo = db();
		$accountId = $_SESSION['user_id'];

		if (accountNeedsOnboarding($pdo, $accountId)) {
			header('Location: /onboarding');
			exit;
		}

		if (!accountNeedsOpeningBalances($pdo, $accountId)) {
			header('Location: /bank');
			exit;
		}

		$pageTitle = 'Sync Balances';
		$error = null;
		$bankBalanceValue = '';
		$cashBalanceValue = '';

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			requireCsrfToken();
			$bankBalanceValue = trim($_POST['bank_balance'] ?? '');
			$cashBalanceValue = trim($_POST['cash_balance'] ?? '');

			if (parseMoneyAmount($bankBalanceValue) === null) {
				$error = 'Enter your current bank balance as zero or a positive number up to 1,000,000,000.';
			} elseif (parseMoneyAmount($cashBalanceValue) === null) {
				$error = 'Enter your current cash balance as zero or a positive number up to 1,000,000,000.';
			} elseif (!applyOpeningBalances($pdo, $accountId, (float) $bankBalanceValue, (float) $cashBalanceValue)) {
				$error = 'Could not save your opening balances. Try again.';
			} else {
				markOpeningBalancesSet($pdo, $accountId);
				header('Location: /bank/configure');
				exit;
			}
		}

		ob_start();
		include __DIR__ . '/../templates/onboarding-balances.php';
		$content = ob_get_clean();
		break;
	case '/wins':
		header('Location: /habits', true, 301);
		exit;
	case '/wins/history':
		header('Location: /habits/history', true, 301);
		exit;
	case '/habits':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}
		$pdo = db();
		$userId = (int) $_SESSION['user_id'];

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			requireCsrfToken();
			$result = createHabit($pdo, $userId, (string) ($_POST['title'] ?? ''));

			if (!$result['success']) {
				$_SESSION['flash'] = $result['error'];
			}

			header('Location: /habits');
			exit;
		}

		$pageTitle = 'Habits';
		$today = Clock::today();
		$selectedDate = (string) ($_GET['date'] ?? $today);

		if (!isHabitDateEditable($selectedDate, $today)) {
			$selectedDate = $today;
		}

		$month = buildHabitMonth($pdo, $userId, Clock::monthStart());
		$boardHabits = getHabitsForMonth($pdo, $userId, Clock::monthStart());
		$dayItems = getHabitDayItems($pdo, $userId, $selectedDate);
		$editableDates = getEditableHabitDates($today);
		ob_start();
		include __DIR__ . '/../templates/habits.php';
		$content = ob_get_clean();
		break;
	case '/habits/history':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}
		$pageTitle = 'Habits History';
		$pdo = db();
		$historyMonths = array_map(
			static fn(string $monthStart): array => buildHabitMonth($pdo, (int) $_SESSION['user_id'], $monthStart),
			getHabitHistoryMonths($pdo, (int) $_SESSION['user_id'], true)
		);
		ob_start();
		include __DIR__ . '/../templates/habits-history.php';
		$content = ob_get_clean();
		break;
	case '/habits/status':
	case '/habits/rename':
	case '/habits/delete':
		header('Content-Type: application/json');

		if (!isset($_SESSION['user_id'])) {
			http_response_code(401);
			echo json_encode(['success' => false]);
			exit;
		}

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			http_response_code(405);
			echo json_encode(['success' => false]);
			exit;
		}

		requireCsrfToken(true);

		$pdo = db();
		$userId = (int) $_SESSION['user_id'];
		$habitId = (int) ($_POST['habitId'] ?? 0);

		if ($uri === '/habits/status') {
			$status = (string) ($_POST['status'] ?? '');
			$updated = setHabitStatus($pdo, $userId, $habitId, (string) ($_POST['date'] ?? ''), $status);

			echo json_encode(['success' => $updated, 'status' => $status]);
		} elseif ($uri === '/habits/rename') {
			echo json_encode(renameHabit($pdo, $userId, $habitId, (string) ($_POST['title'] ?? '')));
		} else {
			echo json_encode(['success' => deleteHabit($pdo, $userId, $habitId)]);
		}
		exit;
	case '/bank':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}
		$pdo = db();

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			requireCsrfToken();
			$type = $_POST['type'] ?? '';
			$method = $_POST['method'] ?? '';
			$direction = $_POST['direction'] ?? 'in';
			$amount = parseMoneyAmount($_POST['amount'] ?? null) ?? 0.0;
			$note = trim($_POST['note'] ?? '');
			$bankType = getBankTypeBySlug($pdo, $_SESSION['user_id'], $type);
			$normalizedAmount = $bankType !== null
				? normalizeBankEntryAmount($bankType, $direction, $amount)
				: null;

			if ($normalizedAmount !== null) {
				createBankEntry($pdo, $_SESSION['user_id'], $type, $method, $normalizedAmount, $note !== '' ? $note : null);
			}

			header('Location: /bank');
			exit;
		}

		$dateRange = parseBankDateRange($_GET['range'] ?? 'all', $_GET['from'] ?? null, $_GET['to'] ?? null);
		$range = $dateRange['range'];

		autoSyncMainLhvAccount($pdo, $_SESSION['user_id']);
		BankRequestCache::enable();

		$todayEntries = getTodayBankEntries($pdo, $_SESSION['user_id']);
		$balance = getBankBalance($pdo, $_SESSION['user_id']);
		$cashBalance = getCashBalance($pdo, $_SESSION['user_id']);
		$bankTypes = getActiveBankTypes($pdo, $_SESSION['user_id']);
		$visibleBankTypes = getVisibleBankTypes($pdo, $_SESSION['user_id']);
		$addFormBankTypes = getAddFormBankTypes($pdo, $_SESSION['user_id']);
		$potBalances = getPotBalances($pdo, $_SESSION['user_id']);
		$investmentNotes = getInvestmentNotes($pdo, $_SESSION['user_id']);
		$history = getBankHistoryBetween($pdo, $_SESSION['user_id'], $dateRange['start'], $dateRange['end']);
		$totals = sumBankHistoryTotals($history, $bankTypes);
		$weekDateRange = parseBankDateRange('week');
		$weekHistory = getBankHistoryBetween($pdo, $_SESSION['user_id'], $weekDateRange['start'], $weekDateRange['end']);
		$weekGoalTargets = computeGoalTargets(
			$pdo,
			$_SESSION['user_id'],
			$bankTypes,
			$weekDateRange['start'],
			$weekDateRange['end']
		);
		$today = date('Y-m-d');
		$todayGoalTargets = computeGoalTargets($pdo, $_SESSION['user_id'], $bankTypes, $today, $today);
		$currentStatMonth = date('Y-m-01');
		$monthlyStats = getMonthlyStats($pdo, $_SESSION['user_id'], $currentStatMonth) ?? defaultMonthlyStatsRow($currentStatMonth);
		$activeTypeSlugs = array_column($bankTypes, 'slug');
		$monthlyStats['type_stats'] = array_intersect_key(
			$monthlyStats['type_stats'] ?? [],
			array_flip($activeTypeSlugs)
		);
		$overallMissingGoals = computeOverallMissingGoals($pdo, $_SESSION['user_id']);
		$overallSurplusGoals = computeOverallSurplusGoals($pdo, $_SESSION['user_id']);

		$pageTitle = 'Bank';
		ob_start();
		include __DIR__ . '/../templates/bank.php';
		$content = ob_get_clean();
		break;
	case '/bank/history':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}
		$pdo = db();
		BankRequestCache::enable();
		$pageTitle = 'Month History';
		$bankTypes = getHistoryDisplayTypes($pdo, $_SESSION['user_id']);
		$statsHistory = getMonthlyStatsHistory($pdo, $_SESSION['user_id']);
		$typeBreakdowns = [];
		foreach ($bankTypes as $bankType) {
			if ($bankType['balance_mode'] === 'pot') {
				$typeBreakdowns[$bankType['slug']] = getTypeBreakdownByMonth($pdo, $_SESSION['user_id'], $bankType['slug']);
			}
		}
		$netWorthHistory = getNetWorthHistory($pdo, $_SESSION['user_id']);
		$accountId = (int) $_SESSION['user_id'];
		ob_start();
		include __DIR__ . '/../templates/bank-history.php';
		$content = ob_get_clean();
		break;
	case '/bank/configure':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}
		$pdo = db();
		$pageTitle = 'Configure Types';
		$bankTypes = getBankTypes($pdo, $_SESSION['user_id']);
		$allocationTotal = sumActiveIncomePercents($bankTypes);
		$allocationOver = hasActiveIncomePercents($bankTypes) && $allocationTotal > 1.0001;
		$guaranteedMonthlyIncome = getAccountGuaranteedIncome($pdo, $_SESSION['user_id']);
		ob_start();
		include __DIR__ . '/../templates/bank-configure.php';
		$content = ob_get_clean();
		break;
	case '/bank/configure/income':
		header('Content-Type: application/json');
		if (!isset($_SESSION['user_id'])) {
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Unauthorized']);
			exit;
		}
		requireCsrfToken(true);
		$incomeValue = trim($_POST['guaranteed_monthly_income'] ?? '');
		if (parseMoneyAmount($incomeValue) === null) {
			echo json_encode(['success' => false, 'error' => 'Enter 0 or a positive number up to 1,000,000,000 for guaranteed monthly income.']);
			exit;
		}
		setAccountGuaranteedIncome(db(), $_SESSION['user_id'], (float) $incomeValue);
		echo json_encode(['success' => true]);
		exit;
	case '/bank/configure/save':
		header('Content-Type: application/json');
		if (!isset($_SESSION['user_id'])) {
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Unauthorized']);
			exit;
		}
		requireCsrfToken(true);
		$pdo = db();
		$typeId = (int) ($_POST['typeId'] ?? 0);
		$payload = [
			'label' => $_POST['label'] ?? '',
			'slug' => $_POST['slug'] ?? '',
			'color_hex' => $_POST['color_hex'] ?? '',
			'income_percent' => $_POST['income_percent'] ?? null,
			'is_active' => filter_var($_POST['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
		];
		if ($typeId > 0) {
			$result = updateBankType($pdo, $_SESSION['user_id'], $typeId, $payload);
		} else {
			$payload['balance_mode'] = $_POST['balance_mode'] ?? 'pot';
			$result = createBankType($pdo, $_SESSION['user_id'], $payload);
		}
		if ($result['success'] ?? false) {
			refreshMonthlyStats($pdo, $_SESSION['user_id'], date('Y-m-01'));
		}
		echo json_encode($result);
		exit;
	case '/bank/configure/delete':
		header('Content-Type: application/json');
		if (!isset($_SESSION['user_id'])) {
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Unauthorized']);
			exit;
		}
		requireCsrfToken(true);
		$typeId = (int) ($_POST['typeId'] ?? 0);
		$result = deleteBankType(db(), $_SESSION['user_id'], $typeId);
		echo json_encode($result);
		exit;
	case '/bank/configure/toggle':
		header('Content-Type: application/json');
		if (!isset($_SESSION['user_id'])) {
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Unauthorized']);
			exit;
		}
		requireCsrfToken(true);
		$pdo = db();
		$typeId = (int) ($_POST['typeId'] ?? 0);
		$isActive = filter_var($_POST['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
		$result = toggleBankTypeActive($pdo, $_SESSION['user_id'], $typeId, $isActive);
		if ($result['success'] ?? false) {
			refreshMonthlyStats($pdo, $_SESSION['user_id'], date('Y-m-01'));
		}
		echo json_encode($result);
		exit;
	case '/bank/update':
		header('Content-Type: application/json');

		if (!isset($_SESSION['user_id'])) {
			http_response_code(401);
			echo json_encode(['success' => false]);
			exit;
		}

		requireCsrfToken(true);

		$entryId = (int) ($_POST['entryId'] ?? 0);
		$type = $_POST['type'] ?? '';
		$method = $_POST['method'] ?? '';
		$direction = $_POST['direction'] ?? 'in';
		$amount = parseMoneyAmount($_POST['amount'] ?? null) ?? 0.0;
		$note = trim($_POST['note'] ?? '');
		$pdo = db();
		$bankType = getBankTypeBySlug($pdo, $_SESSION['user_id'], $type);
		$normalizedAmount = $bankType !== null
			? normalizeBankEntryAmount($bankType, $direction, $amount)
			: null;
		$updated = $normalizedAmount !== null
			&& updateBankEntry($pdo, $entryId, $_SESSION['user_id'], $type, $method, $normalizedAmount, $note !== '' ? $note : null);

		echo json_encode(['success' => $updated]);
		exit;
	case '/bank/delete':
		header('Content-Type: application/json');

		if (!isset($_SESSION['user_id'])) {
			http_response_code(401);
			echo json_encode(['success' => false]);
			exit;
		}

		requireCsrfToken(true);

		$entryId = (int) ($_POST['entryId'] ?? 0);
		$deleted = deleteBankEntry(db(), $entryId, $_SESSION['user_id']);

		echo json_encode(['success' => $deleted]);
		exit;
	case '/bank/lhv/connect':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		$accountId = (int) $_SESSION['user_id'];
		$pdo = db();
		requireVerifiedEmail($pdo, $accountId);

		if (needsLegalReconsent($pdo, $accountId)) {
			$_SESSION['flash'] = 'Please accept the updated Terms and Privacy Policy before connecting your bank.';
			header('Location: ' . settingsRedirectUrl());
			exit;
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			requireCsrfToken();
			$acceptedBankAis = filter_var($_POST['accept_bank_ais'] ?? false, FILTER_VALIDATE_BOOLEAN);

			if (!$acceptedBankAis) {
				$_SESSION['flash'] = 'You must consent to bank account information access before continuing.';
				header('Location: /bank/lhv/connect');
				exit;
			}

			recordBankAisConsent($pdo, $accountId);
			$_SESSION['lhv_state'] = bin2hex(random_bytes(8));
			$auth = enableBankingStartAuth('LHV Pank', 'EE', enableBankingRedirectUrl(), $_SESSION['lhv_state']);
			header('Location: ' . $auth['body']['url']);
			exit;
		}

		$pageTitle = 'Connect LHV';
		ob_start();
		include __DIR__ . '/../templates/bank-lhv-connect.php';
		$content = ob_get_clean();
		break;
	case '/bank/lhv/callback':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		$code = $_GET['code'] ?? '';
		$state = $_GET['state'] ?? '';

		if ($code === '' || $state === '' || $state !== ($_SESSION['lhv_state'] ?? null)) {
			http_response_code(400);
			exit('Invalid or expired authorization attempt.');
		}

		unset($_SESSION['lhv_state']);
		$session = enableBankingCreateSession($code);
		saveBankConnection(db(), $_SESSION['user_id'], $session['body']);

		header('Location: /bank/lhv');
		exit;
	case '/bank/lhv':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		$pdo = db();

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			requireCsrfToken();
			$accountId = (int) $_SESSION['user_id'];
			requireVerifiedEmail($pdo, $accountId);

			if (needsLegalReconsent($pdo, $accountId)) {
				$_SESSION['flash'] = 'Please accept the updated Terms and Privacy Policy before syncing.';
				header('Location: ' . settingsRedirectUrl());
				exit;
			}

			if (($_POST['do'] ?? '') === 'set_main') {
				setMainBankConnection($pdo, $_SESSION['user_id'], $_POST['bank_account_uid'] ?? '');
				$_SESSION['flash'] = 'Main account updated.';
			} else {
				try {
					markBankConnectionSyncAttempt($pdo, $_SESSION['user_id'], $_POST['bank_account_uid'] ?? '');
					$imported = importLhvTransactions($pdo, $_SESSION['user_id'], $_POST['bank_account_uid'] ?? '');
					markBankConnectionSynced($pdo, $_SESSION['user_id'], $_POST['bank_account_uid'] ?? '');
					$_SESSION['flash'] = "Imported $imported new transaction(s).";
				} catch (Throwable $e) {
					logger()?->warning('sync', 'LHV manual sync failed', [
						'account_id' => (int) $_SESSION['user_id'],
						'bank_account_uid' => $_POST['bank_account_uid'] ?? '',
						'error' => $e->getMessage(),
					]);
					$_SESSION['flash'] = 'Sync failed: ' . $e->getMessage();
				}
			}

			header('Location: /bank/lhv');
			exit;
		}

		$pageTitle = 'Connect LHV';
		$connections = getBankConnections($pdo, $_SESSION['user_id']);
		$flash = $_SESSION['flash'] ?? null;
		unset($_SESSION['flash']);
		ob_start();
		include __DIR__ . '/../templates/bank-lhv.php';
		$content = ob_get_clean();
		break;
	case '/logout':
		logout();
		header('Location: /');
		exit;
	case '/about':
		$pageTitle = 'About';
		ob_start();
		include __DIR__ . '/../templates/about.php';
		$content = ob_get_clean();
		break;
	case '/privacy':
		$pageTitle = 'Privacy Policy';
		$legalTitle = 'Privacy Policy';
		$legalVersion = legalPrivacyVersion();
		$legalUpdated = legalPrivacyVersion();
		$legalBodyHtml = renderPrivacyPolicyHtml();
		ob_start();
		include __DIR__ . '/../templates/legal/page.php';
		$content = ob_get_clean();
		break;
	case '/terms':
		$pageTitle = 'Terms of Use';
		$legalTitle = 'Terms of Use';
		$legalVersion = legalTermsVersion();
		$legalUpdated = legalTermsVersion();
		$legalBodyHtml = renderTermsOfUseHtml();
		ob_start();
		include __DIR__ . '/../templates/legal/page.php';
		$content = ob_get_clean();
		break;
	case '/legal/accept-updated':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			header('Location: ' . settingsRedirectUrl());
			exit;
		}

		requireCsrfToken();
		acceptUpdatedLegalDocuments(db(), (int) $_SESSION['user_id']);
		$_SESSION['flash'] = 'Thank you. The updated legal documents have been accepted.';
		header('Location: ' . settingsRedirectUrl());
		exit;
	case '/settings':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		$pdo = db();
		$accountId = (int) $_SESSION['user_id'];
		$account = getAccountForSettings($pdo, $accountId);

		if ($account === null) {
			logout();
			header('Location: /login');
			exit;
		}

		$error = null;
		$success = null;

		if (isset($_GET['password_changed'])) {
			$success = 'Password updated successfully.';
		}

		if (isset($_SESSION['flash'])) {
			$success = $_SESSION['flash'];
			unset($_SESSION['flash']);
		}

		$bankConnections = getBankConnections($pdo, $accountId);
		$needsLegalReconsent = needsLegalReconsent($pdo, $accountId);
		$pageTitle = 'Settings';
		ob_start();
		include __DIR__ . '/../templates/settings.php';
		$content = ob_get_clean();
		break;
	case '/settings/disconnect-bank':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			header('Location: ' . settingsRedirectUrl());
			exit;
		}

		requireCsrfToken();
		$pdo = db();
		$accountId = (int) $_SESSION['user_id'];
		$removeImported = filter_var($_POST['remove_imported_entries'] ?? false, FILTER_VALIDATE_BOOLEAN);
		$result = disconnectBankConnection(
			$pdo,
			$accountId,
			$_POST['bank_account_uid'] ?? '',
			$removeImported,
		);

		if ($result === true) {
			$_SESSION['flash'] = $removeImported
				? 'Bank disconnected and imported transactions removed.'
				: 'Bank disconnected. Imported transactions were kept as manual entries.';
			header('Location: ' . settingsRedirectUrl());
			exit;
		}

		$account = getAccountForSettings($pdo, $accountId);
		$bankConnections = getBankConnections($pdo, $accountId);
		$needsLegalReconsent = needsLegalReconsent($pdo, $accountId);
		$error = $result;
		$success = null;
		$pageTitle = 'Settings';
		ob_start();
		include __DIR__ . '/../templates/settings.php';
		$content = ob_get_clean();
		break;
	case '/settings/change-password':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			header('Location: ' . settingsRedirectUrl());
			exit;
		}

		requireCsrfToken();
		$result = changeAccountPassword(
			db(),
			(int) $_SESSION['user_id'],
			$_POST['current_password'] ?? '',
			$_POST['new_password'] ?? '',
			$_POST['new_password_confirm'] ?? '',
		);

		if ($result === true) {
			header('Location: ' . settingsRedirectUrl(['password_changed' => 1]));
			exit;
		}

		$pdo = db();
		$accountId = (int) $_SESSION['user_id'];
		$account = getAccountForSettings($pdo, $accountId);
		$bankConnections = getBankConnections($pdo, $accountId);
		$needsLegalReconsent = needsLegalReconsent($pdo, $accountId);
		$error = $result;
		$success = null;
		$pageTitle = 'Settings';
		ob_start();
		include __DIR__ . '/../templates/settings.php';
		$content = ob_get_clean();
		break;
	case '/settings/export':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		$export = buildAccountExport(db(), (int) $_SESSION['user_id']);
		if ($export === null) {
			http_response_code(404);
			exit('Account not found.');
		}

		$filename = 'the-vault-export-' . date('Y-m-d') . '.json';
		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		exit;
	case '/settings/delete-account':
		if (!isset($_SESSION['user_id'])) {
			header('Location: /login');
			exit;
		}

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			header('Location: ' . settingsRedirectUrl());
			exit;
		}

		requireCsrfToken();
		$accountId = (int) $_SESSION['user_id'];
		$result = deleteAccount(
			db(),
			$accountId,
			$_POST['password'] ?? '',
			$_POST['username_confirm'] ?? '',
		);

		if ($result === true) {
			logout();
			header('Location: /?account_deleted=1');
			exit;
		}

		$pdo = db();
		$account = getAccountForSettings($pdo, $accountId);
		$bankConnections = getBankConnections($pdo, $accountId);
		$needsLegalReconsent = needsLegalReconsent($pdo, $accountId);
		$error = $result;
		$success = null;
		$pageTitle = 'Settings';
		ob_start();
		include __DIR__ . '/../templates/settings.php';
		$content = ob_get_clean();
		break;
	default:
		if (preg_match('#^/bank/([a-z0-9_]+)$#', $uri, $matches)) {
			$type = $matches[1];
			$reserved = ['history', 'configure', 'update', 'delete', 'lhv'];

			if (in_array($type, $reserved, true)) {
				return false;
			}

			if (!isset($_SESSION['user_id'])) {
				header('Location: /login');
				exit;
			}

			$pdo = db();

			if (!canAccessBankTypePage($pdo, $_SESSION['user_id'], $type)) {
				return false;
			}

			if ($_SERVER['REQUEST_METHOD'] === 'POST') {
				requireCsrfToken();
				$method = $_POST['method'] ?? '';
				$direction = $_POST['direction'] ?? 'in';
				$amount = parseMoneyAmount($_POST['amount'] ?? null) ?? 0.0;
				$note = trim($_POST['note'] ?? '');
				$entryDate = $_POST['entry_date'] ?? '';
				$currentTypeForPost = getBankTypeBySlug($pdo, $_SESSION['user_id'], $type);
				$normalizedAmount = $currentTypeForPost !== null
					? normalizeBankEntryAmount($currentTypeForPost, $direction, $amount)
					: null;

				if ($normalizedAmount !== null) {
					createBankEntry(
						$pdo,
						$_SESSION['user_id'],
						$type,
						$method,
						$normalizedAmount,
						$note !== '' ? $note : null,
						$entryDate !== '' ? $entryDate : null
					);
				}

				header('Location: /bank/' . $type);
				exit;
			}

			$bankTypes = getActiveBankTypes($pdo, $_SESSION['user_id']);
			$visibleBankTypes = getVisibleBankTypes($pdo, $_SESSION['user_id']);
			$currentType = getBankTypeBySlug($pdo, $_SESSION['user_id'], $type);
			if ($currentType === null || !$currentType['is_active']) {
				return false;
			}
			$pageTitle = 'Bank ' . bankEntryTypeLabel($pdo, $_SESSION['user_id'], $type);
			$dateRange = parseBankDateRange($_GET['range'] ?? 'all', $_GET['from'] ?? null, $_GET['to'] ?? null);
			$range = $dateRange['range'];
			$search = trim($_GET['q'] ?? '');
			$entries = getBankEntriesByTypeBetween(
				$pdo,
				$_SESSION['user_id'],
				$type,
				$dateRange['start'],
				$dateRange['end'],
				$search !== '' ? $search : null
			);
			$entriesTotal = array_sum(array_column($entries, 'amount'));
			$investmentsNoteBreakdown = $type === 'investments'
				? getInvestmentBreakdownByNote($pdo, $_SESSION['user_id'], $dateRange['start'], $dateRange['end'])
				: [];
			$investmentNotes = $type === 'investments'
				? getInvestmentNotes($pdo, $_SESSION['user_id'])
				: [];
			ob_start();
			include __DIR__ . '/../templates/bank-entries.php';
			$content = ob_get_clean();
			break;
		}

		return false;
}

include __DIR__ . '/../templates/layout.php';
