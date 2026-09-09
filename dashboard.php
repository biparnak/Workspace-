<?php
require_once __DIR__ . '/includes/layout.php';

require_login();
$user = cur_user();

// ---- Subdomain registration actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_registration') {
        $id = (int)($_POST['id'] ?? 0);
        $st = db()->prepare('DELETE FROM registrations WHERE id = ? AND user_id = ?');
        $st->execute([$id, $user['id']]);
        flash('dns', 'Subdomain deleted.', 'info');
        redirect('dashboard.php');
    }

    if ($action === 'add_record' || $action === 'edit_record') {
        $regId   = (int)($_POST['registration_id'] ?? 0);
        $type    = strtoupper(trim($_POST['type'] ?? ''));
        $name    = trim($_POST['name'] ?? '@');
        $value   = trim($_POST['value'] ?? '');
        $prio    = (int)($_POST['priority'] ?? 0);
        $ttl     = max(60, min(86400, (int)($_POST['ttl'] ?? 3600)));

        // Ownership check
        $st = db()->prepare('SELECT id FROM registrations WHERE id = ? AND user_id = ?');
        $st->execute([$regId, $user['id']]);
        if (!$st->fetch()) {
            flash('dns', 'Invalid registration.', 'error');
            redirect('dashboard.php');
        }

        if (!in_array($type, record_types())) {
            flash('dns', 'Invalid record type.', 'error');
        } elseif ($type === 'MX' && ($prio < 1 || $prio > 65535)) {
            flash('dns', 'MX records require a priority between 1 and 65535.', 'error');
        } else {
            if ($action === 'add_record') {
                $st = db()->prepare('INSERT INTO dns_records (registration_id, type, name, value, priority, ttl) VALUES (?,?,?,?,?,?)');
                $st->execute([$regId, $type, $name ?: '@', $value, $prio, $ttl]);
            } else {
                $rid = (int)($_POST['record_id'] ?? 0);
                $st = db()->prepare('UPDATE dns_records SET type=?, name=?, value=?, priority=?, ttl=? WHERE id=? AND registration_id=?');
                $st->execute([$type, $name ?: '@', $value, $prio, $ttl, $rid, $regId]);
            }
            flash('dns', 'DNS record saved.');
        }
        redirect('dashboard.php?id=' . $regId);
    }

    if ($action === 'delete_record') {
        $id = (int)($_POST['record_id'] ?? 0);
        $st = db()->prepare(
            'DELETE r FROM dns_records r
             JOIN registrations reg ON reg.id = r.registration_id
             WHERE r.id = ? AND reg.user_id = ?');
        $st->execute([$id, $user['id']]);
        flash('dns', 'DNS record deleted.', 'info');
        redirect('dashboard.php?id=' . (int)($_POST['registration_id'] ?? 0));
    }

    if ($action === 'toggle_registration') {
        $id   = (int)($_POST['id'] ?? 0);
        $state= (int)($_POST['state'] ?? 0);
        $st = db()->prepare('UPDATE registrations SET active = ? WHERE id = ? AND user_id = ?');
        $st->execute([$state ? 1 : 0, $id, $user['id']]);
        flash('dns', 'Subdomain status updated.', 'info');
        redirect('dashboard.php');
    }
}

