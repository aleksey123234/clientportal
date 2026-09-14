# CIF (Client Information Form) — Reference

> Last updated: 2026-07-30  
> **This is the living CIF howto.** Locked product decisions: [CHANGE_ORDER_PORTAL_JUL2026.md](CHANGE_ORDER_PORTAL_JUL2026.md).

---

## File map (start here)

| Path | Role |
|------|------|
| `src/config/cif_questions.php` | Field schema (keys, forms, columns, show_when) |
| `src/views/cif/cif-page.php` | HTML render |
| `src/views/cif/_render_fields.php` | Field-type partials (if present) |
| `public/js/cif-datepicker.js` | Flatpickr month/year header dropdowns |
| `public/js/cif/*.js` | Visibility, timeline, tables, validate, save modules |
| `public/js/cif.js` | Orchestrator (nav + wire-up) |
| `public/css/cif.css` | Progress, tables, picker styling |
| `src/controllers/CifController.php` | Load / save / Finish / PDF trigger |
| `src/services/CifVisibility.php` | `rulesMatch` + `isVisible` (PHP + PDF) |
| `src/services/CifEligibility.php` | Citizenship Finish rules + Client Care text |
| `src/services/CifTimeline.php` | Address/employment gaps + JSON migrate |
| `src/services/CifPdfGenerator.php` | Filled PDF output |
| `docs/API_REFERENCE.md` | HTTP routes / POST payloads |

---

## Overview

Clients fill a multi-section form filtered by their active services. Answers are one JSON blob in `cif_responses.response_data`. Finish = client + server validation → save + PDF generation.

### Service → form exclusions

| Rule | Effect |
|------|--------|
| Criminal Rehab active | Skip TRP questions |
| Pardon active | Skip Expungement questions |
| NEXUS | No CIF form |
| WR ≡ W | Same CIF rules/fields as Waiver unless noted |

---

## Question schema

| Key | Purpose |
|-----|---------|
| `type` | `text`, `date`, `month`, `date_optional_day`, `textarea`, `dropdown`, `yes_no`, `checkboxes`, `table` (legacy render also supports single `checkbox`) |
| `forms` | Which `service_type` values show this field |
| `row` / `col` | Bootstrap layout |
| `show_when` / `hidden_when` | Conditional visibility |
| `show_also_when_forms` | Visible if client has **any** of these forms **OR** `show_when` matches |
| `required` / `required_when` | Required rules |
| `lock_when` | Lock value (e.g. military To = Present) |
| `timeline` | Address/employment continuous coverage |
| `add_label` | Table “Add row” button text |

**Yes/No questions use `type: yes_no`** (radio Yes/No), not `checkbox`.

### Visibility (`show_when` + `show_also_when_forms`)

Expected value may be a **string or array** (OR). Shared:

- JS: `public/js/cif/visibility.js`
- PHP: `CifVisibility::isVisible` (Controller + PDF)

Example — Pardon always sees Canada offences table; Waiver-only only after Canada = Yes:

```php
'show_when'            => ['waiver_convicted_canada' => 'Yes'],
'show_also_when_forms' => ['pardon'],
```

---

## Citizenship + eligibility

| Key | Purpose |
|-----|---------|
| `us_citizenship` | Always when citizenship applies |
| `green_card` | Only with Waiver or Waiver Renewal |
| `canadian_citizenship` | Yes/No |

| Services | UI | Persist |
|----------|-----|---------|
| No W and no WR | One label **US citizenship/Green Card** | Only `us_citizenship` |
| W or WR | **US citizenship** + **Green Card** | Both keys |

**Finish only** (`CifEligibility` + under-field errors). Draft save never blocked.

| Services | Error if |
|----------|----------|
| CR and/or TRP | `us_citizenship = No` or `canadian_citizenship = Yes` |
| W and/or WR | `us_citizenship = Yes` or `green_card = Yes` or `canadian_citizenship = No` |

Both families apply if client has both. Text: `CifEligibility::CLIENT_CARE_MESSAGE` (also `data-client-care-message` on the page).

---

## Travel

`CIF_TRAVEL_CORE` = CR, TRP, W, WR (not Pardon/Expg).

