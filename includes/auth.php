<?php
/**
 * Authentication & session helpers
 */
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    if (!empty(SESSION_NAME)) {
        session_name(SESSION_NAME);
    }
    session_start();
}

function cur_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    $st = db()->prepare('SELECT id, username, email, is_admin, created_at FROM users WHERE id = ?');
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
    if (!$u) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $u;
}

function logged_in(): bool {
    return cur_user() !== null;
}

function is_admin(): bool {
    $u = cur_user();
    return $u && (int)$u['is_admin'] === 1;
}

function require_login(): void {
    if (!logged_in()) {
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        redirect('login.php');
    }
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
}

/** Verify a user by username/email + password. */
function authenticate(string $login, string $password): string|bool {
    $st = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $st->execute([$login, $login]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        login_user($u);
        return (int)$u['is_admin'] === 1 ? 'admin' : 'user';
    }
    return false;
}