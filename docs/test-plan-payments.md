# Payments Page — Manual Test Plan

**Date:** April 2026
**Page:** `/payments`
**Files under test:**

- `src/controllers/PaymentsController.php`
- `src/services/MonerisService.php`
- `src/views/payments/payments-page.php`
- `public/js/payments.js`
- `public/css/payments.css`

---

## 1 · Pre-conditions

| #   | Condition                                                                                  | How to verify                       |
| --- | ------------------------------------------------------------------------------------------ | ----------------------------------- |
| 1   | You are logged in as a test user who has payment rows in DB                                | See "Payments" in nav               |
| 2   | `.env` has `MONERIS_TEST_MODE=true`, `MONERIS_STORE_ID=store5`, `MONERIS_API_TOKEN=yesguy` | `cat client-portal/.env`            |
| 3   | Test user has `cc_last4` and `cc_expiry` in `users` table                                  | e.g. `last4=4829`, `expiry=09/2027` |
| 4   | At least one payment is `pending` (upcoming) and one is `missed`                           | Check Payment Plan column           |

---

## 2 · Summary Bar (top 4 stat cards + missed payments row)

| #   | What to do                    | What to look for                                                                                 | Pass |
| --- | ----------------------------- | ------------------------------------------------------------------------------------------------ | ---- |
| 2.1 | Load `/payments`              | 4 stat cards visible: **Paid**, **Outstanding**, **Discount**, **Total**                         | ☐    |
| 2.2 | Check **Paid** card           | Shows `$` amount ≥ 0, blue colour                                                                | ☐    |
| 2.3 | Check **Outstanding** card    | Shows `$` amount ≥ 0, yellow/warning colour                                                      | ☐    |
| 2.4 | Check **Discount** card       | Shows `$0.00` (or configured value), amber colour                                                | ☐    |
| 2.5 | Check **Total** card          | Shows grand total with tax; sub-line shows base + tax %, e.g. `$5,000.00 + 13% tax (Ontario)`    | ☐    |
| 2.6 | Check **Missed Payments** row | Red banner; shows sum of all overdue installments with tax included; `N unpaid` counter on right | ☐    |
| 2.7 | If no missed payments         | Missed row shows `$0.00`, counter hidden                                                         | ☐    |

---

## 3 · Kanban Board — Column 1: Service Cost

| #   | What to do            | What to look for                                                           | Pass |
| --- | --------------------- | -------------------------------------------------------------------------- | ---- |
| 3.1 | Inspect Column 1      | Coloured left border per service type; service label and `$` total per row | ☐    |
| 3.2 | User with no services | Shows "No services enrolled." placeholder                                  | ☐    |

---

## 4 · Kanban Board — Column 2: Payment Plan

| #   | What to do                 | What to look for                                        | Pass |
| --- | -------------------------- | ------------------------------------------------------- | ---- |
| 4.1 | Check paid instalments     | Green "Paid" badge, no **Pay Now** button               | ☐    |
| 4.2 | Check missed instalments   | Red "Missed" badge, red **Pay Now** button              | ☐    |
| 4.3 | Check upcoming instalments | Grey "Upcoming" badge, green **Pay Now** button         | ☐    |
| 4.4 | Verify dates order         | Cards sorted ascending by `due_date`                    | ☐    |
| 4.5 | Verify amounts include tax | Amount on card = base × (1 + 0.13), matches summary bar | ☐    |

---

## 5 · Moneris Pay Now Modal — Opening & Auto-fill

| #   | What to do                                    | What to look for                                                                        | Pass |
| --- | --------------------------------------------- | --------------------------------------------------------------------------------------- | ---- |
| 5.1 | Click **Pay Now** on any upcoming/missed card | Modal opens; header says "Secure Payment"                                               | ☐    |
| 5.2 | Check amount summary row                      | Shows correct due date and `$` amount matching the card                                 | ☐    |
| 5.3 | Check card-on-file hint                       | Blue info line: "Card on file ends in **XXXX**" visible (if user has cc_last4)          | ☐    |
| 5.4 | Check expiry field                            | Pre-filled with saved `cc_expiry` value (e.g. `09/2027`), field is readonly             | ☐    |
| 5.5 | Click the calendar icon on Expiry             | Flatpickr calendar opens                                                                | ☐    |
| 5.6 | Pick a different expiry month                 | Field updates to new `MM/YYYY`; hidden input `monerisCardExpiryHidden` holds same value | ☐    |
| 5.7 | User has NO saved card (`cc_last4` empty)     | Hint div is hidden; expiry field is empty                                               | ☐    |
| 5.8 | Close modal and reopen                        | All fields reset; expiry cleared; hint hidden; no error/success messages                | ☐    |

