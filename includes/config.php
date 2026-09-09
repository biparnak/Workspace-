<?php
/**
 * FreeDNS Panel - configuration
 * Edit the DB credentials to match your VPS / hosting.
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'freedns');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- App ----
define('APP_NAME', 'freedns');
define('BASE_URL', '');                // '' = auto-detect; or set like 'https://dns.example.com'
define('SITE_NAME', 'FreeDNS');
define('SESSION_NAME', 'freedns_session');

// ---- Security / limits ----
define('SUB_MAX_LENGTH', 63);          // max prefix length for a subdomain label
define('MIN_PASSWORD', 8);
define('CSRF_SECRET', 'change-this-to-a-long-random-string');