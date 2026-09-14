# Routes & Form Actions Reference

> **There is no public REST API.** Interactive endpoints return JSON shaped as
> `{ "ok": true, … }` / `{ "ok": false, "error": "…" }` (see AJAX sections below).
> Most interactions are HTML form POSTs (some via AJAX `fetch()`).
> **Last updated:** 2026-07-30 (Phase 9)
>
> **Path source of truth:** [`config/routes.php`](../config/routes.php). Keep this doc aligned when adding routes.

---

## Router — `public/index.php` + `config/routes.php`

All HTTP requests are rewritten to `public/index.php` by `.htaccess`.
The front controller loads [`config/routes.php`](../config/routes.php) (path →
`[Controller::class, method, requiresAuth]`) and dispatches via Composer autoload.
Add a route with one line in that map. See [`ARCHITECTURE.md`](ARCHITECTURE.md).

### Auth Guard

- Route `requiresAuth === true` and no session → redirect to `/login`
- Authenticated + visiting `/login` → redirect to `/dashboard`
- Public routes: flag `false` on `login`, `register`, `forgot-password`, `reset-password`, `health`

---

## GET Routes

| Path                  | Controller → Method                         | Layout   | Notes                      |
| --------------------- | ------------------------------------------- | -------- | -------------------------- |
| `/login`              | `AuthController::login()`                   | auth.php | Client ID + password form  |
| `/register/{uuid}`    | `RegisterController::index(uuid)`           | auth.php | 4-step wizard              |
| `/forgot-password`    | `PasswordResetController::forgotPassword()` | —        | **POST JSON only** (UI = login slider) |
| `/reset-password`     | `PasswordResetController::resetPassword()` | auth.php | GET form + POST (new password) |
| `/dashboard`          | `DashboardController::index()`              | main.php | Real-time overview widgets |
| `/services`           | `ServicesController::index()`               | main.php | Services accordion page    |
| `/cif`                | `CifController::index()`                    | main.php | CIF multi-section form     |
| `/cif?action=download&id=` | `CifController::index()`               | —        | Download generated CIF PDF |
| `/documents`          | `DocumentsController::index()`              | main.php | Document management        |
| `/documents/download` | `DocumentsController::index()`              | —        | File download (exits)      |
| `/documents/email`    | `DocumentsController::index()`              | —        | JSON email body + CTA from `action_section` |
| `/payments`           | `PaymentsController::dispatch()`            | main.php | Kanban board; pay = **POST** `?action=pay` |
| `/faq`                | `FaqController::index()`                    | main.php | FAQ search + filters       |
| `/health`             | `HealthController::index()`                 | —        | Ops probe JSON (no auth)   |
| `/profile`            | `ProfileController::index()`                | main.php | Profile page               |
| `/settings`           | `SettingsController::index()`               | main.php | Settings (3 tabs)          |
| `/logout`             | `AuthController::logout()`                  | —        | Destroy session, redirect  |

### `GET /health` — Ops probe

JSON only. Checks DB (`SELECT 1`), `UPLOAD_BASE_PATH` writable, `storage/logs` writable.

**Access:** If `HEALTH_TOKEN` is set, require header `X-Health-Token: <token>` or `Authorization: Bearer <token>` (mismatch → **401**). If `APP_ENV=production` and `HEALTH_TOKEN` is empty → **404**. Non-production with empty token remains open for local dev.

**Healthy 200:** `{ "ok": true, "checks": { "db": "ok", "uploads": "ok", "logs": "ok" } }`  
**Unhealthy 503:** `{ "ok": false, "error": "unhealthy", "checks": { … } }`  

Never returns DSN, passwords, or filesystem paths.

---

## POST Actions — Login

### `POST /login` — Authenticate

| Field       | Name          | Notes                            |
| ----------- | ------------- | -------------------------------- |
| Client ID   | `client_id`   | Matches `users.client_id` column |
| Password    | `password`    |                                  |
| Remember Me | `remember_me` | Checkbox → sets cookie           |
| CSRF        | `csrf_token`  |                                  |

---

## POST Actions — Documents

### `POST /documents` — Upload / Delete

All document POST actions use a hidden `action` field.

#### `action=upload`

| Field        | Name           | Notes                                                                 |
| ------------ | -------------- | --------------------------------------------------------------------- |
| Service Type | `service_type` | e.g. `pardon`, `trp`, `waiver`                                        |
| Document Key | `doc_key`      | e.g. `pardon-rcmp`, `trp-court-records`                               |
| File         | `doc_file`     | Max 25 MB; PDF/JPG/PNG/DOC/DOCX/TXT                                   |
| File name    | `file_name`    | Optional display/download name; defaults to original; disk path unchanged |
| CSRF         | `csrf_token`   |                                                                       |

**Flow:** Upload to `UPLOAD_BASE_PATH/{userId}/{serviceType}/`, insert into `documents` table, redirect to `/documents` (PRG).

#### `action=delete`

