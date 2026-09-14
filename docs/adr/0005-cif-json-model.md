# ADR 0005 — CIF JSON response model

**Status:** Accepted  
**Date:** 2026-07

## Context

CIF is a large multi-section questionnaire with conditional visibility, eligibility, and PDF fill. A normalized column-per-question schema would explode migrations and slow iteration against change orders.

## Decision

- Persist answers as JSON in `cif_responses.response_data` (tables = arrays of row objects).
- Question metadata in [`src/config/cif_questions.php`](../../src/config/cif_questions.php).
- Visibility / eligibility / timelines implemented in PHP (`CifVisibility`, `CifEligibility`, `CifTimeline`, `CifValidation`) with a JS port under `public/js/cif/` — keep behaviour in sync.
- Finish may return `errors` plus optional `eligibility_keys`.

## Consequences

- Fast to extend fields without ALTER TABLE per question.
- JSON shape is a contract — document in [`CIF_REFERENCE.md`](../CIF_REFERENCE.md); unit-test pure helpers.
- PDF generation reads the same visibility rules so hidden fields are omitted.
