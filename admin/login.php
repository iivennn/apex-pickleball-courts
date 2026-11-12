<?php
require __DIR__ . '/../includes/functions.php';

if (is_admin()) {
    redirect('index.php');
}

$error = '';
$attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'until' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($attempts['until'] > time()) {
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } else {
        $stmt = db()->prepare('SELECT * FROM admins WHERE username = ?');
        $stmt->execute([trim($_POST['username'] ?? '')]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($_POST['password'] ?? '', $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            unset($_SESSION['login_attempts']);
            redirect('index.php');
        }
        $attempts['count']++;
        if ($attempts['count'] >= 5) {
            $attempts = ['count' => 0, 'until' => time() + 300];
        }
        $_SESSION['login_attempts'] = $attempts;
        $error = 'Wrong username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Staff login — <?= e(SITE_NAME) ?></title>
  <link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<main class="login-wrap">
  <form method="post" class="login-card">
    <div class="login-logo"><img src="../assets/img/logo.png" alt="<?= e(SITE_NAME) ?>"></div>
    <h1 style="font-size: 24px">Staff login</h1>
    <?php if ($error): ?><div class="alert alert-error" role="alert" style="margin: 0"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label class="field">Username<input type="text" name="username" required autocomplete="username" autofocus></label>
    <label class="field">Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit" class="btn btn-primary btn-block">Log in</button>
    <a href="../index.php" style="font-size: 14px">← Back to website</a>
  </form>
</main>
</body>
</html>
