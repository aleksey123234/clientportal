# Database Schema — Client Portal

> MySQL 8.0 · Database name: **`mysql_clients_portal`**
> Charset: `utf8mb4`, Collation: `utf8mb4_unicode_ci`
> Last updated: 2026-07-30
> Migrations: `php bin/migrate.php` — see [`database/README.md`](../database/README.md)

---

## Entity-Relationship Overview

```
users ─┬── client_profiles      (1:1)
       ├── client_phones         (1:N)
       ├── client_emails         (1:N)
       ├── client_addresses      (1:N)
       ├── user_settings         (1:N per key)
       ├── sessions              (1:N)
       ├── documents             (1:N)
       ├── payment_plans ───── payments      (1:N)
       │       └──────────── service_costs   (N:1)
       ├── user_extra_services ── service_costs (N:1)
       ├── nsf_fees ←── payments (1:1)          ← deprecated, not loaded by portal
       ├── cif_responses         (1:1)
       ├── cif_generated_pdfs    (1:N)
       └── user_services ─┬── service_courts_police   (1:N)
                          ├── service_email_history    (1:N)
                          ├── service_sbc_records      (1:N)
                          ├── service_conviction_records (1:N)  ← retained, not loaded
                          ├── service_nexus_records    (1:N)
                          └── service_nexus_codes      (1:N)    ← retained, not loaded
```

`schema_migrations` tracks applied filenames (migration runner).
`rate_limit_buckets` stores login / forgot / pay throttles (no FK).

Password reset tokens live on **`users`** (`password_reset_token` / `password_reset_expires`) — there is no `password_resets` table. The column stores the **SHA-256 hex** of the link token (raw token only in email); migration `023` clears any pre-hash plaintext values.
---

## Tables

### `users`

Portal login accounts. Clients log in with `client_id` (not email).

| Column           | Type                        | Constraints                                  | Notes                          |
| ---------------- | --------------------------- | -------------------------------------------- | ------------------------------ |
| `id`             | INT UNSIGNED AUTO_INCREMENT | PRIMARY KEY                                  |                                |
| `client_id`      | VARCHAR(20)                 | UNIQUE, NULL                                 | Login identifier (e.g. `1817`) |
| `email`          | VARCHAR(180)                | UNIQUE, NOT NULL                             |                                |
| `password_hash`  | VARCHAR(255)                | NOT NULL                                     | bcrypt                         |
| `first_name`     | VARCHAR(100)                | NOT NULL DEFAULT ''                          |                                |
| `middle_name`    | VARCHAR(100)                | NULL                                         | Migration `007`                |
| `last_name`      | VARCHAR(100)                | NOT NULL DEFAULT ''                          |                                |
| `phone`          | VARCHAR(30)                 | NULL                                         |                                |
| `cc_last4`       | CHAR(4)                     | NULL                                         | Last 4 digits of active CC     |
| `cc_expiry`      | VARCHAR(7)                  | NULL                                         | Card expiry MM/YYYY            |
| `remember_token` | VARCHAR(255)                | NULL                                         | SHA-256 hex of cookie token    |
| `password_reset_token` | VARCHAR(64)           | NULL, KEY                                    | SHA-256 hex of reset link token |
| `password_reset_expires` | DATETIME            | NULL                                         | Token expiry                   |
| `registration_token` | CHAR(36)                | UNIQUE, NULL                                 | Invite UUID for `/register/{uuid}`; cleared on activate |
| `status`         | TINYINT(1)                  | NOT NULL DEFAULT 1                           | 1 = active, 0 = disabled       |
| `role`           | ENUM('client','admin')      | NOT NULL DEFAULT 'client'                    |                                |
| `last_login_at`  | DATETIME                    | NULL                                         |                                |
| `created_at`     | DATETIME                    | NOT NULL DEFAULT CURRENT_TIMESTAMP           |                                |
| `updated_at`     | DATETIME                    | NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE |                                |

**Indexes:** `uq_users_email (email)`, `idx_client_id (client_id)`

---

### `client_profiles`

One-to-one extended profile (CIF — Client Information File).

