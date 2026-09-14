# ADR 0003 — Payments Direct Post (hosted deferred)

**Status:** Accepted (4b pending)  
**Date:** 2026-07

## Context

Clients pay instalments via Moneris. Hosted tokenization reduces PCI scope but needs merchant account features that are not available yet.

## Decision

1. **Now:** Moneris **Direct Post** through PHP (`MonerisService` cURL). Server-side quote (`PaymentQuoteService`), deterministic `order_id` (`PaymentOrderId`), short DB claim, atomic paid UPDATE.
2. **Later (roadmap 4b):** hosted / tokenized card fields when the account supports them.
3. **Never** log PAN/CVD; payment failures go to Monolog `payments`.

## Consequences

- Portal remains in Direct Post PCI scope until 4b.
- Change-card UI may exist as a stub without a server charge path.
- Integrity regressions covered by unit tests (quote / order_id) + [`test-plan-payments.md`](../test-plan-payments.md).
