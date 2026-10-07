<?php

declare(strict_types=1);

namespace Tests\Integration;

use DatabaseSessionHandler;
use Tests\Support\DatabaseTestCase;

final class DatabaseSessionHandlerTest extends DatabaseTestCase {
	private DatabaseSessionHandler $handler;

	protected function setUp(): void {
		parent::setUp();
		require_once dirname(__DIR__, 2) . '/src/DatabaseSessionHandler.php';
		$this->pdo->exec('TRUNCATE TABLE sessions');
		$this->handler = new DatabaseSessionHandler();
	}

	public function testReadsBackWhatWasWritten(): void {
		$this->handler->write('session-a', 'user_id|i:7;');

		self::assertSame('user_id|i:7;', $this->handler->read('session-a'));
	}

	public function testUnknownSessionReadsAsEmpty(): void {
		self::assertSame('', $this->handler->read('missing'));
		self::assertFalse($this->handler->validateId('missing'));
	}

	public function testWritingTwiceReplacesTheData(): void {
		$this->handler->write('session-a', 'first');
		$this->handler->write('session-a', 'second');

		self::assertSame('second', $this->handler->read('session-a'));
		self::assertTrue($this->handler->validateId('session-a'));
	}

	public function testDestroyRemovesTheSession(): void {
		$this->handler->write('session-a', 'data');
		$this->handler->destroy('session-a');

		self::assertSame('', $this->handler->read('session-a'));
	}

	public function testGarbageCollectionRemovesOnlyExpiredSessions(): void {
		$this->handler->write('old', 'data');
		$this->handler->write('fresh', 'data');
		$this->pdo->prepare('UPDATE sessions SET last_activity = ? WHERE id = ?')->execute([time() - 8000, 'old']);

		$this->handler->gc(7200);

		self::assertSame('', $this->handler->read('old'));
		self::assertSame('data', $this->handler->read('fresh'));
	}
}
