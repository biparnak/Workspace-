<?php
require_once __DIR__ . '/includes/layout.php';

$domainName = trim($_GET['domain'] ?? '');
$st = db()->prepare('SELECT * FROM domains WHERE name = ? AND available = 1 LIMIT 1');
$st->execute([$domainName]);
$domain = $st->fetch();
if (!$domain) {
    redirect('index.php');
}

$errors = [];
$success = null;
$sub = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $sub   = strtolower(trim($_POST['subdomain'] ?? ''));
    $user  = cur_user();

    if (!valid_subdomain($sub)) {
        $errors[] = 'Subdomain must be 1–' . SUB_MAX_LENGTH . ' chars of lowercase letters, numbers, and hyphens (can’t start or end with a hyphen).';
    }
    if (!$user) {
        $errors[] = 'You need to sign in or create a free account to register a subdomain.';
    }

    if (!$errors) {
        // Availability check (concurrency-safe: unique key on (domain_id, subdomain))
        $st = db()->prepare('INSERT INTO registrations (user_id, domain_id, subdomain) VALUES (?, ?, ?)');
        try {
            $st->execute([$user['id'], $domain['id'], $sub]);
            flash('dns', 'Subdomain <strong>' . e($sub . '.' . $domain['name']) . '</strong> is registered!');
            redirect('dashboard.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() == 23000) {
                $errors[] = 'Sorry, <strong>' . e($sub . '.' . $domain['name']) . '</strong> is already taken. Try a different name.';
            } else {
                throw $ex;
            }
        }
    }
}

page_header('Register ' . $domain['name'], ['checkout']);
?>
<section class="section">
    <div class="container">
        <div style="max-width:880px;margin:0 auto;">
            <h2 style="margin-bottom:18px;color:var(--nc-ink);">&larr; <a href="<?php echo url('index.php'); ?>" style="color:var(--nc-blue);text-decoration:none;">Back to search</a></h2>

            <div class="card wide">
                <h1>Register your subdomain</h1>
                <div class="sub">Free · no credit card required · you’ll get full DNS control.</div>

                <?php foreach ($errors as $er): ?>
                    <div class="alert alert-error"><?php echo $er; ?></div>
                <?php endforeach; ?>
                <?php echo flash('welcome', null, 'info') ?: ''; ?>

                <div class="form-row" style="align-items:start;">
                    <div>
                        <div class="form-group">
                            <label for="subdomainInput">Choose your prefix</label>
                            <div class="input-affix">
                                <input type="text" id="subdomainInput" name="subdomain" value="<?php echo e($sub); ?>"
                                       placeholder="myname" autocomplete="off">
                                <span class="affix">.<?php echo e($domain['name']); ?></span>
                            </div>
                            <div class="hint">Letters, numbers, and hyphens only. Lowercase.</div>
                        </div>

                        <div class="form-group">
                            <label>Your new subdomain</label>
                            <div class="subdomain-preview" id="subdomainPreview" data-parent="<?php echo e($domain['name']); ?>">.<?php echo e($domain['name']); ?></div>
                        </div>

                        <?php if (cur_user()): ?>
                            <form method="post" action="<?php echo url('checkout.php?domain=' . urlencode($domain['name'])); ?>" id="checkoutForm">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="subdomain" value="">
                                <button type="submit" class="btn btn-primary btn-lg btn-block">Register <strong>&#36;0.00</strong></button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">You’ll need an account to register. It only takes a minute — <a href="<?php echo url('register.php?next=' . urlencode($_SERVER['REQUEST_URI'])); ?>" style="color:var(--nc-blue);font-weight:600;">create one for free</a>, or <a href="<?php echo url('login.php?next=' . urlencode($_SERVER['REQUEST_URI'])); ?>" style="color:var(--nc-blue);font-weight:600;">sign in</a>.</div>
                        <?php endif; ?>
                    </div>

                    <div style="background:var(--nc-bg);border:1px solid var(--nc-border);border-radius:var(--radius);padding:22px;min-width:250px;">
                        <div class="stat-card" style="box-shadow:none;border:0;background:transparent;padding:0;margin-bottom:14px;">
                            <div class="stat-label">Parent domain</div>
                            <div class="domain-name" style="font-size:1.5rem;font-weight:700;color:var(--nc-ink);"><?php echo e($domain['name']); ?></div>
                        </div>
                        <div class="stat-card" style="box-shadow:none;border:0;background:transparent;padding:0;margin-bottom:14px;">
                            <div class="stat-label">Price</div>
                            <div style="font-size:1.4rem;font-weight:800;color:var(--nc-green);">$<?php echo number_format((float)$domain['price'], 2, '.', ''); ?> <span style="font-size:.85rem;color:var(--nc-muted);font-weight:500;">/ forever</span></div>
                        </div>
                        <div class="stat-card" style="box-shadow:none;border:0;background:transparent;padding:0;">
                            <div class="stat-label">Includes</div>
                            <ul style="font-size:.88rem;color:var(--nc-muted);padding-left:18px;margin-top:6px;line-height:1.9;">
                                <li>Full DNS management</li>
                                <li>A, AAAA, CNAME, MX, TXT, NS</li>
                                <li>No renewal fees (ever)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// keep hidden field in sync with the live preview
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('subdomainInput');
    var hidden = document.querySelector('#checkoutForm input[name="subdomain"]');
    document.getElementById('checkoutForm').addEventListener('submit', function (e) {
        hidden.value = (input.value || '').toLowerCase().replace(/[^a-z0-9-]/g, '');
    });
});
</script>
<?php
page_footer();