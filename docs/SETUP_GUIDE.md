# Setup Guide

Step-by-step instructions to get the Client Portal running on a fresh Windows machine.

> **New to PHP / Apache / MySQL?** Start with [`INSTALL_FOR_BEGINNERS.md`](INSTALL_FOR_BEGINNERS.md), then return here for details.

> Last updated: 2026-07-30

---

## Prerequisites

| Software     | Tested Version | Download                                   |
| ------------ | -------------- | ------------------------------------------ |
| Apache httpd | 2.4.x Win64    | Install locally (not versioned in this repo) |
| PHP          | **7.4.x** CGI  | https://windows.php.net/download           |
| MySQL        | 8.0.x          | https://dev.mysql.com/downloads/installer/ |
| Composer     | 2.x            | https://getcomposer.org                    |
| Git          | any            | https://git-scm.com                        |

> **Target runtime: PHP 7.4.** Portal `composer.json` requires `>=7.4` and stays CRM-aligned (peer CRM is PHP 7.4 / mysqli). Do **not** assume PHP 8 syntax. PHP 8 bump is deferred. See [`INTEGRATION_CRM.md`](INTEGRATION_CRM.md).
>
> **Apache:** The `Apache24/` folder is a local runtime only — it is gitignored. Download Apache for Windows separately and point `DocumentRoot` at `client-portal/public` (see §9). CGI / `AddHandler` style is fine for local and shared-host class setups; **php-fpm** can wait for Phase 9 ops.

### Frontend CDN (pinned + SRI)

Layouts load Bootstrap **5.3.3**, Bootstrap Icons **1.11.3**, and Flatpickr **4.6.13** from jsDelivr with Subresource Integrity hashes in [`config/cdn.php`](../config/cdn.php). Local CSS/JS use `App\Support\Asset::url()` (`?v=filemtime`) so deploys bust browser cache.

CDN access is required this phase (no vendored Bootstrap copy). Pinning + SRI reduces supply-chain risk. If jsDelivr is blocked, host the same versioned files yourself and update `config/cdn.php` URLs/hashes.

---

## 1. Clone the Repository

```bash
git clone https://github.com/Rimoker/ClientsPortal.git
cd ClientsPortal/client-portal
```

---

## 2. Install PHP Dependencies

```bash
composer install
```

This installs:

- `monolog/monolog` — structured logs (`storage/logs/`)
- `vlucas/phpdotenv` — reads `.env` file
- `phpmailer/phpmailer` — sends emails (password reset, notifications)
- `setasign/fpdi` + `setasign/fpdf` — CIF PDF fill

Prod-minded install (no PHPUnit): `composer install --no-dev`.

### Tests & lint (Phase 7)

```bash
vendor/bin/phpunit
php bin/lint-php.php
```

Optional HTTP smoke (Apache must be up):

```bash
# PowerShell
$env:SMOKE_BASE_URL = "http://localhost"
php bin/smoke.php
```

Or open [`docs/http/smoke.http`](http/smoke.http). CI runs validate + lint + phpunit only (no MySQL). Local migrate check: `php bin/migrate.php --status`.

---

## 3. Configure PHP (`php.ini`)

Ensure the following settings in your `php.ini` (e.g. `C:\Program Files (x86)\php\php.ini`):

```ini
; Required extensions
extension=pdo_mysql
extension=fileinfo        ; ← Required for document MIME type detection
extension=openssl
extension=mbstring

; Upload limits (documents can be up to 20 MB)
upload_max_filesize = 20M
post_max_size = 25M
```

**Important:** The `fileinfo` extension must be enabled or document uploads will fail
with a 500 error.

---

## 4. Create the `.env` File

In `client-portal/` (project root), copy `.env.example` to `.env` and edit:

```ini
APP_NAME=ClientPortal
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=America/Toronto

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mysql_clients_portal
DB_USERNAME=root
DB_PASSWORD=YOUR_PASSWORD_HERE

MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@fpws.ca
MAIL_FROM_NAME=ClientPortal

UPLOAD_BASE_PATH=C:\Users\YourName\Downloads\portal-docs

MONERIS_STORE_ID=store5
MONERIS_API_TOKEN=yesguy
MONERIS_TEST_MODE=true

LOG_LEVEL=
```

**Key variables:**

| Variable           | Purpose                                              |
| ------------------ | ---------------------------------------------------- |
| `DB_*`             | Portal MySQL (`mysql_clients_portal` — **not** CRM `crmdevpp_crmdevd`) |
| `MAIL_*`           | SMTP settings for PHPMailer (password reset emails)  |
| `UPLOAD_BASE_PATH` | Absolute path for document storage (outside webroot) |
| `MONERIS_*`        | Payment gateway (sandbox placeholders in example)    |
| `LOG_LEVEL`        | Monolog level; empty → debug (local) / warning (else) |

---

## 5. Create the Database

```sql
CREATE DATABASE IF NOT EXISTS mysql_clients_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## 6. Run Migrations

Preferred (applies **all** pending files in one shot):

```powershell
cd client-portal
php bin/migrate.php
php bin/migrate.php --status
```

Existing DB that already has tables: run `php bin/migrate.php --baseline` once, then `php bin/migrate.php` for new files only.

See [`database/README.md`](../database/README.md) for order notes (`003` → `003b` → `003c`, auth columns in `022`).

Manual `mysql` piping is still possible but not recommended.

---

## 7. Seed a Test User

```sql
USE mysql_clients_portal;

