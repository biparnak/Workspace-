<?php
require_once __DIR__ . '/includes/layout.php';

if (logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$next  = isset($_GET['next']) ? $_GET['next'] : 'dashboard.php';
$old = ['username' => '', 'email' => ''];

if (isset($_GET['next']) && !preg_match('~^[a-zA-Z0-9_./?=&%-]+$~', $_GET['next'])) {
    $next = 'dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['username'] = trim($_POST['username'] ?? '');
    $old['email']    = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirm         = $_POST['password2'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $old['username'])) {
        $errors[] = 'Username must be 3–50 characters (letters, numbers, underscore).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < MIN_PASSWORD) {
        $errors[] = 'Password must be at least ' . MIN_PASSWORD . ' characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if ($password && strlen($password) >= MIN_PASSWORD) {
        foreach (['username', 'email'] as $col) {
            $st = db()->prepare("SELECT id FROM users WHERE $col = ? LIMIT 1");
            $st->execute([$old[$col]]);
            if ($st->fetch()) {
                $errors[] = ucfirst($col) . ' is already registered.';
            }
        }
    }

    if (!$errors) {
        $st = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $st->execute([$old['username'], $old['email'], password_hash($password, PASSWORD_DEFAULT)]);
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([db()->lastInsertId()]);
        login_user($st->fetch());
        flash('welcome', 'Welcome to ' . SITE_NAME . ', ' . $old['username'] . '!');
        redirect($next === 'register.php' ? 'dashboard.php' : $next);
    }
}

page_header('Create account', ['auth']);
?>
<div class="auth-wrap">
    <div class="card auth-card">
        <h1>Create account</h1>
        <div class="sub">One account to register and manage all your free subdomains.</div>

        <?php foreach ($errors as $er): ?>
            <div class="alert alert-error"><?php echo e($er); ?></div>
        <?php endforeach; ?>

        <form method="post" action="register.php" novalidate>
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo e($old['username']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?php echo e($old['email']); ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="<?php echo MIN_PASSWORD; ?>" required>
                </div>
                <div class="form-group">
                    <label for="password2">Confirm password</label>
                    <input type="password" id="password2" name="password2" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg btn-block">Create account</button>
            </div>
        </form>
        <div class="auth-links">Already have an account? <a href="<?php echo url('login.php'); ?>">Sign in</a></div>
    </div>
</div>
<?php
page_footer();