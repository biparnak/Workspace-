# Deployment Guide — FreeDNS Registration & Management Panel

A Namecheap-style subdomain registration + DNS management panel built with
**PHP 8**, **MySQL/MariaDB**, **HTML/CSS/JS**. No Composer dependencies.

## Requirements

- PHP 8.0+ with extensions: `pdo_mysql`, `session`
- MySQL 5.7+ or MariaDB 10.3+
- Any web server (Apache + `.htaccess`, or nginx)
- Linux VPS with root/sudo access **only if** you install the built-in
  Bind9/DNSMasq DNS server

## 1. Upload the files

Upload the whole project to your VPS web root, e.g. `/var/www/freedns` (Apache)
or `~/www/freedns` (shared hosting).

```sh
scp -r ./freedns-main user@YOUR_VPS:/var/www/freedns
```

## 2. Run the installer (recommended)

Open the installer in your browser:

```
https://your-vps/install.php
```

The wizard does everything for you:

1. **Requirements check** — verifies PHP, PDO MySQL and file permissions.
2. **Database setup** — creates the database and all tables automatically
   (enter your MySQL host/user/password once).
3. **Admin account** — you choose your **admin username**, email and password.
4. **Finish** — writes `includes/config.php` with your DB settings and a random
   CSRF secret, pre-loads **30 premium short domains** (`.com`, `.org`, `.co`,
   `.io`, `.net`) and blocks re-installation with a `.installed` lock file.

Afterwards open `admin/` and log in with the credentials you just created.

> **Security:** delete or rename `install.php` after first login. The
> `.htaccess` and lock file already block re-installation.

## 3. Manual setup (alternative)

```sh
mysql -u root -p < schema.sql
```

This creates the `freedns` database, tables, a default **admin** account
(username `admin`, password `Welcome@123` — change it!) and a starter + premium
domain set.

Then edit `includes/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'freedns');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('CSRF_SECRET', 'put-a-long-random-string-here');
```

## 4. First login

| Item         | Value                                      |
| ------------ | ------------------------------------------ |
| URL          | `https://your-vps/admin/`                  |
| Username     | the username you chose at install (/ admin) |
| Password     | the password you chose at install (/ Welcome@123) |

**Change the admin password immediately** after a manual install:

```sql
USE freedns;
UPDATE users
SET password_hash = '$2y$10$<your-new-hash>'
WHERE username = 'admin';
```

## 5. Add your domains

Go to **Admin &rarr; Domains &amp; import**, paste your list one per line
(e.g. `fr.to`, `us.to`), and press **Import**. You can feature, hide or delete
any domain from the table, and add your own anytime.

## 6. Install your own DNS server (Bind9 or DNSMasq)

Real DNS resolution for the subdomains you register. To use it:

1. Go to **Admin &rarr; DNS Server**.
2. Confirm the auto-detected server IP.
3. Enter your server/nameserver domain (e.g. `ns1.your-domain.com`).
4. Click **Install Bind9** or **Install DNSMasq**.

The panel runs the OS package install, writes the zone files for **all your
registered subdomains and their DNS records**, starts the service, and lets you
press **Sync Records Now** whenever records change.

At your domain registrar, set your nameservers to `ns1.your-domain.com` /
`ns2.your-domain.com` (both A records are generated automatically).

## 7. Web server notes

### Apache
`.htaccess` is included and blocks direct access to `includes/`, `schema.sql`,
markdown files, and locks `install.php` after setup.

### nginx
Deny the sensitive paths in your server block:

```nginx
location ~ ^/(includes|schema\.sql)/? { deny all; }
```

Make sure PHP-FPM + `index.php` as `index` is configured.

## 8. FreeDNS API integration

In **Admin &rarr; DNS Server &rarr; FreeDNS API Integration** save your
freedns.afraid.org username + API key. See the bundled
`FreeDNS_Afraid_Tutorial.docx` for a step-by-step walkthrough of creating the
FreeDNS account, getting the API key, and importing public domains.

## Structure

```
freedns-main/
├── install.php           # Setup wizard (db + admin account + config)
├── schema.sql            # Database schema + starter/premium data
├── index.php             # Landpage: search + browse domains (Namecheap style)
├── checkout.php          # Register a subdomain under a chosen domain
├── register.php          # User sign-up
├── login.php / logout.php
├── dashboard.php         # My Domains + full DNS record management
├── admin/                # Admin panel (domains, users, DNS server installer)
├── includes/             # config, db, auth, functions, layout, settings helpers
├── assets/               # css + js
└── FreeDNS_Afraid_Tutorial.docx  # Step-by-step FreeDNS connection guide
```

## Security defaults

- Password hashing via `password_hash()` / `password_verify()`
- All queries via PDO prepared statements
- CSRF token on every form
- Admin-only pages enforced server-side
- Subdomain availability enforced with a DB unique constraint
- Installer generates a random `CSRF_SECRET` and locks itself after setup