<?php

$path = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path !== __DIR__ . '/' && is_file($path)) {
	return false;
}

require __DIR__ . '/index.php';
