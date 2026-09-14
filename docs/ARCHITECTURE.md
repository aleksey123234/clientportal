# Architecture

Short map of the client portal after Phase 3–6. Not a framework — thin front controller + Composer PSR-4.  
See also: [`SECURITY.md`](SECURITY.md) · [`adr/`](adr/) · [`INTEGRATION_CRM.md`](INTEGRATION_CRM.md).

## Request flow

```
public/index.php
  → Dotenv + ExceptionHandler + session + security headers
  → config/routes.php  (path → [Controller, method, requiresAuth])
  → new Controller → method()
  → Services / views
```

Add a page with **one line** in [`config/routes.php`](../config/routes.php). Auth is the route’s third flag (`true` = redirect to `/login` if no session).

Controllers live under `src/Controllers/` (capital **C**); domain logic under `src/Services/` (capital **S**) — required for Linux/CI PSR-4. Autoload: `App\` → `src/`.

## Platform

- **PHP ≥7.4** (composer constraint). Aligned with CRM peer host class; **PHP 8 bump deferred**. See [`INTEGRATION_CRM.md`](INTEGRATION_CRM.md).
- Apache CGI / `AddHandler` style is supported; php-fpm notes in [`DEPLOY.md`](DEPLOY.md).
- Ops probe: **`GET /health`** (DB ping + uploads/logs writable) — gated by `HEALTH_TOKEN` when set; production requires token (empty → 404). No secrets in body.

## Logging & errors (Phase 6)

| Piece | Role |
|-------|------|
| `App\Support\Log` | Monolog channels **`app`**, **`payments`**, **`security`** → `storage/logs/{channel}-YYYY-MM-DD.log` |
| `LOG_LEVEL` | Env override; default `debug` when `APP_ENV=local`, else `warning` |
| `App\Support\ExceptionHandler` | Registered in `public/index.php`; logs via `Log::app()`; user-safe JSON/HTML (no PDO password / no stack to client when `APP_DEBUG=false`) |

Critical pay / Moneris / auth-register / mail / CIF PDF / DB connect paths use the channels above (never PAN/CVD).

## Layers

| Layer | Role |
|-------|------|
| `public/index.php` | Sole HTTP entry; route dispatch only |
| Controllers | Auth/CSRF, load data, call services, render or JSON |
| Services | Quotes, uploads, CIF validation/PDF, email, Moneris |
| `App\Http\Response` | `jsonOk` / `jsonFail` / `redirect` / `requireAuth` |
| `App\Support\Html` | `Html::e()` for views |
| `App\Support\Asset` | `Asset::url('/js/…')` → `?v=filemtime`; `Asset::cdnTag()` for pinned CDN+SRI |
| `App\Support\Log` | Channel loggers (Monolog) |
| `src/config/database.php` | PDO only — **no** Dotenv (env already loaded) |

## JSON contract

All AJAX endpoints use:

- Success: `{ "ok": true, … }`
- Failure: `{ "ok": false, "error": "…" }` (+ optional fields)

Payments `POST /payments?action=pay` uses the same shape (`ok` + `approved` on success; `error` on failure). See [`API_REFERENCE.md`](API_REFERENCE.md).

### Pay integrity (Phase 4a)

1. Deterministic `order_id` = `portal_u{userId}_{sha256(sortedIds)[0:16]}`.
2. Short `SELECT … FOR UPDATE` claim (`moneris_order_id`) — lock **not** held during Moneris HTTP.
3. After approve: single `UPDATE` to `paid` inside a transaction; mismatch → CRITICAL log (no PAN) + support message with `order_id`.
4. Replay of already-paid IDs → idempotent `jsonOk` (no second charge).
5. Tax province from living `client_addresses.province_state` (code → name); default Ontario.

**PCI note:** Direct Post (PAN through PHP) remains in scope. Hosted tokenization (4b) deferred — no tokens planned yet. Change CC modal is UI stub only (no server path).

### Frontend assets (Phase 5)

- CDN versions + SRI: [`config/cdn.php`](../config/cdn.php) (Bootstrap 5.3.3, Icons 1.11.3, Flatpickr 4.6.13).
- Local assets: `Asset::url()` cache-bust.
- Profile JS modules: `public/js/profile/{address,ajax,contacts}.js` + thin `profile.js`.
- Shared `PortalToast` (`public/js/toast.js`); no inline `onclick`/`onchange` in views.
- Visual checklist: [`VISUAL_QA.md`](VISUAL_QA.md).

## Services map (Phase 3 extracts)

| Service | From |
|---------|------|
| `PaymentQuoteService` | Tax rates + `quote()` for pay + UI |
| `DocumentCatalog` | `SERVICES` / `WAIVER_DOCS` |
| `DocumentUploadService` | Validate/store/DB insert (email notify stays in controller) |
| `CifValidation` | Finish / spousal / adult DoB checks |
| `ProfileService` | Profile POST handlers + upserts |

## Controller sizes & next extracts

| Controller | ~lines | Notes |
|------------|-------:|-------|
| Documents | ~240 | Catalog + upload extracted |
| Profile | ~60 | Dispatch + render |
| Payments | ~300 | Quote extracted; **Kanban `index()` still fat** |
| Cif | ~400 | Validation extracted; **PDF generate orchestrator still here** |

**Backlog (explicit):**

1. Extract Payments Kanban assembly from `PaymentsController::index()`.
2. `CifPdfOrchestrator` (or similar) for `generatePdfs()` full flow.
3. Phase **4b** Moneris hosted / tokenized fields (when account supports it).

Out of scope here: Laravel/DI, full CSP.
