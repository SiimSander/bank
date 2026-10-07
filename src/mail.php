<?php

require_once __DIR__ . '/AppConfig.php';

function mailConfig(): array {
	static $config = null;

	if ($config === null) {
		AppConfig::load();
		$config = AppConfig::mailConfig();
	}

	return $config;
}

function buildAbsoluteUrl(string $path): string {
	$config = mailConfig();
	$baseUrl = rtrim($config['base_url'], '/');

	if ($path === '' || $path[0] !== '/') {
		$path = '/' . $path;
	}

	return $baseUrl . $path;
}

function sendMail(string $to, string $subject, string $textBody): bool {
	$config = mailConfig();
	$fromEmail = $config['from_email'];
	$fromName = $config['from_name'];
	$fromHeader = $fromName !== '' ? "{$fromName} <{$fromEmail}>" : $fromEmail;

	if (!empty($config['host'])) {
		return sendMailSmtp(
			$config['host'],
			(int) ($config['port'] ?? 587),
			$config['username'] ?? '',
			$config['password'] ?? '',
			$fromEmail,
			$to,
			$subject,
			$textBody
		);
	}

	$headers = [
		'From: ' . $fromHeader,
		'Content-Type: text/plain; charset=UTF-8',
	];

	return mail($to, $subject, $textBody, implode("\r\n", $headers));
}

function sendMailSmtp(
	string $host,
	int $port,
	string $username,
	string $password,
	string $fromEmail,
	string $toEmail,
	string $subject,
	string $body
): bool {
	$socket = @fsockopen($host, $port, $errno, $errstr, 10);
	if ($socket === false) {
		logger()?->warning('mail', 'SMTP connect failed', [
			'host' => $host,
			'port' => $port,
			'error' => $errstr,
			'errno' => $errno,
		]);

		return false;
	}

	stream_set_timeout($socket, 10);

	$read = static function () use ($socket): string {
		$response = '';
		while (($line = fgets($socket, 515)) !== false) {
			$response .= $line;
			if (isset($line[3]) && $line[3] === ' ') {
				break;
			}
		}

		return $response;
	};

	$write = static function (string $command) use ($socket): void {
		fwrite($socket, $command . "\r\n");
	};

	$expect = static function (string $response, array $codes) use ($read): bool {
		$code = (int) substr($response, 0, 3);

		return in_array($code, $codes, true);
	};

	$greeting = $read();
	if (!$expect($greeting, [220])) {
		fclose($socket);
		logger()?->warning('mail', 'SMTP greeting failed', ['response' => trim($greeting)]);

		return false;
	}

	$write('EHLO localhost');
	$ehlo = $read();
	if (!$expect($ehlo, [250])) {
		fclose($socket);
		logger()?->warning('mail', 'SMTP EHLO failed', ['response' => trim($ehlo)]);

		return false;
	}

	if ($username !== '' && $password !== '') {
		$write('AUTH LOGIN');
		if (!$expect($read(), [334])) {
			fclose($socket);

			return false;
		}
		$write(base64_encode($username));
		if (!$expect($read(), [334])) {
			fclose($socket);

			return false;
		}
		$write(base64_encode($password));
		if (!$expect($read(), [235])) {
			fclose($socket);
			logger()?->warning('mail', 'SMTP authentication failed', ['host' => $host]);

			return false;
		}
	}

	$write('MAIL FROM:<' . $fromEmail . '>');
	if (!$expect($read(), [250])) {
		fclose($socket);

		return false;
	}

	$write('RCPT TO:<' . $toEmail . '>');
	if (!$expect($read(), [250, 251])) {
		fclose($socket);

		return false;
	}

	$write('DATA');
	if (!$expect($read(), [354])) {
		fclose($socket);

		return false;
	}

	$message = 'Subject: ' . $subject . "\r\n"
		. 'To: <' . $toEmail . ">\r\n"
		. 'From: <' . $fromEmail . ">\r\n"
		. "MIME-Version: 1.0\r\n"
		. "Content-Type: text/plain; charset=UTF-8\r\n"
		. "\r\n"
		. str_replace("\n.", "\n..", $body) . "\r\n.\r\n";

	fwrite($socket, $message);
	if (!$expect($read(), [250])) {
		fclose($socket);
		logger()?->warning('mail', 'SMTP DATA failed', ['to' => $toEmail]);

		return false;
	}

	$write('QUIT');
	fclose($socket);

	return true;
}
