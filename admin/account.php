<?php
require __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $hash = $stmt->fetchColumn();
    $new = $_POST['new_password'] ?? '';

    if (!$hash || !password_verify($_POST['current_password'] ?? '', $hash)) $errors[] = 'Your current password is wrong.';
    if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
    if ($new !== ($_POST['confirm_password'] ?? '')) $errors[] = 'The new passwords do not match.';

    if (!$errors) {
        $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
        flash('success', 'Password changed.');
        redirect('account.php');
    }
}

$pageTitle = 'My account';
$adminPage = 'account';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-top"><h1>My account</h1></div>
<section class="panel" style="max-width: 480px">
  <h2 style="font-size: 18px">Change password</h2>
  <?php if ($errors): ?><div class="alert alert-error" role="alert" style="margin: 0"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
  <form method="post" style="display: flex; flex-direction: column; gap: 14px">
    <?= csrf_field() ?>
    <label class="field">Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
    <label class="field">New password <span class="hint">(at least 8 characters)</span><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
    <label class="field">Confirm new password<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
    <button type="submit" class="btn btn-primary">Save new password</button>
  </form>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
