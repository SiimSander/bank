<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class WinsItemsTest extends DatabaseTestCase {
	public function testDeleteWinCardItemRemovesSingleRow(): void {
		$accountId = $this->createTestAccount();
		createTodayWinCard($this->pdo, $accountId);
		$winCard = getTodayWinCard($this->pdo, $accountId);
		createWinCardItem($this->pdo, (int) $winCard['id'], 'First win', 0);
		createWinCardItem($this->pdo, (int) $winCard['id'], 'Second win', 1);

		$items = getWinCardItems($this->pdo, (int) $winCard['id']);
		$firstItemId = (int) $items[0]['id'];

		$deleted = deleteWinCardItem($this->pdo, $firstItemId, $accountId);

		self::assertTrue($deleted);
		self::assertSame(1, $this->countWinCardItems((int) $winCard['id']));
		self::assertSame('Second win', getWinCardItems($this->pdo, (int) $winCard['id'])[0]['title']);
	}

	public function testDeleteWinCardRemovesItemsAndCard(): void {
		$accountId = $this->createTestAccount();
		createTodayWinCard($this->pdo, $accountId);
		$winCard = getTodayWinCard($this->pdo, $accountId);
		createWinCardItem($this->pdo, (int) $winCard['id'], 'Win A', 0);
		createWinCardItem($this->pdo, (int) $winCard['id'], 'Win B', 1);

		$deleted = deleteWinCard($this->pdo, (int) $winCard['id'], $accountId);

		self::assertTrue($deleted);
		self::assertFalse(getTodayWinCard($this->pdo, $accountId));
		self::assertSame(0, $this->countWinCardItems((int) $winCard['id']));
	}

	public function testReorderWinCardItemsUpdatesPositionsForTodayCard(): void {
		$accountId = $this->createTestAccount();
		createTodayWinCard($this->pdo, $accountId);
		$winCard = getTodayWinCard($this->pdo, $accountId);
		createWinCardItem($this->pdo, (int) $winCard['id'], 'Alpha', 0);
		createWinCardItem($this->pdo, (int) $winCard['id'], 'Beta', 1);
		createWinCardItem($this->pdo, (int) $winCard['id'], 'Gamma', 2);

		$items = getWinCardItems($this->pdo, (int) $winCard['id']);
		$reorderedIds = [
			(int) $items[2]['id'],
			(int) $items[0]['id'],
			(int) $items[1]['id'],
		];

		$success = reorderWinCardItems($this->pdo, $accountId, $reorderedIds);

		self::assertTrue($success);
		self::assertSame(
			['Gamma', 'Alpha', 'Beta'],
			array_column(getWinCardItems($this->pdo, (int) $winCard['id']), 'title'),
		);
	}

	public function testReorderWinCardItemsRejectsPartialItemList(): void {
		$accountId = $this->createTestAccount();
		createTodayWinCard($this->pdo, $accountId);
		$winCard = getTodayWinCard($this->pdo, $accountId);
		createWinCardItem($this->pdo, (int) $winCard['id'], 'Alpha', 0);
		createWinCardItem($this->pdo, (int) $winCard['id'], 'Beta', 1);

		$items = getWinCardItems($this->pdo, (int) $winCard['id']);
		$success = reorderWinCardItems($this->pdo, $accountId, [(int) $items[0]['id']]);

		self::assertFalse($success);
		self::assertSame(
			['Alpha', 'Beta'],
			array_column(getWinCardItems($this->pdo, (int) $winCard['id']), 'title'),
		);
	}

	public function testReorderWinCardItemsRejectsPastCardItems(): void {
		$accountId = $this->createTestAccount();
		$this->pdo->prepare(
			'INSERT INTO win_cards (account_id, card_date) VALUES (?, DATE_SUB(CURDATE(), INTERVAL 1 DAY))'
		)->execute([$accountId]);
		$pastCardId = (int) $this->pdo->lastInsertId();
		createWinCardItem($this->pdo, $pastCardId, 'Old win', 0);

		$pastItems = getWinCardItems($this->pdo, $pastCardId);

		createTodayWinCard($this->pdo, $accountId);
		$todayCard = getTodayWinCard($this->pdo, $accountId);
		createWinCardItem($this->pdo, (int) $todayCard['id'], 'Today win', 0);

		$success = reorderWinCardItems($this->pdo, $accountId, [(int) $pastItems[0]['id']]);

		self::assertFalse($success);
	}

	public function testDeleteWinCardItemRejectsOtherAccount(): void {
		$ownerId = $this->createTestAccount();
		$otherId = $this->createTestAccount();
		createTodayWinCard($this->pdo, $ownerId);
		$winCard = getTodayWinCard($this->pdo, $ownerId);
		createWinCardItem($this->pdo, (int) $winCard['id'], 'Private win', 0);
		$itemId = (int) getWinCardItems($this->pdo, (int) $winCard['id'])[0]['id'];

		$deleted = deleteWinCardItem($this->pdo, $itemId, $otherId);

		self::assertFalse($deleted);
		self::assertSame(1, $this->countWinCardItems((int) $winCard['id']));
	}

	private function countWinCardItems(int $cardId): int {
		$stmt = $this->pdo->prepare('SELECT COUNT(*) FROM win_card_items WHERE card_id = ?');
		$stmt->execute([$cardId]);

		return (int) $stmt->fetchColumn();
	}
}
