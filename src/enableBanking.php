<?php

require_once __DIR__ . '/AppConfig.php';

const ENABLE_BANKING_API_ORIGIN = 'https://api.enablebanking.com';

function enableBankingConfig(): array {
	static $config = null;

	if ($config === null) {
		AppConfig::load();
		$config = AppConfig::enableBankingConfig();
	}

	return $config;
}

function enableBankingBase64UrlEncode(string $data): string {
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function enableBankingJwt(): string {
	$config = enableBankingConfig();

	$header = [
		'typ' => 'JWT',
		'alg' => 'RS256',
		'kid' => $config['application_id'],
	];
	$now = time();
	$payload = [
		'iss' => 'enablebanking.com',
		'aud' => 'api.enablebanking.com',
		'iat' => $now,
		'exp' => $now + 3600,
	];

	$segments = enableBankingBase64UrlEncode(json_encode($header))
		. '.' . enableBankingBase64UrlEncode(json_encode($payload));

	$privateKey = openssl_pkey_get_private(file_get_contents($config['private_key_path']));
	openssl_sign($segments, $signature, $privateKey, OPENSSL_ALGO_SHA256);

	return $segments . '.' . enableBankingBase64UrlEncode($signature);
}

function enableBankingRequest(string $method, string $path, ?array $body = null): array {
	$ch = curl_init(ENABLE_BANKING_API_ORIGIN . $path);

	$headers = [
		'Authorization: Bearer ' . enableBankingJwt(),
		'Content-Type: application/json',
	];

	curl_setopt_array($ch, [
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_RETURNTRANSFER => true,
	]);

	if ($body !== null) {
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
	}

	$response = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	return [
		'status' => $status,
		'body' => json_decode($response, true),
	];
}

function enableBankingRedirectUrl(): string {
	return enableBankingConfig()['redirect_url'];
}

function enableBankingGetApplication(): array {
	return enableBankingRequest('GET', '/application');
}

function enableBankingListAspsps(string $country): array {
	return enableBankingRequest('GET', '/aspsps?country=' . urlencode($country));
}

function enableBankingStartAuth(string $aspspName, string $aspspCountry, string $redirectUrl, string $state): array {
	return enableBankingRequest('POST', '/auth', [
		'access' => [
			'valid_until' => (new DateTime('+90 days'))->format('Y-m-d\TH:i:s.v\Z'),
		],
		'aspsp' => [
			'name' => $aspspName,
			'country' => $aspspCountry,
		],
		'state' => $state,
		'redirect_url' => $redirectUrl,
		'psu_type' => 'personal',
	]);
}

function enableBankingCreateSession(string $code): array {
	return enableBankingRequest('POST', '/sessions', ['code' => $code]);
}

function enableBankingGetSession(string $sessionId): array {
	return enableBankingRequest('GET', '/sessions/' . urlencode($sessionId));
}

function enableBankingGetAccountTransactions(string $accountId, ?string $dateFrom = null): array {
	$path = '/accounts/' . urlencode($accountId) . '/transactions';

	if ($dateFrom !== null) {
		$path .= '?date_from=' . urlencode($dateFrom);
	}

	return enableBankingRequest('GET', $path);
}

function enableBankingGetAccountBalances(string $accountId): array {
	return enableBankingRequest('GET', '/accounts/' . urlencode($accountId) . '/balances');
}
