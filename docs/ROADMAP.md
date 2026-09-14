# Client Portal — Full System Polish Roadmap

> Canonical copy for the repo. Cursor plan mirror: `.cursor/plans/portal_polish_roadmap.plan.md`  
> **How to use:** one phase = one Plan-mode session. Do not implement the whole roadmap at once.

Status legend: `pending` · `in_progress` · `done`

| Phase | Title | Status |
|------:|-------|--------|
| 0 | Close WIP (CIF clarity) + clean git | done |
| 1 | Security hard-close | done |
| 2 | Database truth + migration runner | done |
| 3 | Architecture & code quality | done |
| 4a | Payments integrity (pre-PCI) | done |
| 4b | Moneris hosted / tokenized fields (PCI) | pending |
| 5 | Frontend & asset discipline | done |
| 6 | PHP platform, Monolog, Composer | done |
| 7 | Testing & CI | done |
| 8 | Documentation as product | done |
| 9 | Production / ops readiness | done |

Full problem statements, task lists, acceptance criteria, and dependency graph: see the Cursor plan file  
[`portal_polish_roadmap.plan.md`](../../../.cursor/plans/portal_polish_roadmap.plan.md)  
(or open it from Cursor Plans UI).

## Already done (do not redo)

- Repo hygiene: untrack `Apache24/`, gitignore, docs purge
- P0 auth/payments: session cookies, CSRF on login/forgot/reset/pay, server-side pay amount, `registration_token` + migration `020`
- Phase 0: CIF clarity (`public/js/cif/*.js` + `CifVisibility.php`), slim CHANGE_ORDER, purge PHASE diaries, polish ROADMAP
- Phase 1: `Csrf` helper, CIF CSRF, `doc_key` whitelist, login throttle, hashed remember-me, security headers, migration `021`
- Phase 2: `bin/migrate.php`, `schema_migrations`, auth columns `022`, contact CREATE `003c`, SCHEMA aligned to code
- Phase 3: `config/routes.php` + Composer dispatch, `Http\Response` / `Support\Html`, service extracts (quote/catalog/upload/CIF validation/profile), PDO-only `database.php`, unified `{ok,error}` JSON (incl. payments)
- Phase 4a: pay claim + deterministic `order_id`, atomic paid UPDATE, tax from living address, UI double-submit guard, test-plan CSRF/amount/idempotency
- Phase 5: CDN pin+SRI, `Asset::url` cache-bust, profile JS modules, no inline handlers, `PortalToast`, [`VISUAL_QA.md`](VISUAL_QA.md)
- Phase 6: Monolog channels (`app` / `payments` / `security`), exception handler, unused Guzzle removed, `.env.example` + [`INTEGRATION_CRM.md`](INTEGRATION_CRM.md); **PHP stays ≥7.4** (CRM-aligned; PHP 8 deferred)
- Phase 7: PHPUnit units (quote, order_id, CSRF, CIF, register format), `bin/lint-php.php`, optional `bin/smoke.php`, GitHub Actions CI (lint+unit), security/CIF test plans
- Phase 8: README quickstart, API_REFERENCE sync, [`SECURITY.md`](SECURITY.md), [`adr/`](adr/), archive policy, removed obsolete `TASK_REQUIREMENTS.md`
- Phase 9: [`DEPLOY.md`](DEPLOY.md), [`PROD_CHECKLIST.md`](PROD_CHECKLIST.md), `GET /health`, backup/restore drill docs, gitignore upload note

## Next recommended Plan session

**Phase 4b** — Moneris hosted / tokenized fields (PCI), when the merchant account supports tokens.

**Post-polish hardening (2026):** Waves **0–3** done (CIF completed_at, reset hash/revoke, timezone, rate limits, email sanitize, health token, CVD, SecureDownload, PaymentFields/CifProgress units, [`test-plan-hardening.md`](test-plan-hardening.md)). Optional Wave **4** — extract Payment board / CIF form services + shared email-modal JS (readability). Polish core (0–9 except 4b) remains complete.