| Column          | Type            | Constraints                 | Notes |
| --------------- | --------------- | --------------------------- | ----- |
| `id`            | INT UNSIGNED AI | PRIMARY KEY                 |       |
| `user_id`       | INT UNSIGNED    | UNIQUE, FK → users(id)      |       |
| `case_number`   | VARCHAR(50)     | NULL                        |       |
| `attorney_name` | VARCHAR(150)    | NULL                        |       |
| `court_name`    | VARCHAR(200)    | NULL                        |       |
| `case_status`   | VARCHAR(100)    | NULL                        |       |
| `address`       | VARCHAR(255)    | NULL                        |       |
| `city`          | VARCHAR(100)    | NULL                        |       |
| `state`         | VARCHAR(50)     | NULL                        |       |
| `zip_code`      | VARCHAR(20)     | NULL                        |       |
| `ssn_last4`     | CHAR(4)         | NULL                        |       |
| `dob`           | DATE            | NULL                        |       |
| `notes`         | TEXT            | NULL                        |       |
| `created_at`    | DATETIME        | DEFAULT CURRENT_TIMESTAMP   |       |
| `updated_at`    | DATETIME        | ON UPDATE CURRENT_TIMESTAMP |       |

---

### `client_phones`

Multiple phones per user. Created in `003c`; `label` also ensured by idempotent `004`.

| Column       | Type            | Constraints                          |
| ------------ | --------------- | ------------------------------------ |
| `id`         | INT UNSIGNED AI | PRIMARY KEY                          |
| `user_id`    | INT UNSIGNED    | FK → users(id)                       |
| `type`       | VARCHAR(30)     | NOT NULL (alternative, home, cell…)  |
| `number`     | VARCHAR(30)     | NOT NULL                             |
| `extension`  | VARCHAR(10)     | NULL                                 |
| `label`      | VARCHAR(100)    | NULL (nickname for type = other)     |
| `is_main`    | TINYINT(1)      | DEFAULT 0                            |
| `is_old`     | TINYINT(1)      | DEFAULT 0 (inactive toggle)          |
| `created_at` | DATETIME        | DEFAULT NOW()                        |

---

### `client_emails`

Multiple email addresses per user (`003c`).

| Column       | Type            | Constraints                 |
| ------------ | --------------- | --------------------------- |
| `id`         | INT UNSIGNED AI | PRIMARY KEY                 |
| `user_id`    | INT UNSIGNED    | FK → users(id)              |
| `type`       | VARCHAR(30)     | NOT NULL (main/alternative) |
| `email`      | VARCHAR(180)    | NOT NULL                    |
| `is_main`    | TINYINT(1)      | DEFAULT 0                   |
| `created_at` | DATETIME        | DEFAULT NOW()               |

---

### `client_addresses`

Living / mail addresses (`003c`). Types used by portal: `living`, `mail`.

| Column           | Type            | Constraints      |
| ---------------- | --------------- | ---------------- |
| `id`             | INT UNSIGNED AI | PRIMARY KEY      |
| `user_id`        | INT UNSIGNED    | FK → users(id)   |
| `address_type`   | VARCHAR(30)     | NOT NULL         |
| `country`        | VARCHAR(100)    | NULL             |
| `province_state` | VARCHAR(100)    | NULL             |
| `city`           | VARCHAR(100)    | NULL             |
| `postal_code`    | VARCHAR(20)     | NULL             |
| `street`         | VARCHAR(255)    | NULL             |
| `unit`           | VARCHAR(50)     | NULL             |
| `move_in_date`   | DATE            | NULL             | Also ensured by idempotent `008` |
| `created_at`     | DATETIME        | DEFAULT NOW()    |
| `updated_at`     | DATETIME        | ON UPDATE NOW()  |

**Indexes:** `UNIQUE (user_id, address_type)`

---

### `user_settings`

Key-value store for per-user portal preferences.

| Column          | Type            | Constraints     |
| --------------- | --------------- | --------------- |
| `id`            | INT UNSIGNED AI | PRIMARY KEY     |
| `user_id`       | INT UNSIGNED    | FK → users(id)  |
| `setting_key`   | VARCHAR(100)    | NOT NULL        |
| `setting_value` | TEXT            | NULL            |
| `updated_at`    | DATETIME        | ON UPDATE NOW() |

**Indexes:** `UNIQUE (user_id, setting_key)`

