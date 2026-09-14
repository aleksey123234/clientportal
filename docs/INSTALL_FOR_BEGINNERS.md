# Install for Beginners (Windows)

Zero-experience checklist to run the Client Portal on a Windows PC.

If you already know PHP / Apache / MySQL, use the shorter [`SETUP_GUIDE.md`](SETUP_GUIDE.md) instead.

---

## What you are installing

| Piece | Role |
| ----- | ---- |
| **PHP 7.4** | Runs the portal code |
| **MySQL 8** | Stores users, payments, CIF, documents metadata |
| **Apache** | Web server — serves `http://localhost` |
| **Composer** | Downloads PHP libraries into `vendor/` |
| **Git** | Downloads this project from GitHub |

**Important:** Use **PHP 7.4**, not PHP 8.x.

---

## 1. Install the tools

Install each one. Accept defaults unless noted.

1. **Git** — https://git-scm.com  
   During setup, leave “Git from the command line” enabled.

2. **PHP 7.4 (Thread Safe, VS16 x64 ZIP)** — https://windows.php.net/download  
   - Unzip to a folder you will remember, e.g. `C:\php74`  
   - Copy `php.ini-development` to `php.ini` in that same folder  
   - Add `C:\php74` to your Windows **PATH** (Search → “Environment Variables” → Path → New)

3. **MySQL 8.0** — https://dev.mysql.com/downloads/installer/  
   - Choose “Developer Default” or at least Server + Workbench  
   - Set a root password and **write it down** — you need it in `.env`

4. **Composer** — https://getcomposer.org  
   Installer will ask for `php.exe` — point it at `C:\php74\php.exe`

5. **Apache 2.4 for Windows** (e.g. Apache Lounge)  
   Not included in this git repo. Install locally; you will point it at `client-portal\public` in step 8.

Open a **new** PowerShell window and check:

```powershell
php -v          # should show 7.4.x
composer -V
git --version
mysql --version
```

If `php` or `composer` is “not recognized”, PATH is wrong — fix that before continuing.

---

## 2. Turn on PHP extensions

Edit `C:\php74\php.ini` (or wherever your `php.ini` is) in Notepad.

Find these lines (they may start with `;` — remove the `;` to enable):

```ini
extension=pdo_mysql
extension=fileinfo
extension=openssl
extension=mbstring
extension=curl
```

Also set:

```ini
upload_max_filesize = 20M
post_max_size = 25M
```

Save the file. Confirm:

```powershell
php -m
```

You must see `pdo_mysql`, `fileinfo`, `openssl`, `mbstring`, and `curl` in the list.

---

## 3. Download the project and libraries

```powershell
cd C:\Users\YOUR_NAME\Documents
git clone https://github.com/Rimoker/ClientsPortal.git
cd ClientsPortal\client-portal
composer install
```

Wait until Composer finishes without errors.

---

## 4. Create the config file (`.env`)

```powershell
copy .env.example .env
notepad .env
```

Change at least:

```ini
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mysql_clients_portal
DB_USERNAME=root
DB_PASSWORD=YOUR_MYSQL_ROOT_PASSWORD

UPLOAD_BASE_PATH=C:\Users\YOUR_NAME\Downloads\portal-docs
```

Save and close.  
`UPLOAD_BASE_PATH` must be a real folder **outside** the web site folder (you create it in step 9).

---

## 5. Create the empty database

In MySQL Workbench or:

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS mysql_clients_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Enter your MySQL password when asked.

---

## 6. Put data into the database (pick ONE path)

### Path A — Empty database (fresh install)

Builds tables from migration files in the repo (no client data):

```powershell
cd C:\Users\YOUR_NAME\Documents\ClientsPortal\client-portal
php bin\migrate.php
php bin\migrate.php --status
```

Optional test user (replace the hash — see [`SETUP_GUIDE.md`](SETUP_GUIDE.md) §7):

