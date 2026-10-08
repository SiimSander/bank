# PhpLearn

Personal finance tracker with bank entries, goals, missing-goals catch-up, month history, and optional LHV sync.

## Local development

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for full setup.

## CSS structure

Styles live in numbered files in `public/assets/css/` (`00-base.css`, `01-layout.css`, ...). The browser loads a single `/styles` request, which `public/index.php` assembles from the list in `src/cssBundle.php`. The URL is a top-level path without extension so it also works with `php -S` started without `public/router.php`.

- The order in `cssBundleFiles()` is the cascade: later files override earlier ones with equal specificity. Do not reorder without checking the pages.
- Add rules to the file that matches the page or component. A new file must be added to `cssBundleFiles()` (a test fails otherwise).
- There is no `index.css` on disk; do not recreate it.
- The stylesheet link carries a `?v=` hash of the bundle, so browsers cache it for a year and pick up every change automatically.

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
