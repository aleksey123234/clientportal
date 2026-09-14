# Client Portal — Documentation

[![CI](https://github.com/Rimoker/ClientsPortal/actions/workflows/ci.yml/badge.svg)](https://github.com/Rimoker/ClientsPortal/actions/workflows/ci.yml)

Living docs for the portal (auth, payments, CIF, setup). Start from the project [`README.md`](../README.md) for quickstart.

## Read first

| Doc | Purpose |
| --- | --- |
| [INSTALL_FOR_BEGINNERS.md](INSTALL_FOR_BEGINNERS.md) | Zero-experience Windows install (migrations or external dump) |
| [SETUP_GUIDE.md](SETUP_GUIDE.md) | Local install (PHP **7.4**, MySQL, Apache **not** in git) |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Front controller, layers, logging |
| [SECURITY.md](SECURITY.md) | CSRF, cookies, uploads, PCI status |
| [DEPLOY.md](DEPLOY.md) | Production deploy, backups, restore drill |
| [PROD_CHECKLIST.md](PROD_CHECKLIST.md) | Go-live / staging copy-paste checklist |
| [adr/](adr/) | Architecture decision records |
| [INTEGRATION_CRM.md](INTEGRATION_CRM.md) | CRM peer facts — separate app/DB; no shared PHP |
| [API_REFERENCE.md](API_REFERENCE.md) | Routes and request/response notes |
| [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) | Tables, columns, migrations index |
| [CIF_REFERENCE.md](CIF_REFERENCE.md) | CIF form model, visibility, eligibility |
| [CHANGE_ORDER_PORTAL_JUL2026.md](CHANGE_ORDER_PORTAL_JUL2026.md) | CIF change-order scope and decisions |
| [test-plan-payments.md](test-plan-payments.md) | Payments QA checklist |
| [test-plan-security.md](test-plan-security.md) | CSRF / register / auth checklist |
| [test-plan-hardening.md](test-plan-hardening.md) | Post-polish Waves 0–2 (timezone, throttle, health, CVD, downloads) |
| [test-plan-cif.md](test-plan-cif.md) | CIF eligibility / visibility checklist |
| [VISUAL_QA.md](VISUAL_QA.md) | Desktop/mobile smoke + asset/a11y checklist |
| [ROADMAP.md](ROADMAP.md) | Multi-phase polish roadmap (one phase = one Plan session) |

## Archive policy

**`docs/archive/` is historical only.** Do not use [`archive/retell`](archive/retell/) or [`archive/me`](archive/me/) for feature work, setup, or agent context. Prefer SETUP / API / SCHEMA / CIF_REFERENCE / SECURITY / ROADMAP / ADRs.

| Path | Contents |
| --- | --- |
| [archive/retell/](archive/retell/) | Legacy Retell AI agent / voice docs (not maintained) |
| [archive/me/](archive/me/) | Scratch notes / email drafts |

**Already done (do not redo):** polish **Phases 0–9** (through ops readiness). Direct Post still in PCI scope until **4b** (hosted tokens) — deferred next.

---

## CSRF policy

All state-changing requests (POST/JSON mutations) require a valid session `csrf_token`. Shared helper: `App\Services\Csrf`. Details: [SECURITY.md](SECURITY.md). Enforcing CSP is deferred (CDN + inline scripts).

---

## Database migrations

```bash
cd client-portal
php bin/migrate.php            # apply all pending
php bin/migrate.php --status
php bin/migrate.php --baseline # existing DB once
```

Details: [database/README.md](../database/README.md) · [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) · [adr/0002-migration-runner.md](adr/0002-migration-runner.md).

---

## Follow-up engineering

Phased work lives in [ROADMAP.md](ROADMAP.md). Use that file — do not treat this section as the backlog.

---

## Smoke checklist (P0 + Phase 1)

- [ ] `git status` does not churn `Apache24/` logs or binaries
- [ ] Forgot password without CSRF → reject
- [ ] Reset password POST without CSRF → reject
- [ ] Forgot password rate limit (≈5 / 15 min) returns controlled failure
- [ ] Pay without CSRF → reject
- [ ] Pay with forged client `amount` → server recomputes / ignores; charge matches DB + tax
- [ ] Replay pay on already-paid IDs → idempotent ok / no second charge
- [ ] Double-click Pay Now → single in-flight request
- [ ] Register with fake UUID → fail; valid pending `registration_token` → activate + redirect login
- [ ] Login + remember-me: cookie `HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS / production; DB stores SHA-256 of token
- [ ] CIF save / generate without CSRF → reject
- [ ] Upload `doc_key=../x` or unknown key → reject
- [ ] ≥10 failed logins / 15 min → throttled (generic error)
- [ ] HTML responses include `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`
