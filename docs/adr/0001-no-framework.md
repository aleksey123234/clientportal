# ADR 0001 — No framework

**Status:** Accepted  
**Date:** 2026-07

## Context

The portal must stay operable by a solo developer on a PHP **7.4** shared-host class (CRM peer is also 7.4). A full Laravel/Symfony stack would raise upgrade and hosting cost without clear product need.

## Decision

Ship a **thin front controller** ([`public/index.php`](../../public/index.php)) + Composer PSR-4 (`App\` → `src/`) + one-line route map ([`config/routes.php`](../../config/routes.php)). Controllers under `src/Controllers/`; domain logic under `src/Services/`. No DI container, no ORM.

## Consequences

- Fast to understand; easy to host with Apache CGI/`AddHandler`.
- Fat controllers remain a backlog (Kanban assembly, CIF PDF orchestrator) — extract services deliberately, not via framework conventions.
- PHP 8 / framework adoption deferred until CRM/host strategy changes.
