<?php
require_once __DIR__ . '/_header.php';

// ---- Actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_one') {
        $name = strtolower(trim($_POST['name'] ?? ''));
        $cat  = trim($_POST['category'] ?? 'General');
        $desc = trim($_POST['description'] ?? '');
        $fee  = max(0, (float)($_POST['price'] ?? 0));
        if (preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)+$/', $name)) {
            $st = db()->prepare('INSERT INTO domains (name, price, description, category) VALUES (?,?,?,?)');
            try {
                $st->execute([$name, $fee, $desc ?: null, $cat ?: 'General']);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $_SESSION['flash']['admin'] = ['msg' => 'Domain "' . $name . '" already exists.', 'kind' => 'error'];
                }
            }
        }
    }

    if ($action === 'import_bulk') {
        $lines  = array_filter(array_map('trim', explode("\n", $_POST['domains'] ?? '')), fn($l) => $l !== '');
        $cat    = trim($_POST['category'] ?? 'General');
        $fee    = max(0, (float)($_POST['price'] ?? 0));
        $added = 0; $skipped = 0;
        $st = db()->prepare('INSERT IGNORE INTO domains (name, price, description, category) VALUES (?,?,?,?)');
        foreach ($lines as $line) {
            if (!preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)+$/', strtolower($line))) { $skipped++; continue; }
            $st->execute([strtolower($line), $fee, null, $cat]);
            $added += $st->rowCount();
        }
        $_SESSION['flash']['admin'] = ['msg' => "Imported $added domain(s), $skipped invalid/duplicate skipped.", 'kind' => 'info'];
    }

    if ($action === 'quick') {
        $id = (int)($_POST['id'] ?? 0);
        $set = $_POST['set'] ?? '';
        if ($set === 'feature')      { db()->prepare('UPDATE domains SET featured = !featured WHERE id = ?')->execute([$id]); }
        if ($set === 'available')    { db()->prepare('UPDATE domains SET available = !available WHERE id = ?')->execute([$id]); }
        if ($set === 'delete')       { db()->prepare('DELETE FROM domains WHERE id = ?')->execute([$id]); }
    }

    redirect('admin/domains.php');
}

// ---- Data ----
$search = trim($_GET['q'] ?? '');
$where  = '';
$params = [];
if ($search !== '') {
    $where = ' WHERE name LIKE ?';
    $params[] = '%' . $search . '%';
}
$st = db()->prepare('SELECT * FROM domains' . $where . ' ORDER BY name ASC LIMIT 300');
$st->execute($params);
$domains = $st->fetchAll();
?>
        <div class="card wide" style="max-width:none;margin-bottom:24px;">
            <h3>Import your domains <span class="hint">(paste your list)</span></h3>
            <div class="sub" style="margin-bottom:16px;">One domain per line — e.g. <code>yourdomain.tld</code>. The "Add later via admin" domains go in here.</div>
            <form method="post" action="domains.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="import_bulk">
                <textarea id="importDomains" name="domains" rows="8" placeholder="fr.to&#10;us.to&#10;uk.to&#10;my-cool.tld" style="font-family:'Consolas',monospace;margin-bottom:12px;"></textarea>
                <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;align-items:end;">
                    <div class="form-group">
                        <label for="impCat">Category</label>
                        <input type="text" name="category" id="impCat" value="General">
                    </div>
                    <div class="form-group">
                        <label for="impPrice">Price (USD)</label>
                        <input type="number" name="price" id="impPrice" value="0.00" min="0" step="0.01">
                    </div>
                    <div>
                        <button class="btn btn-primary btn-block" type="submit">Import <span id="importCount">0 domains</span></button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card wide" style="max-width:none;margin-bottom:24px;">
            <h3>Add a single domain</h3>
            <form method="post" action="domains.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_one">
                <div class="form-row" style="grid-template-columns:1.5fr 1fr 1fr 1fr;align-items:end;">
                    <div class="form-group">
                        <label for="addName">Domain</label>
                        <input type="text" name="name" id="addName" placeholder="my-new.tld" required>
                    </div>
                    <div class="form-group">
                        <label for="addCat">Category</label>
                        <input type="text" name="category" id="addCat" value="General">
                    </div>
                    <div class="form-group">
                        <label for="addPrice">Price</label>
                        <input type="number" name="price" id="addPrice" value="0.00" min="0" step="0.01">
                    </div>
                    <div><button class="btn btn-primary btn-block" type="submit">Add domain</button></div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="addDesc">Description (optional)</label>
                    <input type="text" name="description" id="addDesc" placeholder="Short & memorable free subdomain">
                </div>
            </form>
        </div>

        <div class="card wide" style="max-width:none;">
            <div class="section-head">
                <h3>Current domains (<?php echo count($domains); ?>)</h3>
                <form method="get" action="domains.php"><input type="search" name="q" value="<?php echo e($search); ?>" placeholder="Filter…"></form>
            </div>
            <div class="table-wrap" style="box-shadow:none;">
                <table class="table">
                    <thead><tr><th>Domain</th><th>Category</th><th>Price</th><th>Featured</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (!$domains): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--nc-muted);">No domains found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($domains as $d): ?>
                        <tr>
                            <td class="mono" style="font-weight:700;"><?php echo e($d['name']); ?></td>
                            <td><?php echo e($d['category']); ?></td>
                            <td>$<?php echo number_format((float)$d['price'], 2, '.', ''); ?></td>
                            <td><span class="pill <?php echo (int)$d['featured'] ? 'pill-on' : 'pill-off'; ?>"><?php echo (int)$d['featured'] ? 'Yes' : 'No'; ?></span></td>
                            <td><span class="pill <?php echo (int)$d['available'] ? 'pill-on' : 'pill-off'; ?>"><?php echo (int)$d['available'] ? 'Available' : 'Hidden'; ?></span></td>
                            <td>
                                <div class="actions">
                                    <form method="post" action="domains.php"><?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="quick"><input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                                        <input type="hidden" name="set" value="feature">
                                        <button class="btn btn-sm btn-outline"><?php echo (int)$d['featured'] ? 'Unfeature' : 'Feature'; ?></button>
                                    </form>
                                    <form method="post" action="domains.php"><?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="quick"><input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                                        <input type="hidden" name="set" value="available">
                                        <button class="btn btn-sm btn-outline"><?php echo (int)$d['available'] ? 'Hide' : 'Show'; ?></button>
                                    </form>
                                    <form method="post" action="domains.php"><?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="quick"><input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                                        <input type="hidden" name="set" value="delete">
                                        <button class="btn btn-sm btn-danger" data-confirm="Delete <?php echo e($d['name']); ?>?">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php require_once __DIR__ . '/_footer.php';