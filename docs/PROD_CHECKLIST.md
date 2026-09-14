# Production / staging go-live checklist

Copy-paste before promoting a build. Narrative: [`DEPLOY.md`](DEPLOY.md).

**Environment:** _______________ (staging / production)  
**Date:** _______________  
**Operator:** _______________

---

## Config

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` is `https://…` (no trailing junk)
- [ ] HTTPS live; session cookies Secure / HSTS path OK
- [ ] `MONERIS_TEST_MODE=false`
- [ ] Live `MONERIS_STORE_ID` / `MONERIS_API_TOKEN` (not `store5` / `yesguy`)
- [ ] `MAIL_*` delivers to a real inbox (test send or known path)
- [ ] `DB_*` points at **portal** DB (`mysql_clients_portal`), not CRM
- [ ] DB user is least-privilege (no need for SUPER)
- [ ] `UPLOAD_BASE_PATH` absolute, **outside** webroot and git tree, writable by PHP
- [ ] `LOG_LEVEL` appropriate (`warning` or empty)
- [ ] `HEALTH_TOKEN` set (non-empty); store with deploy secrets
- [ ] `APP_TIMEZONE` set (e.g. `America/Toronto`)

## Build & schema

- [ ] Deployed with `composer install --no-dev --optimize-autoloader`
- [ ] DocumentRoot = `client-portal/public` only
- [ ] `php bin/migrate.php --status` shows no pending (or applied this release)
- [ ] `.env` present on server; **not** in git (`git check-ignore -v .env`)

## Smoke

- [ ] `GET /health` **without** token → **401** (or **404** if token unset in production — should not happen if checklist above followed)
- [ ] `GET /health` with `X-Health-Token: <HEALTH_TOKEN>` → **200** `{ "ok": true, … }`
- [ ] `GET /login` → **200**
- [ ] Login works (Client ID + password)
- [ ] Pay path: sandbox smoke **or** production card policy acknowledged in writing
- [ ] Optional: `SMOKE_BASE_URL=…` (+ `HEALTH_TOKEN` if set) `php bin/smoke.php`

## Backups & restore

- [ ] Scheduled DB dump (daily+)
- [ ] Scheduled `UPLOAD_BASE_PATH` sync
- [ ] Retention documented (14–30 days)
- [ ] Restore drill completed on staging — **date:** _______________

## Secrets & ops

- [ ] Rotation owners known (Moneris / SMTP / DB)
- [ ] On-call knows to watch `storage/logs/payments-*.log` for `CRITICAL`
- [ ] No `*.log` / uploads committed in the release artifact

---

**Sign-off:** _______________
