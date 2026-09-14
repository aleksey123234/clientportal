# Security

Day-to-day security index for the client portal. Decisions: [`adr/0003-payments-direct-post.md`](adr/0003-payments-direct-post.md), [`adr/0004-csrf-session-policy.md`](adr/0004-csrf-session-policy.md). Manual checks: [`test-plan-security.md`](test-plan-security.md).

---

## CSRF

- Helper: `App\Services\Csrf` (`ensureToken` / `validate`, field name `csrf_token`).
- Required on **all** state-changing POSTs (login, register, forgot/reset, CIF, documents, profile, settings, pay).
- Failure: HTML error or `{ "ok": false, "error": "Security token mismatch." }`.
- Full CSP is **deferred** (CDN + some inline scripts remain).

---

## Sessions & cookies

| Cookie / session | Policy |
|------------------|--------|
| PHP session | `HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS or `APP_ENV=production` |
| Remember-me | Random token; **SHA-256 hash** stored in DB (migration `021`); clear on logout and on password change/reset |
| Password-reset | Raw token only in email link; **SHA-256 hash** in `users.password_reset_token` (migration `023` clears plaintext); 1-hour expiry |

Session keys include `user_id`, `client_id`, profile display fields — never store PAN/CVD.

After **Settings** password change or **reset-password** success: `AuthSession::onPasswordChanged` NULLs `remember_token`, clears the remember cookie, and `session_regenerate_id(true)` (user stays logged in on Settings).

### Timezone

- `APP_TIMEZONE` (default `America/Toronto`) via `App\Support\AppTimezone`: sets PHP default TZ in `public/index.php` and MySQL session `time_zone` (UTC offset) on each PDO connect so `date()` and `NOW()` match.
- Historical DATETIME rows written under UTC are **not** rewritten.

### Health probe

- `GET /health`: if `HEALTH_TOKEN` is set, require `X-Health-Token` or `Authorization: Bearer`. Production with empty token → **404**. No secrets in the JSON body.

---

## Auth hardening

- Login uses **Client ID** (`users.client_id`), not email.
- Failed-login **throttle** (~10 / 15 min per IP) — persisted in `rate_limit_buckets` (`RateLimiter`); generic error (no user enumeration).
- Forgot-password rate limit (~5 / 15 min per IP) — same table.
- Pay attempts (~10 / 15 min per user id) — same table.
- Register: UUID **format** (`RegistrationToken`) then DB `registration_token` lookup.

---

## Uploads

- Files under `UPLOAD_BASE_PATH` (**outside** web root).
- Document `doc_key` must be on the catalog whitelist (path traversal / unknown keys rejected).
- Downloads resolve via `SecureDownload` under `UPLOAD_BASE_PATH` (`realpath` containment).
- MIME / size limits: see SETUP `php.ini` and `DocumentUploadService`.

---

## Payments / PCI

- **Current:** Moneris **Direct Post** — card data passes through PHP → gateway (cURL). Still in PCI scope.
- **Deferred (roadmap 4b):** hosted / tokenized fields — no tokens planned yet.
- Server computes charge via `PaymentQuoteService`; client `amount` must match or is omitted.
- CVD required server-side (3–4 digits after digit strip).
- Claim + deterministic `order_id` (`PaymentOrderId`); never log PAN/CVD (Monolog `payments` channel).
- Gateway **transport/timeout** (`success=false`): leave pay claim (`moneris_order_id`); do not clear. Decline clears claim.

---

## Inbox HTML

- `service_email_history.body` is sanitized with `HtmlSanitizer` (allowlist) before JSON to the UI; CTA HTML is appended after sanitize (`ServiceEmailCta`).

---

## Logging & errors

- Channels: `app`, `payments`, `security` → `storage/logs/`.
- Uncaught exceptions: user-safe message when `APP_DEBUG=false` (`ExceptionHandler`).
- Do not point portal PDO at the CRM database unless intentional (see [`INTEGRATION_CRM.md`](INTEGRATION_CRM.md)).
