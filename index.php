<?php
require_once __DIR__ . '/includes/layout.php';

$q      = trim($_GET['q'] ?? '');
$cat    = trim($_GET['cat'] ?? '');
$only   = trim($_GET['only'] ?? '');   // chips: featured / all / popular-cats
$user   = cur_user();

// Build query on domains
$where  = ['available = 1'];
$params = [];
if ($q !== '') {
    $where[] = 'name LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($cat !== '') {
    $where[] = 'category = ?';
    $params[] = $cat;
}
$order = 'featured DESC, name ASC';
if ($only === 'featured') {
    $where[] = 'featured = 1';
}
$sql = 'SELECT * FROM domains WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order . ' LIMIT 100';
$st  = db()->prepare($sql);
$st->execute($params);
$domains = $st->fetchAll();

// Categories for the filter
$cats = db()->query('SELECT category, COUNT(*) AS n FROM domains GROUP BY category ORDER BY n DESC')->fetchAll();

page_header('Find your free domain', ['home']);
?>
<section class="hero">
    <div class="container">
        <h1>Find your <span class="highlight">free</span> domain in seconds.</h1>
        <p>Pick a free subdomain from our registry — short, memorable and completely free. Register in under a minute.</p>
        <form class="search-bar" method="get" action="index.php">
            <input type="search" name="q" value="<?php echo e($q); ?>"
                   placeholder="Search domains… e.g. fr.to" autocomplete="off">
            <button class="btn btn-orange" type="submit">Search</button>
        </form>
        <div class="search-stats"><?php echo count($domains); ?> domains shown</div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="filter-bar">
            <a href="<?php echo url('index.php'); ?>" class="btn btn-sm <?php echo $only === '' && $cat === '' && $q === '' ? 'btn-primary' : 'btn-outline'; ?>">All domains</a>
            <a href="<?php echo url('index.php?only=featured'); ?>" class="btn btn-sm <?php echo $only === 'featured' ? 'btn-primary' : 'btn-outline'; ?>">Featured</a>
            <span></span>
            <form method="get" action="index.php" style="display:flex;gap:8px;flex:1;">
                <?php if ($only): ?><input type="hidden" name="only" value="<?php echo e($only); ?>"><?php endif; ?>
                <select name="cat" data-auto-submit>
                    <option value="">All categories</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?php echo e($c['category']); ?>" <?php echo $cat === $c['category'] ? 'selected' : ''; ?>>
                            <?php echo e($c['category']); ?> (<?php echo (int)$c['n']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (!$domains): ?>
            <div class="empty-state">
                <div class="icon">&#128269;</div>
                <h3>No domains found</h3>
                <p>Try a different search term, or check back later — new domains are added by admins.</p>
            </div>
        <?php else: ?>
            <div class="domain-grid">
                <?php foreach ($domains as $d): ?>
                    <div class="domain-card <?php echo (int)$d['featured'] ? 'featured' : ''; ?>">
                        <?php if ((int)$d['featured']): ?>
                            <span class="badge badge-featured">Featured</span>
                        <?php endif; ?>
                        <div class="domain-name"><?php echo e($d['name']); ?></div>
                        <div class="domain-cat"><?php echo e($d['category']); ?></div>
                        <div class="domain-price"><span class="free">Free</span>&nbsp;<?php echo $d['price'] > 0 ? '· $' . number_format((float)$d['price'], 2) : ''; ?></div>
                        <?php if ($d['description']): ?>
                            <div class="domain-desc"><?php echo e($d['description']); ?></div>
                        <?php endif; ?>
                        <div class="domain-actions">
                            <a class="btn btn-sm btn-primary" href="<?php echo url('checkout.php?domain=' . urlencode($d['name'])); ?>">Register</a>
                            <?php if ($user): ?>
                                <a class="btn btn-sm btn-outline" href="<?php echo url('dashboard.php'); ?>">My Domains</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
page_footer();