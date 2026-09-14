# Deploy & operations

Production runbook for the client portal. Dev install: [`SETUP_GUIDE.md`](SETUP_GUIDE.md). Security: [`SECURITY.md`](SECURITY.md). CRM peer: [`INTEGRATION_CRM.md`](INTEGRATION_CRM.md). Go-live ticks: [`PROD_CHECKLIST.md`](PROD_CHECKLIST.md).

**Primary host shape:** Apache + **PHP 7.4** CGI / `AddHandler` (CRM-aligned). php-fpm is a short appendix below.

---

## 1. Build

On the deploy host or in a CI artifact:

```bash
cd client-portal
composer install --no-dev --optimize-autoloader
```

Ship `client-portal/` **without** committing `vendor/` if your pipeline builds on the server. DocumentRoot must be **`client-portal/public` only** — never the repo root.

Do **not** deploy a local `Apache24/` tree from git (it is gitignored).

---

## 2. Environment

On the server only:

```bash
cp .env.example .env
# edit .env — never commit
```

| Key | Production expectation |
|-----|------------------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your.domain` |
| `DB_*` | Portal DB `mysql_clients_portal` — **not** CRM `crmdevpp_crmdevd` |
| `MONERIS_TEST_MODE` | `false` |
| `MONERIS_STORE_ID` / `MONERIS_API_TOKEN` | Live merchant credentials |
| `MAIL_*` | Real SMTP |
| `UPLOAD_BASE_PATH` | Absolute path **outside** webroot and **outside** the git tree |
| `LOG_LEVEL` | e.g. `warning` (or empty → warning when not local) |

---

## 3. Migrations

```bash
php bin/migrate.php --status
php bin/migrate.php
php bin/migrate.php --status
```

Apply on the target DB before opening traffic. Existing DBs that pre-date the runner: `php bin/migrate.php --baseline` once (see [`database/README.md`](../database/README.md)).

---

## 4. Apache (PHP 7.4 CGI — primary)

- `DocumentRoot` → `…/client-portal/public`
- `AllowOverride All` + `mod_rewrite` (see `public/.htaccess`)
- PHP 7.4 handler (`AddHandler` / CGI) — same class as CRM
- OS user that runs PHP must **write** `storage/logs` and `UPLOAD_BASE_PATH`

Example handler pattern (paths vary by host):

```apache
AddHandler application/x-httpd-php74 .php
```

### php-fpm (appendix)

Same DocumentRoot and rewrite rules; proxy PHP via FastCGI/php-fpm pool. Prefer this later if the host supports it — not required for CRM-aligned CGI deploys.

---

## 5. Windows vs Linux pitfalls

| Topic | Note |
|-------|------|
| Paths | Use absolute `UPLOAD_BASE_PATH`; on Windows prefer `C:\…`, on Linux `/var/…` |
| PSR-4 casing | Directory names must be `Controllers` / `Services` (Linux is case-sensitive) |
| Permissions | Linux: `chown`/`chmod` for www-data (or pool user) on logs + uploads |
| Apache binaries | Install on the server; do not copy gitignored `Apache24/` |

---

## 6. Post-deploy smoke

1. `GET /health` with `X-Health-Token: <HEALTH_TOKEN>` → **200** with `"ok": true` (DB up, uploads + logs writable). Without token → **401** (or **404** if production misconfigured without `HEALTH_TOKEN`).
2. `GET /login` → **200**.
3. Optional: `SMOKE_BASE_URL=https://your.domain` (+ matching `HEALTH_TOKEN`) `php bin/smoke.php`.

Unhealthy `/health` returns **503** without leaking DSN/passwords/paths.

---

## 7. Backups

| Asset | Cadence | Method |
|-------|---------|--------|
| MySQL `mysql_clients_portal` | Daily (minimum) | `mysqldump` (or host backup panel); retain **14–30 days** |
| `UPLOAD_BASE_PATH` | Daily sync | `rsync` / host file backup; same retention |

Store backups **off** the app server when possible. Encrypt at rest if the host supports it.

### Restore drill (document when performed)

Run on **staging** at least once before go-live; record the date on [`PROD_CHECKLIST.md`](PROD_CHECKLIST.md).

1. Create/restore a staging DB from a recent dump.
2. Point a staging `.env` `DB_*` at that DB; run `php bin/migrate.php --status` (should be clean).
3. Copy **one** sample client file into staging `UPLOAD_BASE_PATH/{userId}/…`.
4. Log in; open Documents (or CIF PDF) and confirm the file downloads.
5. Confirm `/health` → 200.

---

## 8. Logs & alerts

- App logs: Monolog rotating files under `storage/logs/{app,payments,security}-YYYY-MM-DD.log` (14-day rotation in code).
- Apache/PHP `error.log`: rotate via host tools (`logrotate`, Windows log policy).
- **Manual payment alert:** if `payments-*.log` shows repeated `CRITICAL` after Moneris approve (DB update mismatch), page support with the logged `order_id` — treat as possible charged-but-unmarked payments. See [`SECURITY.md`](SECURITY.md) / ADR 0003.

---

## 9. Secrets rotation

| Secret | Steps |
|--------|--------|
| Moneris | Rotate in merchant portal → update `.env` → reload PHP/Apache |
| SMTP | Rotate mailbox/app password → update `MAIL_*` |
| DB | Rotate MySQL user password → update `DB_PASSWORD` → verify `/health` |

Never commit `.env`. After a leak, rotate **immediately** and review `payments` logs.

---

## 10. Related commands

```bash
composer install --no-dev --optimize-autoloader
php bin/migrate.php --status
php bin/lint-php.php          # optional on build agent
vendor/bin/phpunit            # requires require-dev; use on CI, not prod artifact
```
