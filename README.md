# PhpLearn

Personal finance tracker with bank entries, goals, missing-goals catch-up, month history, and optional LHV sync.

## Local development

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for full setup.

## Automated tests

Money logic is covered by PHPUnit (goal targets, missing goals, monthly stats, net worth, LHV import mapping).

### One-time test database setup

```bash
php bin/test-db-setup.php
```

Creates `php_learn_test` (or `DB_NAME` from env), imports `db/tables.sql`, and marks migrations as applied.

### Run tests

If you don't have Composer globally installed, use the project-local `composer.phar`:

```bash
# First time only — downloads composer.phar + PHPUnit
curl -sS https://getcomposer.org/installer | php
php composer.phar install

# Run tests
php composer.phar test
```

Or use the helper script (does the above automatically if needed):

```bash
chmod +x bin/test.sh
./bin/test.sh
```

If you do have Composer on your PATH:

```bash
composer install
composer test
```

Tests use `TEST_TODAY=2026-09-15` (see `phpunit.xml`) so date-sensitive goal logic is deterministic.

Integration tests require MySQL on the host/port configured in `phpunit.xml` (default `127.0.0.1:3308`).
