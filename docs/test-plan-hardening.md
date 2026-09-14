# Hardening — Manual Test Plan (Waves 0–2)

**Audience:** local / staging QA (step-by-step).  
**Automated:** `cd client-portal && vendor/bin/phpunit` and `php bin/lint-php.php`  
**Related:** [`test-plan-security.md`](test-plan-security.md) · [`test-plan-payments.md`](test-plan-payments.md) · [`SECURITY.md`](SECURITY.md)

After each `.env` change, reload the app (front controller reads Dotenv per request). Prefer PowerShell/`curl` for health headers.

---

## 0 · Prep

- [ ] `php bin/migrate.php --status` — migrations through **`024`** applied (`023` reset tokens, `024` rate_limit_buckets)
- [ ] `.env` has `APP_TIMEZONE=America/Toronto` (or your Eastern zone)
- [ ] Portal reachable (e.g. `http://localhost`)

---

## 1 · Wave 0 — CIF `completed_at` + reset hash + revoke

### CIF completed_at (MySQL + UI `/cif`)

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| C1 | Fill CIF to **100%** progress; check `cif_responses.completed_at` | Not NULL | ☐ |
| C2 | Change a field so progress **&lt; 100%**; autosave; SELECT again | **Same** `completed_at` (not cleared) | ☐ |
| C3 | Reach 100% again; SELECT | Timestamp **unchanged** (not shifted) | ☐ |

### Password reset hash

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| R1 | Forgot-password for a real email | Email link has **raw** token; DB `password_reset_token` = `sha256(raw)` (64 hex), **≠** raw | ☐ |
| R2 | Open link, set new password | Success; token columns NULL; **same link again** → invalid/expired | ☐ |
| R3 | After migrate `023` (or cleared tokens) | Old plaintext reset URL fails | ☐ |

### Remember-me revoke on password change

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| V1 | Login with **Remember me** | Cookie `remember_token` set | ☐ |
| V2 | Settings → change password | DB `remember_token` NULL; old cookie does not auto-login after logout | ☐ |
| V3 | Stay on Settings after change | Still logged in; session cookie regenerated | ☐ |

---

## 2 · Wave 1 — Timezone, pay claim, throttle, email XSS

### Timezone

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| T1 | After a portal request, compare wall clock to new DB `NOW()` / `last_login_at` | ≈ Eastern (±1 min), not +4h UTC | ☐ |
| T2 | New CIF `completed_at` or login timestamp | Matches local time | ☐ |

Historical rows written before the fix may still look +4h — only **new** writes matter.

### Pay claim (Moneris)

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| P1 | Transport/timeout (`success=false`) — hard without mock | Code review OK: claim **kept**; message mentions wait/retry + `order_id` | ☐ |
| P2 | Sandbox **decline** card | Claim cleared; can retry pay | ☐ |

See also [`test-plan-payments.md`](test-plan-payments.md) §18.10–18.11 · `PaymentOrderId` docblock.

### Rate limits (`rate_limit_buckets`)

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| L1 | **11** failed logins; clear session cookie between tries | Still throttled by IP (generic invalid credentials) | ☐ |
| L2 | **6** forgot-password submits (same IP, 15 min) | 6th → `Too many reset requests…` | ☐ |
| L3 | Spam Pay Now (~10+) | `Too many payment attempts…` | ☐ |

### Inbox XSS / JSON `ok`

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| X1 | Put `<script>` / `<img onerror=…>` in `service_email_history.body`; open in Documents modal | No script run; dangerous tags stripped | ☐ |
| X2 | Open `/documents/email?id=999999999` while logged in (or Services modal error path) | `{"ok":false,"error":"Email not found."}`; warning in UI | ☐ |

---

## 3 · Wave 2 — Health, CVD, downloads, query trim

### Health (`HEALTH_TOKEN`)

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| H1 | Local, empty `HEALTH_TOKEN`, `APP_ENV` not production | `GET /health` → **200** + `checks` | ☐ |
| H2 | Set `HEALTH_TOKEN=secret123`; open `/health` in browser (no header) | **401** `Unauthorized` | ☐ |
| H3 | `Invoke-WebRequest … -Headers @{ "X-Health-Token"="secret123" }` | **200** | ☐ |
| H4 | Temporarily `APP_ENV=production` + empty token | **404**; then set `APP_ENV=local` again | ☐ |

PowerShell examples:

```powershell
Invoke-WebRequest -Uri "http://localhost/health" -UseBasicParsing
Invoke-WebRequest -Uri "http://localhost/health" -Headers @{ "X-Health-Token" = "secret123" } -UseBasicParsing
```

### CVD + downloads

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| C1 | Pay with empty / 2-digit CVD (Network tab) | `Missing or invalid payment fields.` | ☐ |
| C2 | Pay with CVD 3 or 4 digits (sandbox) | Passes field check (approve/decline from gateway OK) | ☐ |
| D1 | Documents → download own file | File downloads | ☐ |
| D2 | (Optional) Poison `documents.file_path` outside `UPLOAD_BASE_PATH` | **404**; restore path after | ☐ |
| D3 | CIF → download generated PDF | Opens inline PDF | ☐ |

### Light optimization regression

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| O1 | Profile → save name/phone | UI shows new values after save | ☐ |
| O2 | Open `/cif` | Progress / `completed_at` still correct | ☐ |

---

## 4 · Automated (every PR)

```bash
cd client-portal
vendor/bin/phpunit
php bin/lint-php.php
```

Optional: `SMOKE_BASE_URL=http://localhost` (+ `HEALTH_TOKEN` if set) `php bin/smoke.php`

---

## Pass summary

| Wave | Focus | All pass? |
| ---- | ----- | --------- |
| 0 | CIF completed_at, reset hash, revoke | ☐ |
| 1 | TZ, claim, throttle, email XSS | ☐ |
| 2 | Health, CVD, SecureDownload, Profile/CIF load | ☐ |
| CI | phpunit + lint | ☐ |
