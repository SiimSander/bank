<?php
// One-off export script: dumps mikedarippah's manual (non-LHV, non-kogumiskonto)
// bank_entries into a CSV shaped for Base44's BankEntry entity import.
// Run: php exports/export_bank_entries.php

$pdo = new PDO(
	'mysql:host=127.0.0.1;port=3308;dbname=php_learn;charset=utf8mb4',
	'root',
	'root',
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$accountStmt = $pdo->prepare('SELECT id FROM accounts WHERE username = ?');
$accountStmt->execute(['mikedarippah']);
$account = $accountStmt->fetch();

if (!$account) {
	fwrite(STDERR, "No account found for username 'mikedarippah'\n");
	exit(1);
}

$accountId = $account['id'];

$entriesStmt = $pdo->prepare(
	"SELECT entry_date, type, method, amount, note
	FROM bank_entries
	WHERE account_id = ?
		AND type <> 'kogumiskonto'
		AND entry_reference IS NULL
	ORDER BY entry_date, id"
);
$entriesStmt->execute([$accountId]);
$entries = $entriesStmt->fetchAll();

$outputPath = __DIR__ . '/mikedarippah_bank_entries.csv';
$file = fopen($outputPath, 'w');

fputcsv($file, ['entry_date', 'type', 'method', 'amount', 'note'], escape: '\\');

foreach ($entries as $entry) {
	fputcsv($file, [
		$entry['entry_date'],
		$entry['type'],
		$entry['method'],
		$entry['amount'],
		$entry['note'],
	], escape: '\\');
}

fclose($file);

echo 'Exported ' . count($entries) . " entries to $outputPath\n";
