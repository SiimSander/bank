<?php

require_once __DIR__ . '/database.php';

final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
	public function open(string $path, string $name): bool {
		return true;
	}

	public function close(): bool {
		return true;
	}

	public function read(string $id): string|false {
		try {
			$statement = db()->prepare('SELECT data FROM sessions WHERE id = ?');
			$statement->execute([$id]);
			$data = $statement->fetchColumn();
		} catch (Throwable $e) {
			logger()?->error('db', 'Session read failed', ['message' => $e->getMessage()]);

			return '';
		}

		return $data === false ? '' : (string) $data;
	}

	public function write(string $id, string $data): bool {
		try {
			$statement = db()->prepare(
				'INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?)
				ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
			);
			$statement->execute([$id, $data, time()]);
		} catch (Throwable $e) {
			logger()?->error('db', 'Session write failed', ['message' => $e->getMessage()]);

			return false;
		}

		return true;
	}

	public function destroy(string $id): bool {
		try {
			db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
		} catch (Throwable $e) {
			logger()?->error('db', 'Session destroy failed', ['message' => $e->getMessage()]);

			return false;
		}

		return true;
	}

	public function gc(int $max_lifetime): int|false {
		try {
			$statement = db()->prepare('DELETE FROM sessions WHERE last_activity < ?');
			$statement->execute([time() - $max_lifetime]);

			return $statement->rowCount();
		} catch (Throwable $e) {
			logger()?->error('db', 'Session gc failed', ['message' => $e->getMessage()]);

			return false;
		}
	}

	public function validateId(string $id): bool {
		try {
			$statement = db()->prepare('SELECT 1 FROM sessions WHERE id = ?');
			$statement->execute([$id]);

			return $statement->fetchColumn() !== false;
		} catch (Throwable $e) {
			return false;
		}
	}

	public function updateTimestamp(string $id, string $data): bool {
		return $this->write($id, $data);
	}
}
