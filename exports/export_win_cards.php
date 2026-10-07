<?php
// One-off export script: dumps mikedarippah's win_cards + win_card_items
// into files shaped for importing into Base44's WinCard/WinCardItem entities.
//
// WinCardItem needs a real card_id that only exists once the matching WinCard
// is created inside Base44 (auto-generated id), so a flat CSV can't preserve
// that relationship on its own. This script produces:
//   1. mikedarippah_win_cards.json  - nested [{ card_date, items: [...] }, ...]
//      meant for Base44's AI chat to replay (create card, then create its
//      items with the returned card id).
//   2. mikedarippah_win_cards.csv        - flat card list (card_date only)
//   3. mikedarippah_win_card_items.csv   - flat item list, with card_date as
//      a join hint column (not a real WinCardItem field) so a card can be
//      matched up manually if you're not using the JSON import route.
//
// Run: php exports/export_win_cards.php

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

$cardsStmt = $pdo->prepare(
	'SELECT id, card_date FROM win_cards WHERE account_id = ? ORDER BY card_date'
);
$cardsStmt->execute([$accountId]);
$cards = $cardsStmt->fetchAll();

$itemsStmt = $pdo->prepare(
	'SELECT title, status, position FROM win_card_items WHERE card_id = ? ORDER BY position'
);

$nested = [];
$flatItemsRows = [];

foreach ($cards as $card) {
	$itemsStmt->execute([$card['id']]);
	$items = $itemsStmt->fetchAll();

	$nested[] = [
		'card_date' => $card['card_date'],
		'items' => array_map(
			fn($item) => [
				'title' => $item['title'],
				'status' => $item['status'],
				'position' => (int) $item['position'],
			],
			$items
		),
	];

	foreach ($items as $item) {
		$flatItemsRows[] = [$card['card_date'], $item['title'], $item['status'], (int) $item['position']];
	}
}

// 1. Nested JSON (primary deliverable)
file_put_contents(
	__DIR__ . '/mikedarippah_win_cards.json',
	json_encode($nested, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

// 2. Flat cards CSV
$cardsFile = fopen(__DIR__ . '/mikedarippah_win_cards.csv', 'w');
fputcsv($cardsFile, ['card_date'], escape: '\\');
foreach ($cards as $card) {
	fputcsv($cardsFile, [$card['card_date']], escape: '\\');
}
fclose($cardsFile);

// 3. Flat items CSV (card_date is a join hint, not a real WinCardItem field)
$itemsFile = fopen(__DIR__ . '/mikedarippah_win_card_items.csv', 'w');
fputcsv($itemsFile, ['card_date', 'title', 'status', 'position'], escape: '\\');
foreach ($flatItemsRows as $row) {
	fputcsv($itemsFile, $row, escape: '\\');
}
fclose($itemsFile);

echo 'Exported ' . count($cards) . ' cards and ' . count($flatItemsRows) . " items.\n";
echo "JSON:        exports/mikedarippah_win_cards.json\n";
echo "Cards CSV:   exports/mikedarippah_win_cards.csv\n";
echo "Items CSV:   exports/mikedarippah_win_card_items.csv\n";