// ---- Data ----
$stats   = db()->prepare('SELECT COUNT(*) n, SUM(active) a FROM registrations WHERE user_id = ?');
$stats->execute([$user['id']]);
$totals  = $stats->fetch();
$regs    = db()->prepare(
    'SELECT r.*, d.name AS domain FROM registrations r
     JOIN domains d ON d.id = r.domain_id WHERE r.user_id = ? ORDER BY r.created_at DESC');
$regs->execute([$user['id']]);
$registrations = $regs->fetchAll();

$activeId = (int)($_GET['id'] ?? ($registrations[0]['id'] ?? 0));
$activeReg = null;
$records = [];
foreach ($registrations as $r) {
    if ((int)$r['id'] === $activeId) { $activeReg = $r; break; }
}
if ($activeReg) {
    $st = db()->prepare('SELECT * FROM dns_records WHERE registration_id = ? ORDER BY FIELD(type,"A","AAAA","CNAME","MX","TXT","NS"), id');
    $st->execute([$activeReg['id']]);
    $records = $st->fetchAll();
}

page_header('My Domains', ['dashboard']);
?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>My Domains</h2>
                <div class="sub" style="margin:0;">Manage your registered subdomains and their DNS records.</div>
            </div>
            <a class="btn btn-orange" href="<?php echo url('index.php'); ?>">+ Register a subdomain</a>
        </div>

        <?php echo flash('welcome'); ?>
        <?php echo flash('dns'); ?>

        <div class="dash-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo (int)$totals['n'] ?? 0; ?></div>
                <div class="stat-label">Total subdomains</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo (int)($totals['a'] ?? 0); ?></div>
                <div class="stat-label">Active</div>
            </div>
<?php if ($activeReg): ?>
            <div class="stat-card">
                <div class="stat-value" style="font-size:1.05rem;"><?php echo e(fqdn($activeReg, ['name' => $activeReg['domain']])); ?></div>
                <div class="stat-label">Selected subdomain</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($records); ?></div>
                <div class="stat-label">DNS records</div>
            </div>
<?php endif; ?>
        </div>

        <?php if (!$registrations): ?>
            <div class="empty-state card wide" style="max-width:560px;margin:0 auto;">
                <div class="icon">&#127873;</div>
                <h3>No subdomains yet</h3>
                <p>Register your first free subdomain — it takes less than a minute.</p>
                <a class="btn btn-primary" href="<?php echo url('index.php'); ?>">Browse domains</a>
            </div>
        <?php else: ?>

        <div class="admin-grid" style="grid-template-columns:340px 1fr;">

            <!-- registrations list -->
            <div class="admin-side" style="display:flex;flex-direction:column;gap:8px;">
                <div style="font-size:.78rem;color:var(--nc-muted);text-transform:uppercase;letter-spacing:.5px;padding:6px 10px;">My subdomains</div>
                <?php foreach ($registrations as $r): ?>
                    <a href="<?php echo url('dashboard.php?id=' . $r['id']); ?>"
                       class="<?php echo $activeReg && (int)$r['id'] === (int)$activeReg['id'] ? 'active' : ''; ?>"
                       style="display:flex;flex-direction:column;gap:2px;">
                        <span style="font-family:'Consolas','Courier New',monospace;font-weight:700;"><?php echo e($r['subdomain'] . '.' . $r['domain']); ?></span>
                        <span style="font-size:.75rem;color:<?php echo $activeReg && (int)$r['id'] === (int)$activeReg['id'] ? '#9fb6d4' : 'var(--nc-muted)'; ?>;">
                            <?php echo (int)$r['active'] ? 'Active' : 'Paused'; ?> · <?php echo date('M j, Y', strtotime($r['created_at'])); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- active registration detail -->
            <?php if ($activeReg): ?>
            <div class="card wide" style="max-width:none;">
                <div class="section-head" style="margin-bottom:16px;">
                    <div>
                        <h2 style="font-size:1.25rem;"><?php echo e(fqdn($activeReg, ['name' => $activeReg['domain']])); ?></h2>
                        <span class="pill <?php echo (int)$activeReg['active'] ? 'pill-on' : 'pill-off'; ?>" style="margin-top:4px;">
                            <?php echo (int)$activeReg['active'] ? 'Active' : 'Paused'; ?>
                        </span>
                    </div>
                    <div class="actions" style="display:flex;gap:8px;">
                        <form method="post" action="dashboard.php?id=<?php echo (int)$activeReg['id']; ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle_registration">
                            <input type="hidden" name="id" value="<?php echo (int)$activeReg['id']; ?>">
                            <input type="hidden" name="state" value="<?php echo (int)$activeReg['active'] ? 0 : 1; ?>">
                            <button class="btn btn-sm btn-outline" type="submit"><?php echo (int)$activeReg['active'] ? 'Pause' : 'Activate'; ?></button>
                        </form>
                        <form method="post" action="dashboard.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete_registration">
                            <input type="hidden" name="id" value="<?php echo (int)$activeReg['id']; ?>">
                            <button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete this subdomain and all its DNS records?">Delete</button>
                        </form>
                    </div>
                </div>

                <!-- DNS records -->
                <div class="tabs">
                    <button class="tab active" data-pane="dnsPane">DNS Records <span class="badge badge-featured"><?php echo count($records); ?></span></button>
                    <button class="tab" data-pane="infoPane">Info</button>
                </div>

                <div class="tab-pane" id="dnsPane">
                    <div class="table-wrap" style="margin-bottom:20px;">
                        <table class="table">
                            <thead>
                                <tr><th>Type</th><th>Name</th><th>Value</th><th>Priority</th><th>TTL</th><th></th></tr>
                            </thead>
                            <tbody>
                            <?php if (!$records): ?>
                                <tr><td colspan="6" style="text-align:center;color:var(--nc-muted);">No DNS records yet — add your first A or CNAME record below.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($records as $rec): ?>
                                <tr>
                                    <td><span class="badge" style="background:#eef2ff;color:#3b5bdb;"><?php echo e($rec['type']); ?></span></td>
                                    <td class="mono"><?php echo e($rec['name']); ?></td>
                                    <td class="mono" style="word-break:break-all;"><?php echo e($rec['value']); ?></td>
                                    <td><?php echo $rec['priority'] ? (int)$rec['priority'] : '—'; ?></td>
                                    <td><?php echo (int)$rec['ttl']; ?></td>
                                    <td style="width:150px;">
                                        <div class="actions">
                                            <button class="btn btn-sm btn-outline" onclick='editRecord(<?php echo json_encode($rec); ?>)'>Edit</button>
                                            <form method="post" action="dashboard.php?id=<?php echo (int)$activeReg['id']; ?>" style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_record">
                                                <input type="hidden" name="record_id" value="<?php echo (int)$rec['id']; ?>">
                                                <button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete this record?">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Add / edit record form -->
                    <div class="card" style="box-shadow:none;border-color:var(--nc-border);padding:20px;">
                        <h3 id="recordFormTitle" style="margin-bottom:14px;">Add DNS record</h3>
                        <input type="hidden" id="recId" value="">
                        <form method="post" action="dashboard.php?id=<?php echo (int)$activeReg['id']; ?>" id="recordForm">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="add_record" id="recAction">
                            <input type="hidden" name="record_id" value="" id="recRecordId">
                            <input type="hidden" name="registration_id" value="<?php echo (int)$activeReg['id']; ?>">
                            <div class="form-row" style="grid-template-columns:1fr 1.2fr 2fr;">
                                <div class="form-group">
                                    <label for="recType">Type</label>
                                    <select name="type" id="recType">
                                        <?php foreach (record_types() as $t): ?>
                                            <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="recName">Name / Host</label>
                                    <input type="text" id="recName" name="name" value="@" placeholder="@ or www or sub">
                                    <div class="hint">Use <code>@</code> for the bare subdomain.</div>
                                </div>
                                <div class="form-group">
                                    <label for="recValue">Value / Target</label>
                                    <input type="text" id="recValue" name="value" placeholder="1.2.3.4 or target.example.com">
                                </div>
                            </div>
                            <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
                                <div class="form-group">
                                    <label for="recPriority">Priority (MX only)</label>
                                    <input type="number" id="recPriority" name="priority" value="0" min="0" max="65535">
                                </div>
                                <div class="form-group">
                                    <label for="recTtl">TTL (seconds)</label>
                                    <input type="number" id="recTtl" name="ttl" value="3600" min="60" max="86400">
                                </div>
                                <div style="display:flex;align-items:flex-end;gap:8px;">
                                    <button type="submit" class="btn btn-primary" id="recSubmit">Add record</button>
                                    <button type="button" class="btn btn-outline" id="recCancel" style="display:none;">Cancel</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="tab-pane" id="infoPane" style="display:none;">
                    <div class="card" style="box-shadow:none;border-color:var(--nc-border);padding:20px;">
                        <table class="table" style="min-width:0;">
                            <tbody>
                                <tr><th>Full subdomain</th><td class="mono"><?php echo e(fqdn($activeReg, ['name' => $activeReg['domain']])); ?></td></tr>
                                <tr><th>Parent domain</th><td class="mono"><?php echo e($activeReg['domain']); ?></td></tr>
                                <tr><th>Registered on</th><td><?php echo e(date('F j, Y g:i A', strtotime($activeReg['created_at']))); ?></td></tr>
                                <tr><th>Status</th><td><?php echo (int)$activeReg['active'] ? 'Active' : 'Paused'; ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
function editRecord(rec) {
    document.getElementById('recAction').value = 'edit_record';
    document.getElementById('recRecordId').value = rec.id;
    document.getElementById('recId').value = rec.id;
    document.getElementById('recType').value = rec.type;
    document.getElementById('recName').value = rec.name;
    document.getElementById('recValue').value = rec.value;
    document.getElementById('recPriority').value = rec.priority;
    document.getElementById('recTtl').value = rec.ttl;
    document.getElementById('recordFormTitle').textContent = 'Edit DNS record — ' + rec.type + ' Record';
    document.getElementById('recSubmit').textContent = 'Save changes';
    document.getElementById('recCancel').style.display = 'inline-flex';
    window.scrollTo({ top: document.getElementById('recordFormTitle').getBoundingClientRect().top + window.pageYOffset - 120, behavior: 'smooth' });
}
document.addEventListener('DOMContentLoaded', function () {
    var cancel = document.getElementById('recCancel');
    if (cancel) cancel.addEventListener('click', function () {
        location.href = location.pathname + '?id=' + <?php echo (int)$activeReg['id']; ?>;
    });
    // MX priority helper
    document.getElementById('recType').addEventListener('change', function () {
        document.getElementById('recPriority').disabled = this.value !== 'MX';
    });
});
</script>
<?php
page_footer();