**Known keys:** `dark_mode` (`0`/`1`), `sidebar_side` (`left`/`right`), `notify_email`,
`notify_payment`, `notify_status`, `notify_news`, `email_description` (shown on profile).

---

### `sessions`

Token-based session storage.

| Column       | Type         | Constraints    |
| ------------ | ------------ | -------------- |
| `id`         | VARCHAR(128) | PRIMARY KEY    |
| `user_id`    | INT UNSIGNED | FK → users(id) |
| `ip_address` | VARCHAR(45)  | NULL           |
| `user_agent` | VARCHAR(300) | NULL           |
| `expires_at` | DATETIME     | NOT NULL       |
| `created_at` | DATETIME     | DEFAULT NOW()  |

---

### `documents`

Client-uploaded documents, stored **outside webroot** at `UPLOAD_BASE_PATH`.

| Column         | Type                                                   | Constraints                | Notes                                                 |
| -------------- | ------------------------------------------------------ | -------------------------- | ----------------------------------------------------- |
| `id`           | INT UNSIGNED AI                                        | PRIMARY KEY                |                                                       |
| `user_id`      | INT UNSIGNED                                           | FK → users(id)             |                                                       |
| `service_type` | VARCHAR(30)                                            | NOT NULL                   | pardon, criminal-rehab, trp, nexus, expunging, waiver |
| `doc_key`      | VARCHAR(50)                                            | NOT NULL                   | Unique key per doc type (e.g. `pardon-rcmp`)          |
| `title`        | VARCHAR(255)                                           | NOT NULL                   | Display label                                         |
| `file_name`    | VARCHAR(255)                                           | NULL                       | Original upload filename                              |
| `file_path`    | VARCHAR(500)                                           | NULL                       | Full disk path to stored file                         |
| `file_size`    | INT UNSIGNED                                           | NULL                       | Bytes                                                 |
| `mime_type`    | VARCHAR(100)                                           | NULL                       |                                                       |
| `status`       | ENUM('pending','uploaded','approved','completed_fpws') | NOT NULL DEFAULT 'pending' |                                                       |
| `uploaded_at`  | DATETIME                                               | DEFAULT NOW()              |                                                       |

**Status values:**

- `pending` — Document not yet uploaded by client
- `uploaded` — Client uploaded the file, awaiting review
- `approved` — Admin approved the document
- `completed_fpws` — Completed by FPWS staff on client's behalf

**Indexes:** `idx_docs_user_id (user_id)`

---

### `service_costs`

Catalogue of all service types — both main services and extras.

| Column         | Type            | Constraints        | Notes                       |
| -------------- | --------------- | ------------------ | --------------------------- |
| `id`           | INT UNSIGNED AI | PRIMARY KEY        |                             |
| `service_type` | VARCHAR(30)     | NOT NULL           | Machine key                 |
| `label`        | VARCHAR(100)    | NOT NULL           | Human-readable label        |
| `total_cost`   | DECIMAL(10,2)   | NOT NULL DEFAULT 0 |                             |
| `is_extra`     | TINYINT(1)      | NOT NULL DEFAULT 0 | 0 = main service, 1 = extra |
| `created_at`   | DATETIME        | DEFAULT NOW()      |                             |

**Seed data — main services (is_extra = 0):**

| id  | service_type   | label                     | total_cost |
| --- | -------------- | ------------------------- | ---------- |
| 1   | pardon         | Pardon                    | 1200.00    |
| 2   | criminal-rehab | Criminal Rehabilitation   | 2000.00    |
| 3   | trp            | Temporary Resident Permit | 1500.00    |
| 4   | nexus          | NEXUS Card                | 500.00     |
| 5   | expunging      | Record Expunging          | 800.00     |
| 6   | waiver         | US Entry Waiver           | 1800.00    |

**Seed data — extra services (is_extra = 1):**

| id  | service_type | label       | total_cost |
| --- | ------------ | ----------- | ---------- |
| 8   | lprc         | LPRC        | 75.00      |
| 9   | fresh-start  | Fresh Start | 350.00     |
| 10  | mbf          | MBF         | 150.00     |

---

### `payment_plans`

Links a user to a service with a specific installment schedule.