---

## 6 · Moneris Pay Now Modal — Card Number Field

| #   | What to do                                   | What to look for                                  | Pass |
| --- | -------------------------------------------- | ------------------------------------------------- | ---- |
| 6.1 | Type digits in card number                   | Auto-spaces every 4 digits: `4242 4242 4242 4242` | ☐    |
| 6.2 | Type a Visa number (starts with 4)           | Card icon turns **blue** `bi-credit-card-fill`    | ☐    |
| 6.3 | Type a Mastercard number (starts with 51–55) | Card icon turns **yellow/warning**                | ☐    |
| 6.4 | Type an Amex number (starts with 34 or 37)   | Card icon turns **green**                         | ☐    |
| 6.5 | Type an unknown number                       | Card icon stays plain grey                        | ☐    |
| 6.6 | Type > 16 digits                             | Input stops at 16 digits (19 chars with spaces)   | ☐    |

---

## 7 · Moneris Pay Now Modal — Validation (submit with bad data)

| #   | What to do                           | What to look for                                                                      | Pass |
| --- | ------------------------------------ | ------------------------------------------------------------------------------------- | ---- |
| 7.1 | Click **Pay** with all fields empty  | Fields highlight red; no request sent                                                 | ☐    |
| 7.2 | Fill name + card, leave expiry empty | Expiry field highlights red (`is-invalid`); no request sent                           | ☐    |
| 7.3 | Fill everything but CVD              | CVD field highlights red                                                              | ☐    |
| 7.4 | Enter 12-digit card number           | Backend returns `Missing or invalid payment fields` (or frontend `required` stops it) | ☐    |

---

## 8 · Moneris Pay Now Modal — Happy Path (test card)

| #   | What to do                                                                                                | What to look for                                                                                       | Pass |
| --- | --------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ | ---- |
| 8.1 | Fill: Name = `Test User`; Card = `4242 4242 4242 4242`; Expiry = any future month via picker; CVD = `123` | —                                                                                                      | ☐    |
| 8.2 | Click **Pay $XXX.XX**                                                                                     | Spinner shown; form hidden; buttons disabled                                                           | ☐    |
| 8.3 | Wait for response                                                                                         | Green success alert: "Payment approved! Receipt: …"                                                    | ☐    |
| 8.4 | After 2 seconds                                                                                           | Modal closes; page reloads; paid card now shows green "Paid" badge; no Pay Now button                  | ☐    |
| 8.5 | Check DB                                                                                                  | `payments` row has `status='paid'`, `moneris_txn_id` filled, `moneris_order_id` = `portal_N_TIMESTAMP` | ☐    |

---

## 9 · Moneris Pay Now Modal — Declined Card

| #   | What to do                                                                         | What to look for                                   | Pass |
| --- | ---------------------------------------------------------------------------------- | -------------------------------------------------- | ---- |
| 9.1 | Use Moneris test decline card: `5454 5454 5454 5454`, expiry past (e.g. `01/2020`) | —                                                  | ☐    |
| 9.2 | Click Pay                                                                          | Red error alert with gateway decline message       | ☐    |
| 9.3 | Verify DB                                                                          | No status change; payment still `pending`/`missed` | ☐    |
| 9.4 | Try again with valid card after decline                                            | Payment goes through on retry                      | ☐    |

---

## 10 · Moneris Pay Now Modal — Network / Server Errors

| #    | What to do                                                                      | What to look for                                                              | Pass |
| ---- | ------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- | ---- |
| 10.1 | Temporarily set invalid `MONERIS_STORE_ID` in `.env`, submit valid card         | Error: "Gateway connection failed." or gateway decline                        | ☐    |
| 10.2 | Submit with tampered `payment_ids` (IDs belonging to another user via DevTools) | Backend returns `Invalid payment selection.` (403-style JSON)                 | ☐    |
| 10.3 | Submit already-paid payment ID                                                  | Backend returns `Invalid payment selection.` (status not in `pending/missed`) | ☐    |
| 10.4 | Submit while not logged in (clear session cookie)                               | Backend returns `Not authenticated.`                                          | ☐    |

---

## 11 · Kanban Board — Column 3: Payment Method

