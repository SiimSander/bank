<?php

const ACCOUNT_PASSWORD_MIN_LENGTH = 8;
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 900;
const PASSWORD_RESET_EXPIRY_SECONDS = 3600;
const EMAIL_VERIFICATION_EXPIRY_SECONDS = 86400;
const PASSWORD_RESET_RATE_LIMIT = 3;
const PASSWORD_RESET_RATE_WINDOW_SECONDS = 3600;

function hashAccountPassword(string $plain): string {
	return password_hash($plain, PASSWORD_DEFAULT);
}

function isPasswordHashed(string $stored): bool {
	return password_get_info($stored)['algo'] !== null;
}

function verifyAccountPassword(string $stored, string $plain): bool {
	if (isPasswordHashed($stored)) {
		return password_verify($plain, $stored);
	}

	return hash_equals($stored, $plain);
}

function validateAccountPassword(string $password): ?string {
	if (strlen($password) < ACCOUNT_PASSWORD_MIN_LENGTH) {
		return 'Password must be at least ' . ACCOUNT_PASSWORD_MIN_LENGTH . ' characters.';
	}

	return null;
}

function upgradeLegacyPassword(PDO $pdo, int $accountId, string $plain): void {
	$statement = $pdo->prepare('UPDATE accounts SET password = ? WHERE id = ?');
	$statement->execute([hashAccountPassword($plain), $accountId]);
}

function loginAttemptKey(string $username): string {
	return 'login_attempts_' . strtolower(trim($username));
}

function isLoginRateLimited(string $username): bool {
	$key = loginAttemptKey($username);
	$attempts = $_SESSION[$key] ?? null;

	if ($attempts === null) {
		return false;
	}

	if ($attempts['count'] >= LOGIN_MAX_ATTEMPTS && (time() - $attempts['first_at']) < LOGIN_LOCKOUT_SECONDS) {
		return true;
	}

	if ((time() - $attempts['first_at']) >= LOGIN_LOCKOUT_SECONDS) {
		unset($_SESSION[$key]);
	}

	return false;
}

function recordFailedLogin(string $username): void {
	$key = loginAttemptKey($username);
	$attempts = $_SESSION[$key] ?? null;

	if ($attempts === null || (time() - $attempts['first_at']) >= LOGIN_LOCKOUT_SECONDS) {
		$_SESSION[$key] = ['count' => 1, 'first_at' => time()];

		return;
	}

	$_SESSION[$key]['count']++;
}

function clearLoginAttempts(string $username): void {
	unset($_SESSION[loginAttemptKey($username)]);
}

function signup(PDO $pdo, string $name, string $username, string $email, string $password): bool|string {
	if (trim($name) === '') {
		return 'Name is required';
	}
	if (trim($username) === '') {
		return 'Username is required';
	}
	if (trim($email) === '') {
		return 'Email is required';
	}
	if ($password === '') {
		return 'Password is required';
	}

	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		return 'Invalid email address';
	}

	$passwordError = validateAccountPassword($password);
	if ($passwordError !== null) {
		return $passwordError;
	}

	$statement = $pdo->prepare(
		'INSERT INTO accounts (name, username, email, password) VALUES (?, ?, ?, ?)'
	);

	try {
		$statement->execute([$name, $username, $email, hashAccountPassword($password)]);
	} catch (PDOException $e) {
		return 'Username or email already exists';
	}

	$accountId = (int) $pdo->lastInsertId();
	sendEmailVerificationForAccount($pdo, $accountId);

	return true;
}

function login(PDO $pdo, string $username, string $password): array|false|string {
	if (trim($username) === '') {
		return false;
	}
	if (trim($password) === '') {
		return false;
	}

	if (isLoginRateLimited($username)) {
		logger()?->warning('auth', 'Login blocked by rate limit', [
			'username' => strtolower(trim($username)),
		]);

		return 'Too many failed attempts. Try again in a few minutes.';
	}

	$statement = $pdo->prepare(
		'SELECT id, name, username, email, password FROM accounts WHERE username = ?'
	);
	$statement->execute([$username]);
	$user = $statement->fetch();

	if (!$user || !verifyAccountPassword($user['password'], $password)) {
		recordFailedLogin($username);
		logger()?->warning('auth', 'Failed login attempt', [
			'username' => strtolower(trim($username)),
		]);
		usleep(300000);

		return false;
	}

	if (!isPasswordHashed($user['password'])) {
		upgradeLegacyPassword($pdo, (int) $user['id'], $password);
	}

	clearLoginAttempts($username);
	unset($user['password']);

	return $user;
}