| Column            | Type            | Constraints            | Notes                                |
| ----------------- | --------------- | ---------------------- | ------------------------------------ |
| `id`              | INT UNSIGNED AI | PRIMARY KEY            |                                      |
| `user_id`         | INT UNSIGNED    | FK → users(id)         |                                      |
| `service_cost_id` | INT UNSIGNED    | FK → service_costs(id) |                                      |
| `total_amount`    | DECIMAL(10,2)   | NOT NULL               | Copied from service_costs.total_cost |
| `installments`    | INT             | NOT NULL DEFAULT 1     | Number of monthly payments           |
| `created_at`      | DATETIME        | DEFAULT NOW()          |                                      |

**Notes:**

- Most services have **12 installments**.
- **TRP has 16 installments** (12 standard + 4 extra months).
- Monthly payment = `total_amount / installments`.

---

### `payments`

Individual payment installments. One row per service per month.

| Column               | Type                            | Constraints                | Notes                                        |
| -------------------- | ------------------------------- | -------------------------- | -------------------------------------------- |
| `id`                 | INT UNSIGNED AI                 | PRIMARY KEY                |                                              |
| `user_id`            | INT UNSIGNED                    | FK → users(id)             |                                              |
| `payment_plan_id`    | INT UNSIGNED                    | FK → payment_plans(id)     |                                              |
| `installment_number` | INT                             | NOT NULL DEFAULT 1         | 1-based within the plan                      |
| `amount`             | DECIMAL(10,2)                   | NOT NULL                   | Per-service monthly amount                   |
| `due_date`           | DATE                            | NOT NULL                   |                                              |
| `paid_date`          | DATE                            | NULL                       | NULL if not yet paid                         |
| `status`             | ENUM('paid','pending','missed') | NOT NULL DEFAULT 'pending' |                                              |
| `method`             | VARCHAR(50)                     | NULL                       | credit_card, debit, e_transfer, cash, cheque |
| `reference_number`   | VARCHAR(100)                    | NULL                       | Receipt / transaction ID                     |
| `notes`              | TEXT                            | NULL                       |                                              |
| `created_at`         | DATETIME                        | DEFAULT NOW()              |                                              |

**Display logic:** The PaymentsController **bundles** payments by `installment_number` + `due_date`
across all plans. Each "bundle" card on the Kanban board shows the combined total of all services
for that installment, with a service-by-service breakdown.

**Method display labels:**

| DB value    | Display label |
| ----------- | ------------- |
| credit_card | Credit Card   |
| debit       | Debit         |
| e_transfer  | e-Transfer    |
| cash        | Cash          |
| cheque      | Cheque        |

---

### `nsf_fees`

> **Deprecated:** The `nsf_fees` table is retained in the database schema but is **no longer
> loaded or displayed** in the portal UI. NSF fees are no longer issued. The table may still
> be used by admin-facing CRM tools.

NSF (Non-Sufficient Funds) fees attached to missed payments.

| Column       | Type            | Constraints            | Notes              |
| ------------ | --------------- | ---------------------- | ------------------ |
| `id`         | INT UNSIGNED AI | PRIMARY KEY            |                    |
| `payment_id` | INT UNSIGNED    | FK → payments(id)      | The missed payment |
| `amount`     | DECIMAL(10,2)   | NOT NULL DEFAULT 25.00 |                    |
| `created_at` | DATETIME        | DEFAULT NOW()          |                    |

---

### `user_extra_services`

Extra (add-on) services assigned to a user — shown in the "Extra" column of Payments Kanban.

| Column            | Type                                   | Constraints               | Notes |
| ----------------- | -------------------------------------- | ------------------------- | ----- |
| `id`              | INT UNSIGNED AI                        | PRIMARY KEY               |       |
| `user_id`         | INT UNSIGNED                           | FK → users(id)            |       |
| `service_cost_id` | INT UNSIGNED                           | FK → service_costs(id)    |       |
| `amount`          | DECIMAL(10,2)                          | NOT NULL                  |       |
| `status`          | ENUM('active','completed','cancelled') | NOT NULL DEFAULT 'active' |       |
| `notes`           | TEXT                                   | NULL                      |       |
| `created_at`      | DATETIME                               | DEFAULT NOW()             |       |

---

### `cif_responses`

