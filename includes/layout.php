<?php
/**
 * Layout: header + footer
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

function page_header(string $title = '', array $bodyClass = []): void {
    $u     = cur_user();
    $admin = $u && (int)$u['is_admin'] === 1;
    $bc    = $bodyClass ? ' class="' . e(implode(' ', $bodyClass)) . '"' : '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ? e($title) . ' | ' : ''; ?><?php echo e(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body<?php echo $bc; ?>>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?php echo url('index.php'); ?>">
            <span class="brand-mark">free</span><span class="brand-mark-accent">dns</span>
        </a>
        <nav class="main-nav" id="mainNav">
            <a href="<?php echo url('index.php'); ?>">Domains</a>
            <?php if ($admin): ?><a href="<?php echo url('admin/index.php'); ?>">Admin</a><?php endif; ?>
            <?php if ($u): ?>
                <a href="<?php echo url('dashboard.php'); ?>">My Domains</a>
                <a class="nav-user" href="<?php echo url('dashboard.php'); ?>"><?php echo e($u['username']); ?></a>
                <a class="btn btn-sm btn-ghost" href="<?php echo url('logout.php'); ?>">Sign out</a>
            <?php else: ?>
                <a href="<?php echo url('login.php'); ?>">Sign in</a>
                <a class="btn btn-sm btn-primary" href="<?php echo url('register.php'); ?>">Create account</a>
            <?php endif; ?>
        </nav>
        <button class="nav-toggle" id="navToggle" aria-label="Menu">&#9776;</button>
    </div>
</header>
<main class="page">
    <?php
}

function page_footer(): void {
    ?>
</main>
<footer class="site-footer">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> <?php echo e(SITE_NAME); ?>. Free subdomain registration.</p>
    </div>
</footer>
<script src="<?php echo url('assets/js/app.js'); ?>"></script>
</body>
</html>
    <?php
}

function admin_header(string $title = ''): void {
    page_header($title, ['admin-page']);
}

function admin_footer(): void {
    page_footer();
}