function getAccountByUsername(PDO $pdo, string $username): ?array {
	$statement = $pdo->prepare(
		'SELECT id, name, username, email, email_verified_at FROM accounts WHERE username = ?'
	);
	$statement->execute([trim($username)]);
	$row = $statement->fetch();

	return $row !== false ? $row : null;
}

function getAccountByEmail(PDO $pdo, string $email): ?array {
	$statement = $pdo->prepare(
		'SELECT id, name, username, email, email_verified_at FROM accounts WHERE email = ?'
	);
	$statement->execute([trim($email)]);
	$row = $statement->fetch();

	return $row !== false ? $row : null;
}

function hashSecurityToken(string $rawToken): string {
	return hash('sha256', $rawToken);
}

function generateSecurityToken(): string {
	return bin2hex(random_bytes(32));
}

function passwordResetRateLimitKey(string $email): string {
	return 'password_reset_' . strtolower(trim($email));
}

function isPasswordResetRateLimited(string $email): bool {
	$key = passwordResetRateLimitKey($email);
	$requests = $_SESSION[$key] ?? [];

	$cutoff = time() - PASSWORD_RESET_RATE_WINDOW_SECONDS;
	$requests = array_values(array_filter($requests, fn(int $at) => $at >= $cutoff));
	$_SESSION[$key] = $requests;

	return count($requests) >= PASSWORD_RESET_RATE_LIMIT;
}

function recordPasswordResetRequest(string $email): void {
	$key = passwordResetRateLimitKey($email);
	$requests = $_SESSION[$key] ?? [];
	$requests[] = time();
	$_SESSION[$key] = $requests;
}

function requestPasswordReset(PDO $pdo, string $email): void {
	$email = trim($email);

	if ($email === '' || isPasswordResetRateLimited($email)) {
		return;
	}

	recordPasswordResetRequest($email);

	$account = getAccountByEmail($pdo, $email);
	if ($account === null) {
		return;
	}

	$rawToken = generateSecurityToken();
	$expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY_SECONDS);

	$insert = $pdo->prepare(
		'INSERT INTO password_reset_tokens (account_id, token_hash, expires_at) VALUES (?, ?, ?)'
	);
	$insert->execute([(int) $account['id'], hashSecurityToken($rawToken), $expiresAt]);

	$resetUrl = buildAbsoluteUrl('/reset-password?token=' . urlencode($rawToken));
	$body = "Hi {$account['name']},\n\n"
		. "Reset your password using this link (valid for 1 hour):\n"
		. $resetUrl . "\n\n"
		. "If you did not request this, you can ignore this email.\n";

	sendMail($account['email'], 'Reset your password', $body);
}

function isPasswordResetTokenValid(PDO $pdo, string $rawToken): bool {
	$statement = $pdo->prepare(
		'SELECT expires_at, used_at FROM password_reset_tokens WHERE token_hash = ?'
	);
	$statement->execute([hashSecurityToken($rawToken)]);
	$row = $statement->fetch();

	return $row !== false
		&& $row['used_at'] === null
		&& $row['expires_at'] >= date('Y-m-d H:i:s');
}

function resetPassword(PDO $pdo, string $rawToken, string $newPassword): bool|string {
	$passwordError = validateAccountPassword($newPassword);
	if ($passwordError !== null) {
		return $passwordError;
	}

	$statement = $pdo->prepare(
		'SELECT id, account_id, expires_at, used_at
		FROM password_reset_tokens
		WHERE token_hash = ?'
	);
	$statement->execute([hashSecurityToken($rawToken)]);
	$row = $statement->fetch();

	if ($row === false || $row['used_at'] !== null || $row['expires_at'] < date('Y-m-d H:i:s')) {
		return 'This reset link is invalid or has expired.';
	}

	$accountId = (int) $row['account_id'];
	$updatePassword = $pdo->prepare('UPDATE accounts SET password = ? WHERE id = ?');
	$updatePassword->execute([hashAccountPassword($newPassword), $accountId]);

	$markUsed = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?');
	$markUsed->execute([(int) $row['id']]);

	return true;
}

function isEmailVerified(PDO $pdo, int $accountId): bool {
	$statement = $pdo->prepare('SELECT email_verified_at FROM accounts WHERE id = ?');
	$statement->execute([$accountId]);
	$value = $statement->fetchColumn();

	return $value !== false && $value !== null;
}

