#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/bank.php';

$accountId = (int) ($argv[1] ?? 0);
$apply = in_array('--apply', $argv, true);

if ($accountId < 1) {
	fwrite(STDERR, "Usage: php bin/update-account-stock-notes.php <accountId> [--apply]\n");

	exit(1);
}

try {
	$pdo = db();
} catch (Throwable $e) {
	fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);

	exit(1);
}

$notes = [];

$entryNotes = $pdo->prepare("SELECT DISTINCT note FROM bank_entries WHERE account_id = ? AND type = 'investments' AND note IS NOT NULL AND note <> ''");
$entryNotes->execute([$accountId]);

foreach ($entryNotes->fetchAll(PDO::FETCH_COLUMN) as $note) {
	$notes[mb_strtolower(trim($note))] = trim($note);
}

$goalNotes = $pdo->prepare('SELECT DISTINCT stock_note FROM stock_goals WHERE account_id = ?');
$goalNotes->execute([$accountId]);

foreach ($goalNotes->fetchAll(PDO::FETCH_COLUMN) as $note) {
	$notes[mb_strtolower(trim($note))] ??= trim($note);
}

foreach ($notes as $oldKey => $note) {
	$name = splitInvestmentNote($note)['name'];
	$ticker = lookupStockTicker($name);

	if ($ticker === null) {
		echo "NO LIST ENTRY: {$note}\n";

		continue;
	}

	$target = $name . ' (' . STOCK_TICKER_CURRENCY . $ticker . ')';

	if (mb_strtolower($target) === $oldKey) {
		echo "already ok: {$note}\n";

		continue;
	}

	echo ($apply ? 'UPDATE ' : 'would update ') . "\"{$note}\" -> \"{$target}\"\n";

	if ($apply) {
		moveStockToNote($pdo, $accountId, (string) $oldKey, $target);
	}
}

echo $apply ? "Done.\n" : "Preview only. Add --apply to write the changes.\n";