Stores all CIF (Client Information Form) answers as a single JSON blob per user.
One row per user — answers are upserted on save.

| Column          | Type             | Constraints                                  | Notes                             |
| --------------- | ---------------- | -------------------------------------------- | --------------------------------- |
| `id`            | INT UNSIGNED AI  | PRIMARY KEY                                  |                                   |
| `user_id`       | INT UNSIGNED     | UNIQUE, FK → users(id) ON DELETE CASCADE     |                                   |
| `response_data` | JSON             | NOT NULL                                     | All answers keyed by question key |
| `progress`      | TINYINT UNSIGNED | NOT NULL DEFAULT 0                           | Percentage complete 0-100         |
| `completed_at`  | DATETIME         | NULL                                         | Set when progress reaches 100%    |
| `created_at`    | DATETIME         | NOT NULL DEFAULT CURRENT_TIMESTAMP           |                                   |
| `updated_at`    | DATETIME         | NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE |                                   |

**Notes:** `response_data` contains nested arrays for table-type questions
(`addresses`, `employment_history`, `offences`, `former_spouses`, …).
Progress is recalculated on every save from visible required fields.
Legacy keys may be migrated on read/write (see `CifTimeline::migrateResponseData`
and [`CIF_REFERENCE.md`](CIF_REFERENCE.md)).

---

### `rate_limit_buckets`

Persisted throttles for login (per IP), forgot-password (per IP), and pay (per user id). Migration `024`.

| Column         | Type          | Constraints        | Notes                          |
| -------------- | ------------- | ------------------ | ------------------------------ |
| `bucket_key`   | VARCHAR(191)  | PRIMARY KEY        | e.g. `login:1.2.3.4`           |
| `window_start` | INT UNSIGNED  | NOT NULL           | Unix timestamp of window start |
| `hit_count`    | INT UNSIGNED  | NOT NULL DEFAULT 0 | Hits in current window        |

---

### `cif_generated_pdfs`

Tracks each PDF form generated from CIF answers.

| Column         | Type             | Constraints            | Notes                               |
| -------------- | ---------------- | ---------------------- | ----------------------------------- |
| `id`           | INT UNSIGNED AI  | PRIMARY KEY            |                                     |
| `user_id`      | INT UNSIGNED     | FK → users(id)         |                                     |
| `form_type`    | VARCHAR(30)      | NOT NULL               | Service type: pardon, trp, waiver…  |
| `file_path`    | VARCHAR(500)     | NOT NULL               | Relative path from UPLOAD_BASE_PATH |
| `page_count`   | TINYINT UNSIGNED | NOT NULL DEFAULT 1     | Includes continuation pages         |
| `generated_at` | DATETIME         | NOT NULL DEFAULT NOW() |                                     |

**Indexes:** `idx_cif_pdf_user (user_id)`

---

## Migration Files

Run in order:

