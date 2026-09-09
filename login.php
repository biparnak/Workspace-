<?php
require_once __DIR__ . '/includes/layout.php';

if (logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$next   = isset($_GET['next']) && str_starts_with($_GET['next'], '/') ? $_GET['next'] : 'dashboard.php';
$login  = trim($_POST['login'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $login = trim($_POST['login'] ?? '');
    $pw    = $_POST['password'] ?? '';

    $role = authenticate($login, $pw);
    if ($role === false) {
        $errors[] = 'Invalid username/email or password.';
    } else {
        flash('welcome', 'Signed in successfully.');
        redirect($next === 'login.php' ? 'dashboard.php' : $next);
    }
}

page_header('Sign in', ['auth']);
?>
<div class="auth-wrap">
    <div class="card auth-card">
        <h1>Welcome back</h1>
        <div class="sub">Sign in to manage your subdomains.</div>

        <?php echo flash('welcome'); ?>
        <?php foreach ($errors as $er): ?>
            <div class="alert alert-error"><?php echo e($er); ?></div>
        <?php endforeach; ?>

        <form method="post" action="login.php<?php echo $next && $next !== 'dashboard.php' ? '?next=' . urlencode($next) : ''; ?>">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="login">Username or Email</label>
                <input type="text" id="login" name="login" value="<?php echo e($login); ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg btn-block">Sign in</button>
            </div>
        </form>
        <div class="auth-links">New here? <a href="<?php echo url('register.php'); ?>">Create an account</a></div>
    </div>
</div>
<?php
page_footer();