<?php

const MIGRATIONS_DIR = __DIR__ . '/../db/migrations';

function ensureSchemaMigrationsTable(PDO $pdo): void {
	$pdo->exec(
		'CREATE TABLE IF NOT EXISTS schema_migrations (
			version VARCHAR(255) PRIMARY KEY,
			applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		)'
	);
}

/** @return list<string> */
function migrationFiles(): array {
	$files = glob(MIGRATIONS_DIR . '/*.sql') ?: [];
	sort($files, SORT_STRING);

	return array_map('basename', $files);
}

/** @return list<string> */
function appliedMigrations(PDO $pdo): array {
	$statement = $pdo->query('SELECT version FROM schema_migrations ORDER BY version ASC');

	return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function printMigrationUsage(): void {
	echo "Usage:\n";
	echo "  php bin/migrate.php status\n";
	echo "  php bin/migrate.php up [--dry-run]\n";
}

function runMigrationStatus(PDO $pdo): int {
	ensureSchemaMigrationsTable($pdo);
	$applied = appliedMigrations($pdo);

	echo "Applied migrations:\n";
	if ($applied === []) {
		echo "  (none)\n";
	} else {
		foreach ($applied as $version) {
			echo "  - {$version}\n";
		}
	}

	echo "\nPending migrations:\n";
	$pending = array_diff(migrationFiles(), $applied);
	if ($pending === []) {
		echo "  (none)\n";

		return 0;
	}

	foreach ($pending as $version) {
		echo "  - {$version}\n";
	}

	return 0;
}

function runMigrationsUp(PDO $pdo, bool $dryRun): int {
	ensureSchemaMigrationsTable($pdo);
	$applied = appliedMigrations($pdo);
	$pending = array_values(array_diff(migrationFiles(), $applied));

	if ($pending === []) {
		echo "No pending migrations.\n";

		return 0;
	}

	foreach ($pending as $version) {
		$path = MIGRATIONS_DIR . '/' . $version;
		$sql = file_get_contents($path);

		if ($sql === false) {
			fwrite(STDERR, "Could not read migration file: {$version}\n");

			return 1;
		}

		if ($dryRun) {
			echo "[dry-run] would apply {$version}\n";
			continue;
		}

		echo "Applying {$version}...\n";

		try {
			$pdo->exec($sql);
			$insert = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
			$insert->execute([$version]);
		} catch (Throwable $e) {

			fwrite(STDERR, "Migration failed: {$version}\n");
			fwrite(STDERR, $e->getMessage() . "\n");
			logger()?->error('migrate', 'Migration failed', [
				'version' => $version,
				'error' => $e->getMessage(),
			]);

			return 1;
		}
	}

	echo "Done.\n";

	return 0;
}

function markAllMigrationsApplied(PDO $pdo): int {
	ensureSchemaMigrationsTable($pdo);
	$applied = appliedMigrations($pdo);
	$insert = $pdo->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)');
	$marked = 0;

	foreach (migrationFiles() as $version) {
		if (in_array($version, $applied, true)) {
			continue;
		}

		$insert->execute([$version]);
		$marked++;
		echo "  marked {$version}\n";
	}

	return $marked;
}
