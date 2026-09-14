# Security — Manual Test Plan

**Date:** 2026-07  
**Automated:** `vendor/bin/phpunit` → `CsrfTest`, `RegistrationTokenTest`  
**HTTP smoke:** [`http/smoke.http`](http/smoke.http) or `SMOKE_BASE_URL=… php bin/smoke.php`  
**Related:** [`test-plan-payments.md`](test-plan-payments.md) §18 (pay CSRF / amount) · post-polish hardening (throttle, reset hash, health token): [`test-plan-hardening.md`](test-plan-hardening.md)

---

## 1 · CSRF

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| 1.1 | Load `/login`, submit with empty/wrong `csrf_token` | Error / not logged in | ☐ |
| 1.2 | Forgot-password / reset POST without CSRF | Rejected | ☐ |
| 1.3 | Register POST without CSRF | “Security token mismatch” (or equivalent) | ☐ |
| 1.4 | CIF save / finish without CSRF | `{ "ok": false, … }` | ☐ |
| 1.5 | Pay POST without CSRF | See payments §18.2 | ☐ |

Unit coverage: `Tests\Unit\CsrfTest` (ensure + validate).

---

## 2 · Registration token

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| 2.1 | `GET /register/not-a-uuid` | Invalid-link UI; no PHP fatal | ☐ |
| 2.2 | Valid UUID shape **not** in `users.registration_token` | Same invalid/expired UI | ☐ |
| 2.3 | Valid pending token → set password → login | Account active; token cleared | ☐ |

Unit coverage: `RegistrationToken::isValidFormat` only. DB lookup is manual (no SQLite in CI).

---

## 3 · Auth hardening (Phase 1)

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| 3.1 | Rapid failed logins | Throttle / lockout message after limit | ☐ |
| 3.2 | Session cookie | `HttpOnly`; `Secure` when HTTPS / production | ☐ |
| 3.3 | Response headers on HTML page | Baseline security headers present | ☐ |

---

## 4 · Quick regression

- [ ] `vendor/bin/phpunit` green
- [ ] `php bin/lint-php.php` green
- [ ] Optional: smoke against local Apache
- [ ] Local only: `php bin/migrate.php --status` (needs MySQL; **not** in GitHub Actions)
