<?php
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$current = basename($_SERVER['PHP_SELF']);

page_header('Admin · ' . ucfirst(str_replace('.php', '', $current)), ['admin-page']);
?>
<section class="section" style="padding-top:16px;">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Admin panel</h2>
                <div class="sub" style="margin:0;">Manage available domains and users.</div>
            </div>
            <a class="btn btn-outline" href="<?php echo url('index.php'); ?>">View site &rarr;</a>
        </div>

        <div class="admin-grid">
            <aside class="admin-side">
                <a href="<?php echo url('admin/index.php'); ?>" class="<?php echo $current === 'index.php' ? 'active' : ''; ?>">Dashboard</a>
                <a href="<?php echo url('admin/domains.php'); ?>" class="<?php echo $current === 'domains.php' ? 'active' : ''; ?>">Domains &amp; import</a>
                <a href="<?php echo url('admin/users.php'); ?>" class="<?php echo $current === 'users.php' ? 'active' : ''; ?>">Users</a>
            </aside>
            <div><?php echo flash('admin'); ?>