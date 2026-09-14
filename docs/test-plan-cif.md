# CIF — Manual Test Plan

**Date:** 2026-07  
**Automated:** `vendor/bin/phpunit` → `CifVisibilityTest`, `CifEligibilityTest`  
**Reference:** [`CIF_REFERENCE.md`](CIF_REFERENCE.md), [`CHANGE_ORDER_PORTAL_JUL2026.md`](CHANGE_ORDER_PORTAL_JUL2026.md)

---

## 1 · Eligibility (Finish)

| # | Forms | Answers | Expected | Pass |
| --- | --- | --- | --- | --- |
| 1.1 | `criminal-rehab` / `trp` | US=Yes, Canadian=No | Finish allowed | ☐ |
| 1.2 | CR/TRP | US=No and/or Canadian=Yes | Client Care message; blocked | ☐ |
| 1.3 | `waiver` / `waiver-renewal` | US=No, Canadian=Yes, green_card=No | Finish allowed | ☐ |
| 1.4 | Waiver | US=Yes and/or green_card=Yes and/or Canadian=No | Client Care; blocked | ☐ |
| 1.5 | Empty citizenship fields | — | Required validation (eligibility skips empties) | ☐ |

Unit coverage mirrors 1.1–1.4 via `CifEligibility::validate`.

---

## 2 · Visibility

| # | What to do | What to look for | Pass |
| --- | --- | --- | --- |
| 2.1 | Answer a `show_when` parent (e.g. marital) | Dependent questions appear | ☐ |
| 2.2 | Answer `hidden_when` trigger | Dependent questions hide | ☐ |
| 2.3 | Form-gated `show_also_when_forms` | Question shows for matching service forms only | ☐ |

Unit coverage: `CifVisibilityTest` fixtures.

---

## 3 · Manual still required

| # | What to do | Pass |
| --- | --- | --- |
| 3.1 | Full Finish → PDF generate for Waiver + CR | ☐ |
| 3.2 | Progress % matches visible required fields | ☐ |
| 3.3 | CSRF on CIF POST (see [`test-plan-security.md`](test-plan-security.md)) | ☐ |

---

## 4 · Quick regression

- [ ] `vendor/bin/phpunit` — CIF unit suite green
- [ ] No console errors on `/cif` after answer changes
