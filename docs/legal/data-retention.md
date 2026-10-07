# Data retention runbook (internal)

Engineering reference for what the app stores and when it is deleted. User-facing summary lives in `/privacy`.

## Account and user content

| Data | Location | Retention |
|------|----------|-----------|
| Profile, entries, wins, types, stats | MySQL | Until account deletion via Settings |
| Consent audit log | `account_consents` | Until account deletion |
| Bank connection metadata | `bank_connections` | Until disconnect or account deletion |

## Tokens

| Data | Retention |
|------|-----------|
| Password reset tokens | 1 hour validity; delete on use |
| Email verification tokens | 24 hour validity; delete on use |

## Logs

| Data | Retention |
|------|-----------|
| `storage/logs/app.log` | Rotate after ~90 days in production (see `docs/DEPLOYMENT.md`) |

## Bank imports

| Data | Retention |
|------|-----------|
| Normalised entries (`bank_entries` with `entry_reference`) | Until user deletes account, or user disconnects bank and opts to remove imported transactions |
| Raw Enable Banking API payloads | Not stored |

## Backups

Production DB backups may retain deleted user data until backup rotation (default 30 days). Document this in the Privacy Policy once backup policy is final.
