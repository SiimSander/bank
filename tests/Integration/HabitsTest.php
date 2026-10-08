<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

final class HabitsTest extends DatabaseTestCase {
	private function addHabit(int $accountId, string $title, string $today): int {
		putenv('TEST_TODAY=' . $today);
		$result = createHabit($this->pdo, $accountId, $title);
		self::assertTrue($result['success'], $result['error'] ?? '');

		return $result['id'];
	}

	public function testRejectsMoreThanEightHabits(): void {
		$accountId = $this->createTestAccount();

		for ($number = 1; $number <= 8; $number++) {
			$this->addHabit($accountId, 'Habit ' . $number, '2026-10-15');
		}

		$result = createHabit($this->pdo, $accountId, 'Habit 9');

		self::assertFalse($result['success']);
		self::assertCount(8, getHabitsForMonth($this->pdo, $accountId, '2026-10-01'));
	}

	public function testRejectsEmptyAndTooLongTitles(): void {
		$accountId = $this->createTestAccount();
		putenv('TEST_TODAY=2026-10-15');

		self::assertFalse(createHabit($this->pdo, $accountId, '   ')['success']);
		self::assertFalse(createHabit($this->pdo, $accountId, str_repeat('a', 61))['success']);
		self::assertTrue(createHabit($this->pdo, $accountId, str_repeat('a', 60))['success']);
	}

	public function testRenameKeepsHistory(): void {
		$accountId = $this->createTestAccount();
		$habitId = $this->addHabit($accountId, 'Read', '2026-10-15');

		self::assertTrue(renameHabit($this->pdo, $accountId, $habitId, 'Read 10 pages')['success']);
		self::assertSame('Read 10 pages', getHabitById($this->pdo, $accountId, $habitId)['title']);
		self::assertFalse(renameHabit($this->pdo, $accountId, $habitId, '')['success']);
	}

