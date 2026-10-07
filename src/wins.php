<?php

function createTodayWinCard(PDO $pdo, int $userId): bool|string {
	$rawSql = $pdo->prepare(
		'INSERT INTO win_cards (account_id, card_date) VALUES (?, CURDATE())'
	);

	try {
		$rawSql->execute([$userId]);
	} catch (PDOException $e) {
		return 'Today\'s win card is already created';
	}

	return true;
}

function getTodayWinCard(PDO $pdo, int $userId): array|false {
	$rawSql= $pdo->prepare(
		'SELECT id, card_date FROM win_cards WHERE account_id = ? AND card_date = CURDATE()'
	);
	$rawSql->execute([$userId]);
	$winCard = $rawSql->fetch();

	if (!$winCard) {
		return false;
	}

	return $winCard;
}

function createWinCardItem(PDO $pdo, int $cardId, string $title, int $position): void {
	$rawSql = $pdo->prepare(
			'INSERT INTO win_card_items (card_id, title, position) VALUES (?, ?, ?)'
	);
	$rawSql->execute([$cardId, $title, $position]);
}

function getWinCardItems(PDO $pdo, int $cardId): array {
	$rawSql = $pdo->prepare(
		'SELECT id, title, status FROM win_card_items WHERE card_id = ? ORDER BY position'
	);
	$rawSql->execute([$cardId]);

	return $rawSql->fetchAll();
}

function updateWinCardItemStatus(PDO $pdo, int $itemId, int $userId, string $status): bool {
	if (!in_array($status, ['pending', 'done', 'failed'], true)) {
		return false;
	}

	$rawSql = $pdo->prepare(
		'UPDATE win_card_items wci
		JOIN win_cards wc ON wc.id = wci.card_id
		SET wci.status = ?, wci.updated_at = NOW()
		WHERE wci.id = ? AND wc.account_id = ?'
	);
	$rawSql->execute([$status, $itemId, $userId]);

	return $rawSql->rowCount() > 0;
}

function updateWinCardItemTitle(PDO $pdo, int $itemId, int $userId, string $title): bool {
	if (trim($title) === '') {
		return false;
	}

	$rawSql = $pdo->prepare(
		'UPDATE win_card_items wci
		JOIN win_cards wc ON wc.id = wci.card_id
		SET wci.title = ?, wci.updated_at = NOW()
		WHERE wci.id = ? AND wc.account_id = ?'
	);
	$rawSql->execute([$title, $itemId, $userId]);

	return $rawSql->rowCount() > 0;
}

function deleteWinCardItem(PDO $pdo, int $itemId, int $userId): bool {
	$rawSql = $pdo->prepare(
		'DELETE wci FROM win_card_items wci
		INNER JOIN win_cards wc ON wc.id = wci.card_id
		WHERE wci.id = ? AND wc.account_id = ?'
	);
	$rawSql->execute([$itemId, $userId]);

	return $rawSql->rowCount() > 0;
}

function deleteWinCard(PDO $pdo, int $cardId, int $userId): bool {
	$verifySql = $pdo->prepare(
		'SELECT id FROM win_cards WHERE id = ? AND account_id = ?'
	);
	$verifySql->execute([$cardId, $userId]);

	if ($verifySql->fetch() === false) {
		return false;
	}

	$pdo->beginTransaction();

	try {
		$deleteItemsSql = $pdo->prepare('DELETE FROM win_card_items WHERE card_id = ?');
		$deleteItemsSql->execute([$cardId]);

		$deleteCardSql = $pdo->prepare('DELETE FROM win_cards WHERE id = ? AND account_id = ?');
		$deleteCardSql->execute([$cardId, $userId]);

		$pdo->commit();
	} catch (Throwable) {
		$pdo->rollBack();

		return false;
	}

	return true;
}

function reorderWinCardItems(PDO $pdo, int $userId, array $itemIds): bool {
	$winCard = getTodayWinCard($pdo, $userId);

	if ($winCard === false) {
		return false;
	}

	$cardId = (int) $winCard['id'];
	$itemIds = array_values(array_unique(array_map('intval', $itemIds)));

	if ($itemIds === []) {
		return false;
	}

	$existingItems = getWinCardItems($pdo, $cardId);
	$existingIds = array_map(static fn(array $item): int => (int) $item['id'], $existingItems);

	sort($existingIds);
	$sortedRequestIds = $itemIds;
	sort($sortedRequestIds);

	if ($existingIds !== $sortedRequestIds) {
		return false;
	}

	$pdo->beginTransaction();

	try {
		$updateSql = $pdo->prepare(
			'UPDATE win_card_items SET position = ? WHERE id = ? AND card_id = ?'
		);

		foreach ($itemIds as $position => $itemId) {
			$updateSql->execute([$position, $itemId, $cardId]);
		}

		$pdo->commit();
	} catch (Throwable) {
		$pdo->rollBack();

		return false;
	}

	return true;
}

function getWinCardHistory(PDO $pdo, int $userId): array {
	$rawSql = $pdo->prepare(
		'SELECT id, card_date FROM win_cards WHERE account_id = ? AND card_date < CURDATE() ORDER BY card_date DESC'
	);
	$rawSql->execute([$userId]);
	$cards = $rawSql->fetchAll();

	foreach ($cards as &$card) {
		$card['items'] = getWinCardItems($pdo, $card['id']);
	}
	unset($card);

	return $cards;
}