| File                              | What it does                                                                                                                                                                       |
| --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `001_create_users_table.sql`      | Creates `users` table                                                                                                                                                              |
| `002_create_clients_table.sql`    | Creates `client_profiles` table (FK → users)                                                                                                                                       |
| `003_create_settings_table.sql`   | Creates `documents`, `payments` (old schema), `user_settings`, `sessions`                                                                                                          |
| `003b_payments_system.sql`        | Adds `client_id` to users, `completed_fpws` to documents status, creates `service_costs`, `payment_plans`, `payments` (new), `nsf_fees`, `user_extra_services`                   |
| `003c_create_client_contact_tables.sql` | CREATE `client_phones`, `client_emails`, `client_addresses` (Profile columns)                                                                                               |
| `004_add_phone_label.sql`         | Idempotent ADD `label` on `client_phones`                                                                                                                                          |
| `005_services_page.sql`           | Creates `user_services`, `service_courts_police`, `service_email_history`, `service_sbc_records`, `service_conviction_records`, `service_nexus_records`                            |
| `006_nexus_codes.sql`             | Creates `service_nexus_codes` (backup codes for NEXUS service)                                                                                                                     |
| `007_add_middle_name.sql`         | Adds `middle_name` column to `users`                                                                                                                                               |
| `008_add_move_in_date.sql`        | Idempotent ADD `move_in_date` on `client_addresses`                                                                                                                                |
| `009_add_email_body.sql`          | Adds `body` column to `service_email_history`                                                                                                                                      |
| `010_add_rcmp_exp_date.sql`       | Adds `rcmp_record_exp_date` column to `user_services`                                                                                                                              |
| `011_add_email_read_at.sql`       | Adds `read_at` column to `service_email_history`                                                                                                                                   |
| `012_add_credit_card_columns.sql` | Adds `cc_last4`, `cc_expiry` columns to `users`                                                                                                                                    |
| `013_cleanup_sbc_records.sql`     | Drops unused columns from `service_sbc_records`, renames `bc_name` → `issuing_authority`                                                                                           |
| `014_cif_system.sql`              | Creates `cif_responses` (JSON blob per user) and `cif_generated_pdfs` (tracks generated form files)                                                                                |
| `015_drop_avatar_column.sql`      | Drops unused `avatar_path` and `avatar` columns from `users` table                                                                                                                 |
| `016_add_moneris_columns.sql`     | Moneris payment columns                                                                                            |
| `017_documents_catalog_remap.sql` | Remap/delete `documents.doc_key` for new Documents catalog                                                         |
| `018_add_courts_police_received.sql` | Adds `received` to `service_courts_police`                                                                      |
| `019_email_action_section.sql`    | Adds `action_section` ENUM to `service_email_history` for Portal CTA (profile/cif/documents)                       |
| `020_add_registration_token.sql`  | Idempotent ADD `users.registration_token` (UUID invite)                                                            |
| `021_clear_remember_tokens.sql`   | NULLs `users.remember_token` before SHA-256 cookie hashing (Phase 1)                                               |
| `022_add_users_auth_token_columns.sql` | Idempotent ADD `remember_token`, `password_reset_token`, `password_reset_expires`                              |
| `023_clear_plaintext_reset_tokens.sql` | NULLs `password_reset_token` / `password_reset_expires` before SHA-256 reset hashing (Wave 0)                 |
| `024_create_rate_limit_buckets.sql` | `rate_limit_buckets` for login / forgot / pay throttles (Wave 1) |

Apply via `php bin/migrate.php` (see `database/README.md`). Table `schema_migrations` records applied filenames.

### Jul 2026 portal notes (Phases 1–2)

**Documents catalog:** `DocumentCatalog::SERVICES`; shared `WAIVER_DOCS` for `waiver` + `waiver-renewal`; slots `waiver-granted-pardon`, `waiver-port-of-entry`. WR UI color `#1a5c2a`. Remap: migration `017`.

**Courts / Police Certificate:** `service_courts_police.received` TINYINT — green badge when 1 (migration `018`).

**Email CTA (CRM contract):** when CRM inserts `service_email_history`, set `action_section`:

| `action_section` | Client CTA |
|------------------|------------|
| `profile` | Change info in Profile |
| `cif` | Fill CIF |
| `documents` | Upload Documents |
| `NULL` | Custom Update — no CTA |

Portal reads the column via `ServiceEmailCta` (does not send email). Migration `019`.

> **Note:** The old `payments` table from `003_create_settings_table.sql` is replaced by
> `003b_payments_system.sql`. Lexical order guarantees settings → payments → contact tables (`003c`).

> **Note:** `service_conviction_records` and `service_nexus_codes` tables are retained in the
> database but no longer loaded or displayed in the portal UI. They may be used by
> admin-facing CRM tools.

---

## Seed Files

| File                      | What it does                                                                                               |
| ------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `seeds/initial_data.sql`  | Legacy seed (outdated — creates old-style users/clients)                                                   |
| `seeds/payments_seed.sql` | Seeds all 6 services' payment installments + extra services for user 1 (NSF fees rows retained but unused) |
| `seeds/services_seed.sql` | Seeds all 6 user_services + courts/police, email history, police certificates, NEXUS records for user 1    |

### Test User (current state)

| Field      | Value               |
| ---------- | ------------------- |
| id         | 1                   |
| client_id  | 1817                |
| email      | admin@portal.com    |
| password   | (bcrypt hash in DB) |
| first_name | (set via profile)   |
| last_name  | (set via profile)   |

Login at `/login` using **Client ID** = `1817` and the password.