	public function testDeletingMidMonthRemovesRowFromThatMonthOnlyAndKeepsPastMonths(): void {
		$accountId = $this->createTestAccount();
		$habitId = $this->addHabit($accountId, 'Water', '2026-09-10');
		self::assertTrue(setHabitStatus($this->pdo, $accountId, $habitId, '2026-09-10', 'done'));

		putenv('TEST_TODAY=2026-10-15');
		self::assertTrue(setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-14', 'done'));
		self::assertTrue(deleteHabit($this->pdo, $accountId, $habitId));

		self::assertSame([], buildHabitMonth($this->pdo, $accountId, '2026-10-01')['habits']);

		$september = buildHabitMonth($this->pdo, $accountId, '2026-09-01');
		self::assertCount(1, $september['habits']);
		self::assertSame(1, $september['done']);
		self::assertSame(20, $september['failed']);
		self::assertSame(['2026-09-01'], getHabitHistoryMonths($this->pdo, $accountId));
	}

	public function testDeletedHabitStillAllowsEditingItsPreviousMonthWithinWindow(): void {
		$accountId = $this->createTestAccount();
		$habitId = $this->addHabit($accountId, 'Water', '2026-09-01');

		putenv('TEST_TODAY=2026-10-03');
		self::assertTrue(deleteHabit($this->pdo, $accountId, $habitId));

		self::assertTrue(setHabitStatus($this->pdo, $accountId, $habitId, '2026-09-28', 'done'));
		self::assertFalse(setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-02', 'done'));
	}

	public function testPositionOfDeletedHabitIsReused(): void {
		$accountId = $this->createTestAccount();
		$first = $this->addHabit($accountId, 'One', '2026-10-15');
		$this->addHabit($accountId, 'Two', '2026-10-15');

		deleteHabit($this->pdo, $accountId, $first);
		$this->addHabit($accountId, 'Three', '2026-10-15');

		$positions = array_column(getHabitsForMonth($this->pdo, $accountId, '2026-10-01'), 'position', 'title');
		self::assertSame(['Three' => 1, 'Two' => 2], $positions);
	}

	public function testOnlyTodayAndPreviousSevenDaysAreEditable(): void {
		$accountId = $this->createTestAccount();
		$habitId = $this->addHabit($accountId, 'Walk', '2026-10-01');
		putenv('TEST_TODAY=2026-10-15');

		self::assertTrue(setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-15', 'done'));
		self::assertTrue(setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-08', 'failed'));
		self::assertFalse(setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-07', 'done'));
		self::assertFalse(setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-16', 'done'));
		self::assertFalse(setHabitStatus($this->pdo, $accountId, $habitId, 'not-a-date', 'done'));
		self::assertFalse(setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-15', 'bogus'));
	}

	public function testPendingCanOnlyBeRestoredForToday(): void {
		$accountId = $this->createTestAccount();
		$habitId = $this->addHabit($accountId, 'Walk', '2026-10-01');
		putenv('TEST_TODAY=2026-10-15');

		setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-15', 'done');
		setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-14', 'done');

		self::assertTrue(setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-15', 'pending'));
		self::assertFalse(setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-14', 'pending'));
		self::assertSame('pending', getHabitDayItems($this->pdo, $accountId, '2026-10-15')[0]['status']);
		self::assertSame('done', getHabitDayItems($this->pdo, $accountId, '2026-10-14')[0]['status']);
	}

	public function testUnmarkedPastDaysCountAsFailedAndTodayStaysPending(): void {
		$accountId = $this->createTestAccount();
		$habitId = $this->addHabit($accountId, 'Walk', '2026-10-01');
		putenv('TEST_TODAY=2026-10-04');
		setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-02', 'done');

		$month = buildHabitMonth($this->pdo, $accountId, '2026-10-01');
		$statuses = $month['habits'][0]['statuses'];

		self::assertSame('failed', $statuses[1]);
		self::assertSame('done', $statuses[2]);
		self::assertSame('failed', $statuses[3]);
		self::assertSame('pending', $statuses[4]);
		self::assertSame('pending', $statuses[5]);
		self::assertSame(1, $month['done']);
		self::assertSame(2, $month['failed']);
	}

	public function testDaysBeforeTheHabitExistedAreNotCounted(): void {
		$accountId = $this->createTestAccount();
		$this->addHabit($accountId, 'Late start', '2026-10-10');
		putenv('TEST_TODAY=2026-10-12');

		$month = buildHabitMonth($this->pdo, $accountId, '2026-10-01');

		self::assertSame('none', $month['habits'][0]['statuses'][9]);
		self::assertSame(2, $month['failed']);
	}

	public function testOtherAccountsCannotChangeHabits(): void {
		$owner = $this->createTestAccount();
		$habitId = $this->addHabit($owner, 'Private', '2026-10-15');
		$stranger = $this->createTestAccount();

		self::assertFalse(setHabitStatus($this->pdo, $stranger, $habitId, '2026-10-15', 'done'));
		self::assertFalse(renameHabit($this->pdo, $stranger, $habitId, 'Hacked')['success']);
		self::assertFalse(deleteHabit($this->pdo, $stranger, $habitId));
		self::assertSame('Private', getHabitById($this->pdo, $owner, $habitId)['title']);
	}

	public function testHistoryListsOnlyPastMonthsNewestFirst(): void {
		$accountId = $this->createTestAccount();
		$this->addHabit($accountId, 'Walk', '2026-07-20');
		putenv('TEST_TODAY=2026-10-15');

		self::assertSame(['2026-09-01', '2026-08-01', '2026-07-01'], getHabitHistoryMonths($this->pdo, $accountId));
	}

	public function testHistoryCanIncludeTheCurrentMonthFirst(): void {
		$accountId = $this->createTestAccount();
		$this->addHabit($accountId, 'Walk', '2026-09-20');
		putenv('TEST_TODAY=2026-10-15');

		self::assertSame(['2026-10-01', '2026-09-01'], getHabitHistoryMonths($this->pdo, $accountId, true));
		self::assertSame(['2026-09-01'], getHabitHistoryMonths($this->pdo, $accountId));
	}

	public function testHistoryHidesCurrentMonthWhenAllHabitsWereDeletedThisMonth(): void {
		$accountId = $this->createTestAccount();
		$habitId = $this->addHabit($accountId, 'Walk', '2026-09-20');
		putenv('TEST_TODAY=2026-10-15');
		deleteHabit($this->pdo, $accountId, $habitId);

		self::assertSame(['2026-09-01'], getHabitHistoryMonths($this->pdo, $accountId, true));
	}

	public function testDeletingAccountRemovesHabits(): void {
		$accountId = $this->createTestAccountWithPassword();
		$habitId = $this->addHabit($accountId, 'Walk', '2026-10-15');
		setHabitStatus($this->pdo, $accountId, $habitId, '2026-10-15', 'done');

		$username = (string) $this->pdo->query('SELECT username FROM accounts WHERE id = ' . $accountId)->fetchColumn();

		self::assertTrue(deleteAccount($this->pdo, $accountId, 'test-password12', $username));

		self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM habits')->fetchColumn());
		self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM habit_logs')->fetchColumn());
	}
}
