# ADR 0002 — Forward-only migration runner

**Status:** Accepted  
**Date:** 2026-07

## Context

Schema lived as numbered SQL files with drift between docs and a live DB. Framework migrators (Laravel, Phinx) add dependencies and PHP version pressure.

## Decision

Use [`bin/migrate.php`](../../bin/migrate.php): apply pending `database/migrations/*.sql` in name order; track applied files in `schema_migrations`. Support `--status` and `--baseline` for existing databases. Document truth in [`DATABASE_SCHEMA.md`](../DATABASE_SCHEMA.md) and [`database/README.md`](../../database/README.md).

## Consequences

- Fresh installs and teammate DBs stay reproducible without Slack dumps.
- No automatic rollbacks — fix-forward with new SQL files.
- CI does **not** run migrate (needs MySQL); local `php bin/migrate.php --status` is the check.
