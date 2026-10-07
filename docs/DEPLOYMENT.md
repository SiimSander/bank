# Production deployment

Hosting-agnostic runbook for deploying PhpLearn.

## Pre-flight checklist

- [ ] Domain and TLS certificate configured (HTTPS only in production)
- [ ] MySQL database created with a dedicated app user (not root)
- [ ] `.env` filled from `.env.example` with `APP_ENV=production` and `APP_DEBUG=false`
- [ ] `secrets/enablebanking/*.pem` uploaded with mode `600`
- [ ] SMTP credentials tested (password reset + email verification)
- [ ] Enable Banking production app registered with redirect URL matching `ENABLE_BANKING_REDIRECT_URL`
- [ ] Web server document root points to `public/` only
- [ ] `storage/logs/` and `storage/backups/` writable by the PHP user
- [ ] `composer test` passes (see [README.md](../README.md#automated-tests))
- [ ] `LEGAL_*` env vars filled and `/privacy` + `/terms` reviewed (see [docs/legal/launch-checklist.md](legal/launch-checklist.md))

## Environment variables

| Key | Required | Description |
|-----|----------|-------------|
| `APP_ENV` | Yes | `production` or `local` |
| `APP_DEBUG` | Yes | `false` in production |
| `APP_URL` | Yes | Public URL, e.g. `https://app.example.com` |
| `TRUST_PROXY` | Prod behind proxy | Set `true` when TLS terminates at nginx/Caddy/Cloudflare |
| `DB_HOST` | Yes | Database host |
| `DB_PORT` | No | Default `3306` |
| `DB_NAME` | Yes | Database name |
| `DB_USER` | Yes | Database user |
| `DB_PASSWORD` | Yes | Database password |
| `MAIL_HOST` | Yes | SMTP host |
| `MAIL_PORT` | No | Default `587` |
| `MAIL_USERNAME` | If auth | SMTP username |
| `MAIL_PASSWORD` | If auth | SMTP password |
| `MAIL_FROM_EMAIL` | Yes | From address |
| `MAIL_FROM_NAME` | No | From name |
| `ENABLE_BANKING_APP_ID` | If LHV | Enable Banking application UUID |
| `ENABLE_BANKING_PRIVATE_KEY_PATH` | If LHV | Path to PEM key, relative to project root |
| `ENABLE_BANKING_REDIRECT_URL` | If LHV | Must match Enable Banking app settings |
| `LOG_PATH` | No | Default `storage/logs/app.log` |
| `LOG_LEVEL` | No | Default `warning` in production |

## First deploy

1. Deploy code to the server (git clone, rsync, etc.).
2. Copy `.env.example` to `.env` and fill all production values.
3. Set permissions:

```bash
chmod 600 .env
chmod 600 secrets/enablebanking/*.pem
chmod -R 775 storage/logs storage/backups
```

4. Import baseline schema (fresh database only):

```bash
mysql -h DB_HOST -u DB_USER -p DB_NAME < db/tables.sql
php bin/migrate-baseline.php
```

5. Apply any migrations added after the baseline:

```bash
php bin/migrate.php up
```

6. Configure the web server (see HTTPS section below).
7. Smoke test:

```bash
curl -s https://your-domain/health
```

Expected: `{"status":"ok","database":"ok","log_writable":true}`

## Subsequent deploys

1. **Backup:**

```bash
php bin/backup-db.php
```

2. Pull / deploy new code.

3. **Migrate:**

```bash
php bin/migrate.php status
php bin/migrate.php up
```

4. Reload PHP-FPM or Apache (if applicable).

5. Smoke test `/health`, login, add a bank entry, and LHV sync (if used).

## Vercel

The repo includes `vercel.json` and `api/index.php`, which run the app on the community `vercel-php` runtime. Local development is unchanged.

1. Create a hosted MySQL database (needs TLS and to be reachable from the internet). Import the schema from your machine:

```bash
mysql -h DB_HOST -u DB_USER -p DB_NAME < db/tables.sql
```

   Then run `php bin/migrate-baseline.php` with the same `DB_*` values set as environment variables.
2. Import the GitHub repo in Vercel. Framework preset `Other`; leave the build command and output directory empty.
3. Add these environment variables in Vercel (names are in `.env.example`):
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<your-domain>`, `TRUST_PROXY=true`
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, plus `DB_SSL=true` (and `DB_SSL_CA_PEM` if the provider needs its own CA)
   - `LOG_TO_STDERR=true` (logs appear in the Vercel dashboard)
   - `MAIL_*` and the `LEGAL_*` values
   - `ENABLE_BANKING_*` only if LHV sync is used; pass the PEM through `ENABLE_BANKING_PRIVATE_KEY` with `\n` for line breaks
4. Sessions are stored in the `sessions` table automatically (`SESSION_DRIVER` defaults to `database` on Vercel).
5. Apply new migrations to the hosted database from your machine before deploying code that needs them:

```bash
DB_HOST=... DB_NAME=... DB_USER=... DB_PASSWORD=... DB_SSL=true php bin/migrate.php up
```

Backups (`bin/backup-db.php`) cannot run on Vercel; use the database provider's backups.

## Rollback

There is no automatic down-migration. Rollback procedure:

1. Restore the pre-deploy backup:

```bash
php bin/restore-db.php --file storage/backups/php_learn_YYYY-MM-DD_HHMMSS.sql.gz --yes
```

2. Redeploy the previous git tag/commit.

3. Verify `/health` and critical user flows.

## HTTPS and reverse proxy

Always serve production over HTTPS. Set `TRUST_PROXY=true` when a reverse proxy terminates TLS so secure session cookies work.

Example nginx snippet:

```nginx
server {
    listen 443 ssl;
    server_name app.example.com;
    root /var/www/php-learn/public;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
        fastcgi_param HTTP_X_FORWARDED_PROTO https;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

## Database backups

| Environment | Frequency | Retention |
|-------------|-----------|-----------|
| Production | Daily cron + before each deploy | 30 daily |
| Staging | Before each migrate | 7 days |

Example cron (daily at 03:00 UTC):

```cron
0 3 * * * cd /var/www/php-learn && php bin/backup-db.php >> storage/logs/backup.log 2>&1
```

Optional logrotate for `storage/logs/app.log`:

```
/var/www/php-learn/storage/logs/app.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
}
```

## Logging and monitoring

Logs are JSON lines at `LOG_PATH` (default `storage/logs/app.log`).

Channels:

| Channel | Events |
|---------|--------|
| `auth` | Failed logins, rate-limit blocks |
| `db` | Connection failures |
| `sync` | LHV auto/manual sync failures |
| `mail` | SMTP errors |
| `migrate` | Failed migrations |
| `backup` | Backup/restore events |
| `health` | Health check DB failures |
| `app` | Uncaught exceptions (production) |

### Monitoring checklist

- Ping `GET /health` every 5 minutes (UptimeRobot, Better Stack, etc.)
- Alert on HTTP 503 from `/health`
- Tail logs for `"level":"error"` or repeated `"channel":"sync"` warnings
- Monitor MySQL disk usage and connection count
- Watch `bank_connections.valid_until` for expiring LHV sessions

### Useful commands

```bash
tail -f storage/logs/app.log
grep '"level":"error"' storage/logs/app.log
grep '"channel":"sync"' storage/logs/app.log
```

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Session not sticking behind proxy | `TRUST_PROXY` not set | Set `TRUST_PROXY=true`, forward `X-Forwarded-Proto` |
| Enable Banking redirect error | Redirect URL mismatch | Match `ENABLE_BANKING_REDIRECT_URL` to Enable Banking app config |
| Mail not sending | SMTP config | Check `MAIL_*` vars, tail logs for `channel":"mail"` |
| `/health` returns 503 | DB down or logs not writable | Check DB credentials and `storage/logs/` permissions |
| Migration failed | SQL error | Fix migration, restore backup if needed, redeploy |

## Security hardening

- `APP_DEBUG=false` in production (hides stack traces from users)
- Never commit `.env` or `secrets/`
- Web root = `public/` only
- Use a dedicated DB user with minimal privileges
- Keep PHP and MySQL patched