```sql
USE mysql_clients_portal;
INSERT INTO users (client_id, first_name, last_name, email, password_hash, status, created_at, updated_at)
VALUES ('1817', 'Test', 'User', 'admin@portal.com', 'BCRYPT_HASH_HERE', 1, NOW(), NOW());
```

Generate a hash:

```powershell
php -r "echo password_hash('your-password', PASSWORD_BCRYPT);"
```

Login later with Client ID `1817` and that password.

### Path B — Restore a full data dump (hybrid)

Use this if you have a backup folder of table dumps (example path):

`C:\Users\Lev\Documents\dumps\MyPortalDump20260804`

That folder is **not** in GitHub. Keep the folder and the `.rar` backed up on disk/cloud, or you cannot restore live data later.

1. Create the empty database (step 5) if it does not exist.
2. Import every `mysql_clients_portal_*.sql` file:

```powershell
$dumpDir = "C:\Users\Lev\Documents\dumps\MyPortalDump20260804"
$db = "mysql_clients_portal"
$user = "root"
# You will be prompted for the password for each file, or put -pYOURPASSWORD (no space)

Get-ChildItem $dumpDir -Filter "mysql_clients_portal_*.sql" | Sort-Object Name | ForEach-Object {
    Write-Host "Importing $($_.Name)..."
    Get-Content $_.FullName -Raw | & mysql -u $user -p $db
}
```

Or in **MySQL Workbench**: Server → Data Import → Import from Self-Contained File (or Import from Dump Project Folder) → select the dump folder → Start Import.

3. Check migrations match the dump:

```powershell
cd C:\Users\YOUR_NAME\Documents\ClientsPortal\client-portal
php bin\migrate.php --status
```

If everything already shows applied, you are done. If the tool suggests baseline for an existing DB, see [`database/README.md`](../database/README.md) (`--baseline`) — only for special cases.

---

## 7. Point Apache at the portal

Edit Apache `httpd.conf` (path depends on your Apache install).

Set DocumentRoot to the **`public`** folder only:

```apache
DocumentRoot "C:/Users/YOUR_NAME/Documents/ClientsPortal/client-portal/public"
<Directory "C:/Users/YOUR_NAME/Documents/ClientsPortal/client-portal/public">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

LoadModule rewrite_module modules/mod_rewrite.so

ScriptAlias /php-cgi/ "C:/php74/"
Action application/x-httpd-php "/php-cgi/php-cgi.exe"
AddHandler application/x-httpd-php .php
```

Adjust `C:/php74/` if your PHP folder is different.  
More detail: [`SETUP_GUIDE.md`](SETUP_GUIDE.md) §9–10.

Confirm `client-portal\public\.htaccess` exists (it ships with the repo).

---

## 8. Create the uploads folder

```powershell
mkdir "C:\Users\YOUR_NAME\Downloads\portal-docs"
```

This must match `UPLOAD_BASE_PATH` in `.env`.

---

## 9. Start MySQL and Apache

```powershell
net start MySQL80
# Start Apache — either:
#   net start Apache2.4
# or run httpd.exe from your Apache bin folder
```

---

## 10. Open the site

1. Browser → `http://localhost/login`
2. Log in with a Client ID + password from Path A (test user) or Path B (real dump users)

You should see the Dashboard.

---

## If it breaks (FAQ)

| Symptom | Likely fix |
| ------- | ---------- |
| Blank white page | Apache not running PHP — check `AddHandler` / `php-cgi.exe` path; check Apache error log |
| “could not find driver” / DB connection error | Wrong `DB_*` in `.env`, or `pdo_mysql` not enabled; MySQL not started |
| Document upload returns 500 | Enable `extension=fileinfo` in `php.ini` |
| CSS/JS missing or 404 | DocumentRoot must be `...\client-portal\public`, not the repo root |
| `composer` / `php` not found | Re-open PowerShell after fixing PATH |
| Dump import errors | Database must exist first; import all `mysql_clients_portal_*.sql` files |

Still stuck? Read [`SETUP_GUIDE.md`](SETUP_GUIDE.md) for the full technical guide.
