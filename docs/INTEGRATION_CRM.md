# CRM integration notes (peer app)

Portal and CRM are **separate applications**. They do not share a PHP process, Composer autoload, or session cookie. Future glue is **database and/or API**, not a shared runtime.

## CRM peer (facts)

| Item | Value |
|------|--------|
| Codebase | Separate repo (e.g. local `test_crmtest`) |
| PHP | **7.4** (`AddHandler application/x-httpd-php74` style) |
| DB access | **mysqli** |
| Database | `crmdevpp_crmdevd` |
| Tables | `core_*` naming |

## Portal (this repo)

| Item | Value |
|------|--------|
| PHP | **≥7.4** (stay aligned with CRM host class; PHP 8 bump deferred) |
| DB access | **PDO** |
| Database | `mysql_clients_portal` |
| Entry | `public/index.php` + Apache CGI/handler OK |

## Do not

- Point portal `.env` `DB_*` at `crmdevpp_crmdevd` unless an intentional integration experiment.
- Assume CRM classes or includes are available inside the portal.
- Expect php-fpm / PHP 8.x on the shared host until CRM and ops catch up (portal ops path: Phase 9).

## Future sync (out of scope for Phase 6)

Possible patterns: shared read-only tables, ETL, or a small API between apps. Until then, treat CRM Client ID / invite tokens as data the portal already stores in its own schema.