| Field       | Name         | Notes                                          |
| ----------- | ------------ | ---------------------------------------------- |
| Document ID | `doc_id`     | Must be owned by user, within 10-minute window |
| CSRF        | `csrf_token` |                                                |

### `GET /documents/download?id=X`

Streams file as attachment. Validates ownership. Returns proper MIME headers.

---

## POST Actions — Profile

All profile POST actions go to `POST /profile` with a hidden `action` field.

### `action=profile` — Save Personal Info

| Field              | Name                 | Notes                          |
| ------------------ | -------------------- | ------------------------------ |
| First / Middle / Last | `first_name`, `middle_name`, `last_name` | Written to `users` |
| Preferred Name     | `preferred_name`     | Profile row                    |
| Date of Birth      | `dob` / `dob_hidden` | `Y-m-d` (or `d/m/Y` parsed)    |
| Timezone           | `timezone`           |                                |
| Additional Contact | `additional_contact` |                                |
| Access to Printer  | `has_printer`        | `"1"`, `"0"`, or `""`          |
| CSRF               | `csrf_token`         |                                |

Travel intent lives on **CIF**, not profile (`intended_travel_date` is not a profile field).

### `action=add_phone` — Add Phone (AJAX)

| Field     | Name              | Notes            |
| --------- | ----------------- | ---------------- |
| Type      | `phone_type`      | ENUM value       |
| Number    | `phone_number`    |                  |
| Extension | `phone_extension` | Optional         |
| Label     | `phone_label`     | For "other" type |
| Main?     | `phone_is_main`   | checked → main   |

There is **no** `delete_phone` action — use `toggle_phone_inactive` for non-main phones.

### `action=toggle_phone_inactive` — Toggle Phone Active/Inactive (AJAX)

| Field | Name       | Notes                                             |
| ----- | ---------- | ------------------------------------------------- |
| ID    | `phone_id` | Non-main phone to toggle `is_old` between 0 and 1 |

### `action=add_email` — Add Email (AJAX)

| Field | Name            | Notes              |
| ----- | --------------- | ------------------ |
| Email | `email_address` |                    |
| Main? | `email_is_main` | checked → main     |

### `action=delete_email` — Delete Email (AJAX)

| Field | Name       | Notes         |
| ----- | ---------- | ------------- |
| ID    | `email_id` | Row to delete |

### `action=address_living` / `action=address_mail` — Save Address (AJAX)

| Field    | Name                              | Notes    |
| -------- | --------------------------------- | -------- |
| Country  | `country`                         | ISO code |
| Province | `province_state`                  |          |
| City     | `city`                            | Trimmed  |
| Postal   | `postal_code`                     |          |
| Street   | `street`                          | Trimmed  |
| Unit     | `unit`                            | Trimmed  |
| Move-in  | `move_in_date` / `move_in_date_hidden` | Optional |

Uses `INSERT ... ON DUPLICATE KEY UPDATE`.

---

## POST Actions — Settings

### `tab=password`

| Field   | Name               | Notes                   |
| ------- | ------------------ | ----------------------- |
| Current | `current_password` | Verified against bcrypt |
| New     | `new_password`     | Min 8 chars             |
| Confirm | `confirm_password` | Must match              |

### `tab=notifications`

| Field    | Name              | Notes    |
| -------- | ----------------- | -------- |
| All      | `notify_all`      | Checkbox |
| Payments | `notify_payments` | Checkbox |
| Case     | `notify_case`     | Checkbox |

### `tab=test_email`

Sends a test notification email to the user's registered email addresses
via `EmailService::notifyUser()`. No additional fields.

### `tab=viewsettings`

| Field     | Name           | Notes          |
| --------- | -------------- | -------------- |
| Sidebar   | `sidebar_side` | `left`/`right` |
| Dark Mode | `dark_mode`    | `"1"`/`"0"`    |

---

## POST Actions — Auth

### `POST /register/{uuid}`

| Field    | Name               | Notes       |
| -------- | ------------------ | ----------- |
| Password | `password`         | Min 8 chars |
| Confirm  | `confirm_password` | Must match  |
| CSRF     | `csrf_token`       |             |

### `POST /forgot-password` — Request reset link (AJAX JSON)

UI lives on the **login** page slider (`public/js/login.js`). There is no standalone forgot-password HTML page.

| Field | Name           | Notes                                      |
| ----- | -------------- | ------------------------------------------ |
| Email | `forgot_email` | Empty/unknown → still `{ "ok": true }`     |
| CSRF  | `csrf_token`   | Required; mismatch → `jsonFail`            |

**Success:** `{ "ok": true }` (always, to avoid email enumeration)  
**Throttle:** `{ "ok": false, "error": "Too many reset requests…" }`

### `POST /reset-password`

| Field    | Name               | Notes                |
| -------- | ------------------ | -------------------- |
| Token    | `token`            | From URL query param |
| Password | `new_password`     | Min 8 chars          |
| Confirm  | `confirm_password` | Must match           |
| CSRF     | `csrf_token`       |                      |

