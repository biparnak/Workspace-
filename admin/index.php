<?php
require_once __DIR__ . '/_header.php';

$domainCount = (int)db()->query('SELECT COUNT(*) FROM domains')->fetchColumn();
$regCount    = (int)db()->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
$userCount   = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$recordCount = (int)db()->query('SELECT COUNT(*) FROM dns_records')->fetchColumn();
$activeRegs  = (int)db()->query('SELECT COUNT(*) FROM registrations WHERE active = 1')->fetchColumn();
$featuredCount = (int)db()->query('SELECT COUNT(*) FROM domains WHERE featured = 1')->fetchColumn();

$recent = db()->query(
    'SELECT r.id, r.subdomain, d.name AS domain, u.username, r.created_at
     FROM registrations r
     JOIN domains d ON d.id = r.domain_id
     JOIN users u   ON u.id  = r.user_id
     ORDER BY r.created_at DESC LIMIT 8')->fetchAll();

$serverType = get_setting('dns_server_type', 'none');
$serverInstalled = get_setting('dns_server_installed', '0');
$serverIP = get_server_ip();
$serverDomain = get_setting('dns_server_domain', '');
?>
        <div class="dash-stats">
            <div class="stat-card" style="border-left:4px solid var(--nc-teal);"><div class="stat-value"><?php echo $domainCount; ?></div><div class="stat-label">Available domains</div></div>
            <div class="stat-card" style="border-left:4px solid var(--nc-blue);"><div class="stat-value"><?php echo $regCount; ?></div><div class="stat-label">Registrations</div></div>
            <div class="stat-card" style="border-left:4px solid var(--nc-orange);"><div class="stat-value"><?php echo $activeRegs; ?></div><div class="stat-label">Active subdomains</div></div>
            <div class="stat-card" style="border-left:4px solid var(--nc-green);"><div class="stat-value"><?php echo $recordCount; ?></div><div class="stat-label">DNS records</div></div>
        </div>

        <div class="dash-stats" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:28px;">
            <div class="stat-card" style="border-left:4px solid var(--nc-navy);"><div class="stat-value" style="font-size:1.2rem;"><?php echo $userCount; ?></div><div class="stat-label">Users</div></div>
            <div class="stat-card" style="border-left:4px solid var(--nc-teal);"><div class="stat-value" style="font-size:1.2rem;"><?php echo $featuredCount; ?></div><div class="stat-label">Featured domains</div></div>
            <div class="stat-card" style="border-left:4px solid <?php echo $serverInstalled === '1' ? 'var(--nc-green)' : 'var(--nc-orange)'; ?>;">
                <div class="stat-value" style="font-size:1.2rem;">
                    <?php echo $serverType === 'none' ? 'Not set' : strtoupper($serverType); ?>
                    <?php if ($serverInstalled === '1'): ?><span style="color:var(--nc-green);font-size:.9rem;">&#10003;</span><?php endif; ?>
                </div>
                <div class="stat-label">DNS server</div>
            </div>
            <div class="stat-card" style="border-left:4px solid var(--nc-navy-3);"><div class="stat-value" style="font-size:1.2rem;word-break:break-all;"><?php echo e($serverDomain ?: '—'); ?></div><div class="stat-label">Server domain</div></div>
        </div>

        <div class="card wide" style="max-width:none;margin-bottom:24px;">
            <h3>Quick actions</h3>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
                <a href="<?php echo url('admin/domains.php'); ?>" class="btn btn-outline">+ Add / Import domains</a>
                <a href="<?php echo url('admin/dns-server.php'); ?>" class="btn btn-outline">&#128225; DNS Server setup</a>
                <a href="<?php echo url('admin/users.php'); ?>" class="btn btn-outline">Users</a>
                <?php if ($serverInstalled === '1'): ?>
                <form method="post" action="<?php echo url('admin/dns-server.php'); ?>" style="display:inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="sync_records">
                    <button class="btn btn-primary btn-sm" type="submit" style="padding:9px 20px;">&#8635; Sync records</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card wide" style="max-width:none;">
            <div class="section-head">
                <h3>Latest registrations</h3>
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