| #    | What to do                          | What to look for                                | Pass |
| ---- | ----------------------------------- | ----------------------------------------------- | ---- |
| 11.1 | Check "Credit card number" field    | Shows `xxxx xxxx xxxx XXXX` with last 4 from DB | ☐    |
| 11.2 | Check "Expiry date" field           | Shows `MM/YYYY` from DB (e.g. `09/2027`)        | ☐    |
| 11.3 | Check "Method of payment"           | Shows correct method from `payments` table      | ☐    |
| 11.4 | Check "Monthly payment"             | Correct amount (total / installments)           | ☐    |
| 11.5 | Click **Change Credit Card** button | Change CC modal opens                           | ☐    |

---

## 12 · Change Credit Card Modal — Basic Validation

| #    | What to do                                          | What to look for                                       | Pass |
| ---- | --------------------------------------------------- | ------------------------------------------------------ | ---- |
| 12.1 | Open modal, click **Submit Request** with all empty | All required fields highlight red; no submission       | ☐    |
| 12.2 | Fill name, card, expiry, CVV, address, city, postal | Fields pass validation                                 | ☐    |
| 12.3 | Check card number auto-spacing                      | `1234 5678 9012 3456` — spaces inserted every 4 digits | ☐    |
| 12.4 | Close modal, reopen                                 | All fields cleared; warning hidden; success hidden     | ☐    |

---

## 13 · Change Credit Card Modal — Requested Start Date (flatpickr)

| #    | What to do                        | What to look for                                | Pass |
| ---- | --------------------------------- | ----------------------------------------------- | ---- |
| 13.1 | Click the Start Date field        | Flatpickr calendar opens                        | ☐    |
| 13.2 | Select today's date               | Date fills in DD/MM/YYYY format                 | ☐    |
| 13.3 | Try to select a past date         | Calendar does not allow it (`minDate: 'today'`) | ☐    |
| 13.4 | Hidden input `ccChangeDateHidden` | Has value in `YYYY-MM-DD` format                | ☐    |

---

## 14 · Change Credit Card Modal — **4-Day Proximity Rule** ⚠️

> The next payment date is read from `data-next-payment` on `#ccChangeDateInput`.
> If `(nextPaymentDate − chosenDate) ≤ 4 days` → block submit and show warning.

| #    | What to do                                                          | What to look for                                                                                             | Pass |
| ---- | ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ | ---- |
| 14.1 | Find the next payment date (visible in Payment Plan column)         | Note the date, e.g. `Apr 15, 2026`                                                                           | ☐    |
| 14.2 | In Start Date picker, choose that **exact** same date (0 days away) | Yellow/red warning banner appears: _"We are unable to process your payment online, please call accounting…"_ | ☐    |
| 14.3 | Click **Submit Request** while warning is visible                   | **Nothing happens** — submit is blocked                                                                      | ☐    |
| 14.4 | Choose a date 1 day before payment (−1 day)                         | Warning appears; submit blocked                                                                              | ☐    |
| 14.5 | Choose a date 4 days before payment                                 | Warning appears; submit blocked                                                                              | ☐    |
| 14.6 | Choose a date **5 days** before payment                             | Warning disappears; submit allowed                                                                           | ☐    |
| 14.7 | Choose a date well in the future (30+ days)                         | No warning; submit succeeds; success message shown                                                           | ☐    |
| 14.8 | Warning is shown → pick a safe date → re-submit                     | Warning hides; submit succeeds                                                                               | ☐    |
| 14.9 | User with no upcoming payments (`nextPayment` empty)                | No warning ever; submit always allowed                                                                       | ☐    |

---

## 15 · Kanban Board — Column 4: Additional Info

| #    | What to do                                                                   | What to look for                                | Pass |
| ---- | ---------------------------------------------------------------------------- | ----------------------------------------------- | ---- |
| 15.1 | Check **Admin Fee**                                                          | Shows `$0.00` or configured fee amount          | ☐    |
| 15.2 | Check **Tax Rate**                                                           | Shows `13%` (Ontario) and tax amount in dollars | ☐    |
| 15.3 | Select a day from dropdown (1–28), add reason text, click **Submit Request** | Success message appears; button disabled        | ☐    |
| 15.4 | Select day without reason                                                    | Success message still appears (reason optional) | ☐    |

---

## 16 · Dark Mode

| #    | What to do                                         | What to look for                                          | Pass |
| ---- | -------------------------------------------------- | --------------------------------------------------------- | ---- |
| 16.1 | Switch to dark theme                               | Kanban columns, cards, stat bars all use dark backgrounds | ☐    |
| 16.2 | Open Pay Now modal in dark mode                    | Modal content dark; form fields dark; text readable       | ☐    |
| 16.3 | Open Change CC modal in dark mode                  | Same — dark modal, readable fields                        | ☐    |
| 16.4 | Open flatpickr calendar in dark mode (both modals) | Calendar styled with dark background                      | ☐    |

