<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$testOverrides = [
	'APP_ENV' => getenv('APP_ENV') ?: 'testing',
	'DB_HOST' => getenv('DB_HOST') ?: '127.0.0.1',
	'DB_PORT' => getenv('DB_PORT') ?: '3308',
	'DB_NAME' => getenv('DB_NAME') ?: 'php_learn_test',
	'DB_USER' => getenv('DB_USER') ?: 'root',
	'DB_PASSWORD' => getenv('DB_PASSWORD') ?: 'root',
	'APP_URL' => getenv('APP_URL') ?: 'http://localhost:8000',
	'MAIL_FROM_EMAIL' => getenv('MAIL_FROM_EMAIL') ?: 'test@localhost.dev',
];

putenv('TEST_TODAY=' . (getenv('TEST_TODAY') ?: '2026-09-15'));

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/database.php';
require_once dirname(__DIR__) . '/src/Clock.php';
require_once dirname(__DIR__) . '/src/bankTypes.php';
require_once dirname(__DIR__) . '/src/typeIncomePercentHistory.php';
require_once dirname(__DIR__) . '/src/goalCalculations.php';
require_once dirname(__DIR__) . '/src/bank.php';
require_once dirname(__DIR__) . '/src/stockGoals.php';
require_once dirname(__DIR__) . '/src/lhvSync.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/account.php';
require_once dirname(__DIR__) . '/src/legal.php';
require_once dirname(__DIR__) . '/src/legalContent.php';
require_once dirname(__DIR__) . '/src/compoundInterest.php';
require_once dirname(__DIR__) . '/src/wins.php';

AppConfig::overrideForTesting($testOverrides);
dbReset();

require_once dirname(__DIR__) . '/src/migrations.php';
runMigrationsUp(db(), false);