| Key | Forms | Notes |
|-----|-------|-------|
| `intended_travel_date` | CORE | Was `proposed_entry_date` (migrated) |
| `length_of_stay` | CORE | |
| `alien_registration_number` | W + WR | |
| `deported_removed_canada` (+ `_details`) | CR | `yes_no` + follow-up |
| `denied_entry_canada` / `denied_entry_when` | CR | when = `date_optional_day` |
| `travel_purpose` | W + WR | Purpose for visiting the **US** |
| `travel_purpose_canada` | CR + TRP | Purpose for visiting **Canada** |
| `inadmissibility_reason` | W + WR | Checkboxes; label “select all that apply” |
| `immigration_details` | W + WR | Follow-up for Immigration Issues / Other |
| `applied_waiver_before` | W + WR | |
| `waiver_filed_where` / `waiver_filed_when` | W + WR | |
| `us_6months_stay` | W + WR | |
| `us_immigration_applications` | W + WR | |
| `us_benefit_denied` | W + WR | |
| `wr_entry_date`, `wr_last_waiver_when` | WR | |

`date_optional_day` stores `YYYY-MM` or `YYYY-MM-DD`.

---

## Military

CR abroad follow-ups live in the **Military** section (same tab as served?), not Travel.

| Key | Forms | Notes |
|-----|-------|-------|
| `cr_served_military` | CR only (**not** TRP) | `yes_no` |
| `military_abroad_dd214` | CR | show when served = Yes |
| `military_abroad_service` | CR | Country / From / To table when abroad = Yes |
| `is_military_member` … | Pardon | Canadian Forces block (unchanged) |

---

## Criminal

| Key | Forms | Notes |
|-----|-------|-------|
| `offences_table` | CR, TRP | Column label **Statute #** |
| `offences_expunged` / `_which` | CR | `yes_no` + textarea |
| `offences_table_ca` | Pardon, W, WR | **Police**; always if Pardon; else Canada = Yes |
| `waiver_convicted_canada` / `_us` | W, WR | `yes_no` |
| `offences_table_us` | W, WR | **Police** when US = Yes |
| `conviction_ages` | Pardon | Only remaining conviction textarea |
| `waiver_has_pardon` | W, WR | Yes → red link `/documents?service=…&doc=waiver-granted-pardon` |

Helper: `cif_offences_columns('Statute'|'Police')`.  
Migrate: free-text expunged → which; `waiver_convicted_in_us` → US table row.

---

## Spousal (Waiver) ↔ `marital_status`

| `marital_status` | UI | Finish |
|------------------|-----|--------|
| **Single** | Empty state only | No spousal checks |
| **Widowed** | Current + termination + Former | XOR: full Current **or** ≥1 Former |
| **Married / Common-law** | Current + Former | Current names + dob |
| **Divorced / Separated / Annulled / Other** | Current + termination + Former | Current + termination + ≥1 Former |

Former table key: `former_spouses`. Adult DoB (18+) on applicant, current spouse, former rows.

---

## Addresses / employment / datepickers

| Field | Format | UI |
|-------|--------|-----|
| Address / employment `from`, `to` | `yyyy-mm` | Month grid + year dropdown; typing OK; **max = current month** |
| First row `to` | `Present` | Locked |
| Full dates (DoB, marriage, …) | `Y-m-d` | Matching month + year header dropdowns |
| Offences `date` | `yyyy-mm` | Month picker |

Plugins: `cif-datepicker.js` (`window.CifDatepicker`). CDN: flatpickr + monthSelect in `layouts/main.php`.

### Timeline windows

`CifTimeline::resolveWindows` — **all** applicable windows must pass:

| Forms | Window |
|-------|--------|
| CR / TRP | since age 18 |
| Pardon (addresses) | last **10** years |
| Waiver / WR | last **5** years |

Gaps ≥ 2 months → error; UI marks bounding `from`/`to` (`cif-timeline-gap`). JS port: `public/js/cif/timeline.js` (must match PHP).

---

## PDF

`CifPdfGenerator` uses `CifVisibility` to skip hidden fields. Empty cells → ASCII `N/A`. Long tables get continuation pages.

---

## Client JS behaviour

- Section nav, auto-save on section change, progress
- Conditional visibility (+ hide empty `data-cif-row` wrappers)
- Dynamic table rows
- Finish: required, timelines, yyyy-mm, spousal, adult DoB, eligibility → save `{ finish: true }` → generate PDFs
