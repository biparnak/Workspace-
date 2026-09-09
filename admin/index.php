<?php
require_once __DIR__ . '/_header.php';

$domainCount = (int)db()->query('SELECT COUNT(*) FROM domains')->fetchColumn();
$regCount    = (int)db()->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
$userCount   = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$recordCount = (int)db()->query('SELECT COUNT(*) FROM dns_records')->fetchColumn();

$recent = db()->query(
    'SELECT r.id, r.subdomain, d.name AS domain, u.username, r.created_at
     FROM registrations r
     JOIN domains d ON d.id = r.domain_id
     JOIN users u   ON u.id  = r.user_id
     ORDER BY r.created_at DESC LIMIT 8')->fetchAll();
?>
        <div class="dash-stats">
            <div class="stat-card"><div class="stat-value"><?php echo $domainCount; ?></div><div class="stat-label">Available domains</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $regCount; ?></div><div class="stat-label">Registrations</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $userCount; ?></div><div class="stat-label">Users</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $recordCount; ?></div><div class="stat-label">DNS records</div></div>
        </div>

        <div class="card wide" style="max-width:none;">
            <div class="section-head">
                <h2>Latest registrations</h2>
                <a href="<?php echo url('admin/domains.php'); ?>">Manage domains &rarr;</a>
            </div>
            <div class="table-wrap" style="box-shadow:none;">
                <table class="table">
                    <thead><tr><th>User</th><th>Subdomain</th><th>Registered</th></tr></thead>
                    <tbody>
                    <?php if (!$recent): ?>
                        <tr><td colspan="3" style="text-align:center;color:var(--nc-muted);">No registrations yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><?php echo e($r['username']); ?></td>
                            <td class="mono"><?php echo e($r['subdomain'] . '.' . $r['domain']); ?></td>
                            <td><?php echo e(date('M j, Y g:i A', strtotime($r['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php require_once __DIR__ . '/_footer.php';