---

## POST Actions — CIF

CIF behaviour details (spousal rules, month pickers, PDF `N/A`, timelines): see
[`CIF_REFERENCE.md`](CIF_REFERENCE.md).

### `POST /cif` — Save CIF Answers (AJAX JSON)

Body is JSON (not `multipart/form-data`):

```json
{
  "data": { "first_name": "…", "addresses": […], "former_spouses": […] },
  "finish": false,
  "csrf_token": "…"
}
```

| Field   | Notes |
| ------- | ----- |
| `data`  | Object of all CIF answers (tables = arrays of row objects) |
| `finish`| Optional. If `true`, runs server Finish validation before save |
| `csrf_token` | **Required.** Session CSRF; reject without match |

**Success:** `{ "ok": true, "progress": 0-100 }`  
**CSRF failure:** `{ "ok": false, "error": "Security token mismatch." }`  
**Finish validation failure:** `{ "ok": false, "error": "…", "errors": ["…", …] }`  
Optional on finish failure: `eligibility_keys` (field keys from `CifEligibility`).

On load/save, `CifTimeline::migrateResponseData()` may rewrite legacy keys
(e.g. flat `spouse_former_*` → `former_spouses` table).

Stored in `cif_responses.response_data`; `progress` recalculated each save.
`completed_at` set when progress reaches 100%.

### `POST /cif?action=generate` — Generate PDFs (AJAX)

| Field  | Name         | Notes            |
| ------ | ------------ | ---------------- |
| Action | `action`     | `"generate"` (query or POST) |
| CSRF   | `csrf_token` | **Required** (FormData or JSON) |

Generates one PDF per applicable service form via `CifPdfGenerator`. Returns JSON
`{ ok, generated: […] }`. Files under `UPLOAD_BASE_PATH/{userId}/cif/`.

Empty fields render as `N/A`. Questions hidden by `show_when` / `hidden_when` are omitted.

### Finish validation (when `finish: true`)

Server checks (mirrored in `cif.js`) via `CifValidation`:

- Required / `required_when` fields that are currently visible
- Address & employment timeline continuity (`CifTimeline`)
- `from` / `to` months must be `yyyy-mm` (or `Present`)
- Spousal rules by `marital_status` (Single skip; Widowed XOR; etc.)
- Applicant + spouse DoBs must be age 18+

---

### `POST /payments?action=pay` — Moneris purchase (AJAX JSON)

Body may be JSON or form fields. Amount is computed server-side via `PaymentQuoteService::quote()`
(subtotal of selected unpaid rows + tax for living-address province, default Ontario).
Optional client `amount` must match ±0.01.

| Field | Name | Notes |
| ----- | ---- | ----- |
| Payment IDs | `payment_ids` | int[] unpaid rows owned by session user |
| Amount | `amount` | Optional; must match server total |
| Card | `pan`, `expiry`, `cvd` | PAN ≥13 digits; expiry `MM/YYYY`; CVD **3–4 digits** (Direct Post) |
| CSRF | `csrf_token` | **Required** |

**Integrity (Phase 4a):** deterministic `order_id`; `FOR UPDATE` claim on `moneris_order_id`; single transactional `UPDATE` to `paid` after approve. Replay of already-paid IDs returns idempotent success without a second Moneris charge. DB update failure after approve → CRITICAL log (no PAN) + support message with `order_id`.

**Success:** `{ "ok": true, "approved": true, "code": "…", "txn_id": "…", "receipt": "…", "card_type": "…" }` (may include `idempotent: true`)  
**Failure:** `{ "ok": false, "error": "…" }` (optional `approved: false`, `code` on decline)  

UI: [`public/js/payments.js`](../public/js/payments.js) checks `data.ok && data.approved`, shows `data.error`; `_monerisPayInFlight` blocks double-submit.

---

### `GET /documents/email?id=` — Inbox email body (AJAX JSON)

**Success:** `{ "ok": true, "id": …, "subject": …, "body": …, … }`  
**Failure:** `{ "ok": false, "error": "…" }`

---

## AJAX Pattern — Modal Forms

Profile page modals use:

```javascript
function ajaxSubmitForm(form, modalId) {
    fetch(form.action, { method: 'POST', body: new FormData(form) })
        .then((r) => r.text())
        .then((html) => {
            // Parse for .alert-danger → show error in modal
            // No error → close modal, refreshContactsColumn()
        });
}
```

`refreshContactsColumn()` re-fetches `/profile`, extracts `#contactsColumn` HTML,
and replaces the current column.

---

## External API Calls (Client-Side)

### Postal Code Auto-Fill

```
GET https://api.zippopotam.us/{country}/{postalCode}
```

Auto-fills city and province/state fields on address modals.

### State → Timezone Suggestion

JS `STATE_TZ` map (50 US states + 13 CA provinces + Jamaica) →
auto-suggests CRM timezone code on profile form.
