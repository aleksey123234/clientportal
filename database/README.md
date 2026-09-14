# Database — migrations & seeds

Charset: `utf8mb4` / `utf8mb4_unicode_ci`. Database name from `.env` (`DB_DATABASE`, default `mysql_clients_portal`).

## Policy

- **Forward-only.** No down migrations. Fix forward with a new numbered file if needed.
- Runner selects the DB via PDO DSN. Leading `USE mysql_clients_portal;` in SQL files is stripped/ignored.
- Apply **all pending** files in one run (sorted by filename).

## Runner

```bash
# From client-portal/
php bin/migrate.php            # apply ALL pending migrations
php bin/migrate.php --status   # list applied vs pending
php bin/migrate.php --baseline # mark all current *.sql as applied (existing DB), then run pending
```

### Fresh install

1. Create empty database `mysql_clients_portal`.
2. `php bin/migrate.php` — applies `001` … latest in one shot.

### Existing portal DB

1. `php bin/migrate.php --baseline` once (records every current migration filename without re-running).
2. `php bin/migrate.php` — applies only new files (e.g. `022+`).

Table `schema_migrations(filename PK, applied_at)` tracks what ran.

## File order (highlights)

| File | Role |
|------|------|
| `001`–`002` | users, client_profiles |
| `003_create_settings_table.sql` | documents (legacy), user_settings, sessions |
| `003b_payments_system.sql` | client_id, payments stack (runs after settings) |
| `003c_create_client_contact_tables.sql` | phones / emails / addresses CREATE |
| `004` / `008` | idempotent ADD label / move_in_date |
| `020` | registration_token (no dependency on remember_token) |
| `021` | clear plaintext remember tokens |
| `022` | remember_token + password_reset_* (idempotent) |
| `023` | clear plaintext password_reset tokens (SHA-256 store) |
| `024` | rate_limit_buckets (login / forgot / pay) |

## Gaps closed in Phase 2

| Gap | Fix |
|-----|-----|
| No migration for remember / password_reset columns | `022` |
| `020` used `AFTER remember_token` | patched |
| Docs claimed `password_resets` table | removed; columns on `users` |
| No CREATE for contact tables | `003c` |
| Ambiguous dual `003_*` | renamed payments → `003b` |
| No runner | `bin/migrate.php` |

## Seeds

All files under `seeds/` are **DEV ONLY** — hardcoded `user_id=1` / demo `client_id`. Do not run on production.
