<?php

function csrfToken(): string {
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}

	return $_SESSION['csrf_token'];
}

function csrfField(): string {
	$token = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');

	return '<input type="hidden" name="_csrf" value="' . $token . '">';
}

function submittedCsrfToken(): string {
	$header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

	if ($header !== '') {
		return $header;
	}

	return $_POST['_csrf'] ?? '';
}

function requireCsrfToken(bool $jsonResponse = false): void {
	$submitted = submittedCsrfToken();
	$expected = $_SESSION['csrf_token'] ?? '';

	if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
		if ($jsonResponse) {
			http_response_code(403);
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
			exit;
		}

		http_response_code(403);
		exit('Invalid or missing CSRF token.');
	}
}