function sendEmailVerificationForAccount(PDO $pdo, int $accountId): void {
	$statement = $pdo->prepare('SELECT name, email, email_verified_at FROM accounts WHERE id = ?');
	$statement->execute([$accountId]);
	$account = $statement->fetch();

	if ($account === false || $account['email_verified_at'] !== null) {
		return;
	}

	$rawToken = generateSecurityToken();
	$expiresAt = date('Y-m-d H:i:s', time() + EMAIL_VERIFICATION_EXPIRY_SECONDS);

	$insert = $pdo->prepare(
		'INSERT INTO email_verification_tokens (account_id, token_hash, expires_at) VALUES (?, ?, ?)'
	);
	$insert->execute([$accountId, hashSecurityToken($rawToken), $expiresAt]);

	$verifyUrl = buildAbsoluteUrl('/verify-email?token=' . urlencode($rawToken));
	$body = "Hi {$account['name']},\n\n"
		. "Verify your email address:\n"
		. $verifyUrl . "\n\n"
		. "This link expires in 24 hours.\n";

	sendMail($account['email'], 'Verify your email', $body);
}

function verifyEmail(PDO $pdo, string $rawToken): bool {
	$statement = $pdo->prepare(
		'SELECT id, account_id, expires_at, used_at
		FROM email_verification_tokens
		WHERE token_hash = ?'
	);
	$statement->execute([hashSecurityToken($rawToken)]);
	$row = $statement->fetch();

	if ($row === false || $row['used_at'] !== null || $row['expires_at'] < date('Y-m-d H:i:s')) {
		return false;
	}

	$accountId = (int) $row['account_id'];
	$verifyAccount = $pdo->prepare('UPDATE accounts SET email_verified_at = NOW() WHERE id = ?');
	$verifyAccount->execute([$accountId]);

	$markUsed = $pdo->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE id = ?');
	$markUsed->execute([(int) $row['id']]);

	return true;
}

function requireVerifiedEmail(PDO $pdo, int $accountId): void {
	if (isEmailVerified($pdo, $accountId)) {
		return;
	}

	$_SESSION['flash'] = 'Verify your email before connecting or syncing your bank.';
	header('Location: /bank/lhv');
	exit;
}

function getAccountGuaranteedIncome(PDO $pdo, int $accountId): ?float {
	$statement = $pdo->prepare('SELECT guaranteed_monthly_income FROM accounts WHERE id = ?');
	$statement->execute([$accountId]);
	$value = $statement->fetchColumn();

	return $value !== null ? (float) $value : null;
}

function setAccountGuaranteedIncome(PDO $pdo, int $accountId, float $income): void {
	$statement = $pdo->prepare('UPDATE accounts SET guaranteed_monthly_income = ? WHERE id = ?');
	$statement->execute([$income, $accountId]);
}

function accountNeedsOnboarding(PDO $pdo, int $accountId): bool {
	if (getAccountGuaranteedIncome($pdo, $accountId) !== null) {
		return false;
	}

	$statement = $pdo->prepare('SELECT COUNT(*) FROM bank_entry_types WHERE account_id = ?');
	$statement->execute([$accountId]);

	return (int) $statement->fetchColumn() === 0;
}

function getOpeningBalancesSetAt(PDO $pdo, int $accountId): ?string {
	$statement = $pdo->prepare('SELECT opening_balances_set_at FROM accounts WHERE id = ?');
	$statement->execute([$accountId]);
	$value = $statement->fetchColumn();

	return $value !== false && $value !== null ? (string) $value : null;
}

function markOpeningBalancesSet(PDO $pdo, int $accountId): void {
	$statement = $pdo->prepare('UPDATE accounts SET opening_balances_set_at = NOW() WHERE id = ?');
	$statement->execute([$accountId]);
}

function accountNeedsOpeningBalances(PDO $pdo, int $accountId): bool {
	if (getOpeningBalancesSetAt($pdo, $accountId) !== null) {
		return false;
	}

	if (getAccountGuaranteedIncome($pdo, $accountId) === null) {
		return false;
	}

	$statement = $pdo->prepare('SELECT COUNT(*) FROM bank_entries WHERE account_id = ?');
	$statement->execute([$accountId]);

	return (int) $statement->fetchColumn() === 0;
}
