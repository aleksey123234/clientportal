# ADR 0004 — Session CSRF policy

**Status:** Accepted  
**Date:** 2026-07

## Context

State-changing forms and JSON endpoints were inconsistently protected; forged POSTs (pay, CIF, auth) are an unacceptable risk on a client portal.

## Decision

- One helper: [`App\Services\Csrf`](../../src/Services/Csrf.php) — session field `csrf_token`, `hash_equals` validation.
- **All** mutations require a valid token (login, register, forgot/reset, CIF, documents, profile, settings, pay).
- Shared test coverage: `Tests\Unit\CsrfTest`; manual matrix in [`test-plan-security.md`](../test-plan-security.md).

## Consequences

- Slight UX cost (token in every form / JSON body).
- CSP / SameSite hardening remains a follow-up; CSRF is the primary mutation guard today.
- See [`SECURITY.md`](../SECURITY.md).
