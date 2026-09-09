<?php
require_once __DIR__ . '/_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);

    if (($_POST['action'] ?? '') === 'promote') {
        db()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$id]);
    }
    if (($_POST['action'] ?? '') === 'demote') {
        db()->prepare('UPDATE users SET is_admin = 0 WHERE id = ? AND id != ?')->execute([$id, cur_user()['id']]);
    }
    if (($_POST['action'] ?? '') === 'delete') {
        db()->prepare('DELETE FROM users WHERE id = ? AND id != ?')->execute([$id, cur_user()['id']]);
    }
    redirect('admin/users.php');
}

$users = db()->query('SELECT id, username, email, is_admin, created_at FROM users ORDER BY id DESC')->fetchAll();
?>
        <div class="card wide" style="max-width:none;">
            <div class="section-head"><h3>Users (<?php echo count($users); ?>)</h3></div>
            <div class="table-wrap" style="box-shadow:none;">
                <table class="table">
                    <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo e($u['username']); ?><?php echo (int)$u['id'] === (int)cur_user()['id'] ? ' (you)' : ''; ?></td>
                            <td><?php echo e($u['email']); ?></td>
                            <td><span class="pill <?php echo (int)$u['is_admin'] ? 'pill-on' : 'pill-off'; ?>"><?php echo (int)$u['is_admin'] ? 'Admin' : 'User'; ?></span></td>
                            <td><?php echo e(date('M j, Y', strtotime($u['created_at']))); ?></td>
                            <td>
                                <div class="actions">
                                    <?php if ((int)$u['id'] !== (int)cur_user()['id']): ?>
                                        <form method="post" action="users.php"><?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                            <input type="hidden" name="action" value="<?php echo (int)$u['is_admin'] ? 'demote' : 'promote'; ?>">
                                            <button class="btn btn-sm btn-outline"><?php echo (int)$u['is_admin'] ? 'Revoke admin' : 'Make admin'; ?></button>
                                        </form>
                                        <form method="post" action="users.php"><?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button class="btn btn-sm btn-danger" data-confirm="Delete user <?php echo e($u['username']); ?> and all their subdomains?">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:var(--nc-muted);font-size:.85rem;">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php require_once __DIR__ . '/_footer.php';