---

## 17 · Responsive / Mobile

| #    | What to do                   | What to look for                                                 | Pass |
| ---- | ---------------------------- | ---------------------------------------------------------------- | ---- |
| 17.1 | Resize browser to 768px wide | Kanban columns stack 2×2                                         | +    |
| 17.2 | Resize to 480px (mobile)     | Columns stack 1×4; all text readable; Pay Now buttons full width | ☐    |
| 17.3 | Open Pay Now modal on mobile | Modal fills screen; form usable                                  | ☐    |

---

## 18 · Edge Cases / Security

JSON shape: `{ "ok": true, … }` / `{ "ok": false, "error": "…" }` (Phase 3+).

**Automated (Phase 7):** run `vendor/bin/phpunit` — `PaymentQuoteServiceTest` (server tax/total), `PaymentOrderIdTest` (deterministic claim `order_id`), `CsrfTest`. Manual rows below still needed for Moneris/session HTTP.

| #    | What to do                                              | What to look for                                                            | Pass |
| ---- | ------------------------------------------------------- | --------------------------------------------------------------------------- | ---- |
| 18.1 | Manually POST to `/payments?action=pay` with no session | `{ "ok": false, "error": "Not authenticated." }`                            | ☐    |
| 18.2 | POST without / with wrong `csrf_token`                  | `{ "ok": false, "error": "Security token mismatch." }`                      | ☐    |
| 18.3 | POST with `payment_ids` of another user's payments      | `{ "ok": false, "error": "Invalid payment selection." }`                    | ☐    |
| 18.4 | POST with already-paid IDs (same session)               | Idempotent `{ "ok": true, "approved": true, … }` — **no second Moneris charge** | ☐    |
| 18.5 | POST with `amount` forged (±$1 from server total)       | `{ "ok": false, "error": "Amount mismatch. Please reload and try again." }` | ☐    |
| 18.6 | POST with `pan = "123"` (< 13 digits)                   | `{ "ok": false, "error": "Missing or invalid payment fields." }`            | ☐    |
| 18.6b | POST with empty / 2-digit `cvd`                        | `{ "ok": false, "error": "Missing or invalid payment fields." }`            | ☐    |
| 18.7 | POST with `expiry = ""`                                 | `{ "ok": false, "error": "Missing or invalid payment fields." }`            | ☐    |
| 18.8 | Double-click Pay Now while request in flight            | UI ignores second click; server claim prevents double charge                | ☐    |
| 18.9 | XSS: put `<script>alert(1)</script>` in cardholder name | Field not submitted to Moneris (cardholder name not in XML); no alert fires | ☐    |
| 18.10 | Gateway timeout / transport fail (`success=false`)    | `moneris_order_id` **kept**; message mentions wait/retry + `order_id`; retry → already in progress | ☐    |
| 18.11 | Explicit card decline                                 | Claim cleared; can pay again                                                | ☐    |

Claim / `order_id` policy is documented on `App\Services\PaymentOrderId` and implemented in `PaymentsController::pay`. Beginner walkthrough: [`test-plan-hardening.md`](test-plan-hardening.md).

**Note:** Change CC modal is UI-only (no server POST / CSRF). Pay Now uses Direct Post (PAN → PHP → Moneris); PCI hosted (4b) deferred.

---

## 19 · MonerisService Unit Checks

Run these manually via a test PHP script or inspect logs:

| #   | Test input            | Expected `formatExpiry()` output |
| --- | --------------------- | -------------------------------- |
| A   | `"04/2026"`           | `"2604"`                         |
| B   | `"12/2025"`           | `"2512"`                         |
| C   | `"01/2030"`           | `"3001"`                         |
| D   | `"04/26"` (MM/YY)     | `"2604"`                         |
| E   | `"042026"` (no slash) | `"2604"`                         |
| F   | `""` (empty)          | `""`                             |

---

## 20 · Quick Regression Checklist (after any code change)

- [ ] Payment Plan column shows correct badges (Paid / Missed / Upcoming)
- [ ] Pay Now button missing on Paid cards ✓
- [ ] Modal opens with correct amount from clicked card ✓
- [ ] Expiry auto-fills from saved card ✓
- [ ] 4-day block works on CC change modal ✓
- [ ] Successful payment → page reloads → card shows Paid ✓
- [ ] Dark mode — no white boxes in modals ✓
- [ ] No JS console errors on page load ✓
