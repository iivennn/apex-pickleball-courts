<?php
/** @var string $pageTitle  @var string $adminPage */
$newInquiries = (int) db()->query("SELECT COUNT(*) FROM event_inquiries WHERE status = 'new'")->fetchColumn();
$links = [
    'dashboard' => ['index.php', 'Dashboard', 0],
    'bookings' => ['bookings.php', 'Bookings', 0],
    'openplay' => ['open-play.php', 'Open play sessions', 0],
    'inquiries' => ['inquiries.php', 'Event inquiries', $newInquiries],
    'courts' => ['courts.php', 'Courts & rates', 0],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($pageTitle ?? 'Admin') ?> — <?= e(SITE_NAME) ?> Admin</title>
  <link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-side" aria-label="Admin menu">
    <a href="index.php" class="admin-brand"><img src="../assets/img/logo.png" alt="<?= e(SITE_NAME) ?>"><span>ADMIN</span></a>
    <nav class="admin-nav" aria-label="Admin">
      <?php foreach ($links as $key => [$href, $label, $badge]): ?>
        <a href="<?= $href ?>" class="<?= ($adminPage ?? '') === $key ? 'active' : '' ?>" <?= ($adminPage ?? '') === $key ? 'aria-current="page"' : '' ?>>
          <?= e($label) ?><?php if ($badge): ?><span class="badge"><?= $badge ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-bottom admin-nav">
      <a href="../index.php" target="_blank" rel="noopener">View website ↗</a>
      <a href="account.php" class="<?= ($adminPage ?? '') === 'account' ? 'active' : '' ?>">My account</a>
      <form method="post" action="logout.php" style="margin: 0"><?= csrf_field() ?><button type="submit" class="btn btn-ghost-light btn-block" style="border-color: #52525b">Log out</button></form>
    </div>
  </aside>
  <main class="admin-main">
    <?= render_flash() ?>