-- Create the test user (generate hash: php -r "echo password_hash('your-password', PASSWORD_BCRYPT);")
INSERT INTO users (client_id, first_name, last_name, email, password_hash, status, created_at, updated_at)
VALUES ('1817', 'Test', 'User', 'admin@portal.com', 'BCRYPT_HASH_HERE', 1, NOW(), NOW());
```

Login at `/login` with **Client ID** = `1817` and the password you hashed.

---

## 8. Seed Payment Data (Optional)

To populate the payments system with demo data for all 6 services:

```powershell
Get-Content database\seeds\payments_seed.sql | & $mysql -u root -pYOUR_PASSWORD mysql_clients_portal
```

This creates:

- 6 payment plans (Pardon, Criminal Rehab, TRP, NEXUS, Expunging, Waiver)
- Monthly installments (12 per service, 16 for TRP)
- Sample paid/missed/pending payment records
- NSF fees on missed payments
- 3 extra services (LPRC, Fresh Start, MBF)

**Prerequisites:** The `service_costs` table must already contain the seed data from
`003_payments_system.sql`. If service_costs is empty, insert them first:

```sql
INSERT INTO service_costs (service_type, label, total_cost, is_extra) VALUES
('pardon',         'Pardon',                      1200.00, 0),
('criminal-rehab', 'Criminal Rehabilitation',     2000.00, 0),
('trp',            'Temporary Resident Permit',   1500.00, 0),
('nexus',          'NEXUS Card',                   500.00, 0),
('expunging',      'Record Expunging',             800.00, 0),
('waiver',         'US Entry Waiver',             1800.00, 0),
('lprc',           'LPRC',                          75.00, 1),
('fresh-start',    'Fresh Start',                  350.00, 1),
('mbf',            'MBF',                          150.00, 1);
```

---

## 9. Configure Apache

Install Apache 2.4 locally (not shipped in this repository). Edit your `httpd.conf` — set DocumentRoot to `client-portal/public`:

```apache
DocumentRoot "C:/path/to/ClientsPortal/client-portal/public"
<Directory "C:/path/to/ClientsPortal/client-portal/public">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

LoadModule rewrite_module modules/mod_rewrite.so

ScriptAlias /php-cgi/ "C:/Program Files (x86)/php/"
Action application/x-httpd-php "/php-cgi/php-cgi.exe"
AddHandler application/x-httpd-php .php
```

### Virtual Host Alternative

Add to `Apache24/conf/extra/httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName portal.local
    DocumentRoot "C:/path/to/ClientsPortal/client-portal/public"
    <Directory "C:/path/to/ClientsPortal/client-portal/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add `127.0.0.1 portal.local` to `C:\Windows\System32\drivers\etc\hosts`.

---

## 10. Verify `.htaccess`

`public/.htaccess` should contain:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

---

## 11. Create Required Directories

```powershell
# Document uploads (outside webroot — must match UPLOAD_BASE_PATH in .env)
mkdir "C:\Users\YourName\Downloads\portal-docs"
```

Ensure Apache/PHP has **write permission** to the upload directory.

---

## 12. Start Services

```powershell
# Start MySQL (if installed as Windows service)
net start MySQL80

# Start Apache from your local install, e.g.:
httpd.exe
# or: net start Apache2.4
```

---

## 13. Test

1. Open `http://localhost/login`
2. Enter Client ID: `1817`
3. Enter the password you hashed in step 7
4. You should see the Dashboard

### Quick Feature Verification

| Page      | URL          | What to check                                        |
| --------- | ------------ | ---------------------------------------------------- |
| Dashboard | `/dashboard` | Landing page loads                                   |
| Profile   | `/profile`   | Personal info form, phone/email/address modals       |
| Documents | `/documents` | Per-service tabs, upload/download, status badges     |
| Payments  | `/payments`  | 4-column Kanban, service breakdown cards, NSF badges |
| FAQ       | `/faq`       | 12 filter pills, search, accordions                  |
| Settings  | `/settings`  | Dark mode toggle, sidebar side, password change      |

---

## Troubleshooting

| Issue                         | Fix                                                             |
| ----------------------------- | --------------------------------------------------------------- |
| 500 error                     | Check `storage/logs/app-*.log` and Apache `logs/error.log` |
| Class not found               | Run `composer install`                                          |
| DB connection refused         | Verify `.env` credentials, check MySQL is running               |
| mod_rewrite not working       | Ensure `AllowOverride All` + `mod_rewrite` loaded               |
| Document upload 500           | Enable `extension=fileinfo` in `php.ini`                        |
| Document upload too large     | Set `upload_max_filesize=20M`, `post_max_size=25M` in `php.ini` |
| Date shows mm/dd/yyyy         | Clear browser cache — dates use d/m/Y                           |
| Phone label not saving        | Run migration `004_add_phone_label.sql`                         |
| Payments page empty           | Run `003_payments_system.sql` + seed `service_costs` data       |
| Login says "Invalid"          | Login uses **Client ID** (e.g. `1817`), not email               |
| PowerShell `<` redirect error | Use `Get-Content file.sql \| mysql` piping instead              |
| `match()` error               | PHP 7.4 does not support `match()` — use `switch/case`          |
