<?php

require_once __DIR__ . '/AppConfig.php';

function initSecureSession(): void {
	if (session_status() === PHP_SESSION_ACTIVE) {
		return;
	}

	AppConfig::load();
	$isSecure = AppConfig::isRequestSecure();

	session_set_cookie_params([
		'lifetime' => 0,
		'path' => '/',
		'secure' => $isSecure,
		'httponly' => true,
		'samesite' => 'Lax',
	]);

	ini_set('session.use_strict_mode', '1');
	ini_set('session.use_only_cookies', '1');
	ini_set('session.cookie_httponly', '1');
	ini_set('session.gc_maxlifetime', '7200');

	session_start();
}

function regenerateSessionOnLogin(): void {
	session_regenerate_id(true);
}

function logout(): void {
	$_SESSION = [];

	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(
			session_name(),
			'',
			time() - 42000,
			$params['path'],
			$params['domain'],
			$params['secure'],
			$params['httponly']
		);
	}

	session_destroy();
}
