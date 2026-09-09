<?php
/**
 * Shared helpers
 */
require_once __DIR__ . '/config.php';

function base_url(): string {
    $base = BASE_URL;
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Anchor the base to the app root (the folder containing includes/),
        // so it stays correct regardless of which page (public/ or admin/) runs.
        $appRoot = realpath(__DIR__ . '/..');
        $docRoot = is_dir($appRoot) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '/')) : null;
        $rel     = '';
        if ($docRoot !== null && $appRoot !== false && str_starts_with(str_replace('\\', '/', $appRoot), $docRoot)) {
            $rel = rtrim(substr(str_replace('\\', '/', $appRoot), strlen($docRoot)), '/');
        }
        $base = "$scheme://$host$rel";
    }
    return rtrim($base, '/');
}

function url(string $path = ''): string {
    return base_url() . '/' . ltrim($path, '/');
}

function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $msg = null, string $kind = 'success'): ?string {
    if ($msg !== null) {
        $_SESSION['flash'][$key] = ['msg' => $msg, 'kind' => $kind];
        return null;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $f = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return '<div class="alert alert-' . e($f['kind']) . '">' . e($f['msg']) . '</div>';
    }
    return null;
}

function csrf_field(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function csrf_verify(): bool {
    return isset($_SESSION['csrf'], $_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function require_csrf(): void {
    if (!csrf_verify()) {
        http_response_code(403);
        exit('Invalid security token.');
    }
}

/** Validate a subdomain label: lowercase letters, digits, hyphens. */
function valid_subdomain(string $s): bool {
    if ($s === '' || strlen($s) > SUB_MAX_LENGTH) return false;
    if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $s)) return false;
    if (str_ends_with($s, '-')) return false;
    return true;
}

function record_types(): array {
    return ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS'];
}

/** Full hostname for a registration, e.g. "mysite.fr.to" */
function fqdn(array $reg, array $domain): string {
    $prefix = ($reg['subdomain'] === '@') ? '' : $reg['subdomain'] . '.';
    return $prefix . $domain['name'];
}

/** Get a setting from the settings table */
function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    try {
        $st = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $st->execute([$key]);
        $row = $st->fetch();
        $cache[$key] = $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

/** Set a setting in the settings table */
function set_setting(string $key, string $value): void {
    $st = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
    $st->execute([$key, $value, $value]);
}

/** Get server IP address */
function get_server_ip(): string {
    $ip = get_setting('dns_server_ip', '');
    if ($ip !== '') return $ip;
    $ip = @file_get_contents('https://api.ipify.org?format=text');
    if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    return '127.0.0.1';
}

/** Check if a command exists on the system */
function command_exists(string $cmd): bool {
    $check = (PHP_OS_FAMILY === 'Windows') ? "where $cmd" : "which $cmd";
    exec($check . ' 2>/dev/null', $output, $returnCode);
    return $returnCode === 0;
}