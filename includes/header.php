<?php
/** @var string $pageTitle  @var string $activePage */
$pageTitle = $pageTitle ?? SITE_NAME;
$activePage = $activePage ?? '';
$nav = [
    'home' => ['index.php', 'Home'],
    'book' => ['book.php', 'Book a Court'],
    'openplay' => ['open-play.php', 'Open Play'],
    'rentals' => ['rentals.php', 'Event Rentals'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="Book pickleball courts, join open play and rent the venue for events at <?= e(SITE_NAME) ?>.">
  <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
  <div class="container header-inner">
    <a href="index.php" class="brand"><img src="assets/img/logo.png" alt="<?= e(SITE_NAME) ?>"></a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
      <span class="sr-only">Menu</span>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
    <nav id="site-nav" class="site-nav" aria-label="Main">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= $href ?>" <?= $activePage === $key ? 'class="active" aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="#contact">Contact</a>
      <a href="book.php" class="btn btn-lime nav-cta">Book now</a>
    </nav>
  </div>
</header>
<main id="main">
