# Deployment Guide — FreeDNS Registration & Management Panel

A Namecheap-style subdomain registration + DNS management panel built with
**PHP 8**, **MySQL/MariaDB**, **HTML/CSS/JS**. No Composer dependencies.

## Requirements

- PHP 8.0+ with extensions: `pdo_mysql`, `session`
- MySQL 5.7+ or MariaDB 10.3+
- Any web server (Apache + `.htaccess`, or nginx)

## 1. Upload the files

Upload the whole project to your VPS web root, e.g. `/var/www/freedns` (Apache)
or `~/www/freedns` (shared hosting).

Examples:

```sh
scp -r ./freedns-main user@YOUR_VPS:/var/www/freedns
```

## 2. Create the database

```sh
mysql -u root -p < schema.sql
```

> This creates the `freedns` database, tables, a default **admin** account and a
> small starter set of domains.

If you prefer to import only a part:

```sh
mysql -u root -p -e "CREATE DATABASE freedns CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p freedns < schema.sql
```

## 3. Configure credentials

Edit `includes/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'freedns');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Optional: set a full URL if the app isn't at the domain root
// define('BASE_URL', 'https://dns.example.com');

// IMPORTANT: change the CSRF secret to a long random string
define('CSRF_SECRET', 'put-a-long-random-string-here');
```

## 4. First login

| Item         | Value          |
| ------------ | -------------- |
| URL          | `https://your-vps/admin/` |
| Username     | `admin`        |
| Password     | `Welcome@123`  |

**Change the admin password immediately** — fastest way is to edit it in the DB:

```sql
USE freedns;
UPDATE users
SET password_hash = '$2y$10$<your-new-hash>'
WHERE username = 'admin';
```

(Tip: generate a hash with `php -r "echo password_hash('mynewpass', PASSWORD_DEFAULT);"`)

## 5. Add your domains

Go to **Admin &rarr; Domains &amp; import**, paste your list one per line, and
press **Import**. You can feature, hide or delete any domain from the table.

## 6. Web server notes

### Apache
`.htaccess` is included and already blocks direct access to `includes/`,
`schema.sql`, and markdown files.

### nginx
Deny the sensitive paths in your server block:

```nginx
location ~ ^/(includes|schema\.sql)/? { deny all; }
```

Make sure PHP-FPM + `index.php` as `index` is configured.

## 7. DNS / nameserver note

This panel manages **records in the database**. To put records into your real
DynDNS/FreeDNS provider, point this panel's output at your authoritative
nameserver, or use the FreeDNS API to sync `dns_records`. The schema is cleanly
separated (`registrations` → `dns_records`) so a sync script can read it easily.

## Structure

```
freedns-main/
├── schema.sql            # Database schema + starter data
├── index.php             # Landpage: search + browse domains (Namecheap style)
├── checkout.php          # Register a subdomain under a chosen domain
├── register.php          # User sign-up
├── login.php / logout.php
├── dashboard.php         # My Domains + full DNS record management
├── admin/                # Admin panel (import domains, manage users)
├── includes/             # config, db, auth, functions, layout
└── assets/               # css + js
```

## Security defaults

- Password hashing via `password_hash()` / `password_verify()`
- All queries via PDO prepared statements
- CSRF token on every form
- Admin-only pages enforced server-side
- Subdomain availability enforced with a DB unique constraint