<?php
require __DIR__ . '/includes/functions.php';

$pdo = db();
$today = date('Y-m-d');
$minCourtRate = $pdo->query("SELECT MIN(hourly_rate) FROM courts WHERE status = 'available'")->fetchColumn();
$stmt = $pdo->prepare('SELECT MIN(price) FROM open_play_sessions WHERE session_date >= ?');
$stmt->execute([$today]);
$minOpenPlay = $stmt->fetchColumn();
$minPackage = $pdo->query('SELECT MIN(price) FROM event_packages WHERE price IS NOT NULL')->fetchColumn();

$gallery = glob(__DIR__ . '/assets/img/gallery/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];

$pageTitle = SITE_NAME . ' — Book your court online';
$activePage = 'home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container hero-inner">
    <div class="hero-copy">
      <p class="eyebrow">Pickleball in <?= e(SITE_CITY) ?></p>
      <h1>Book your pickleball court in minutes</h1>
      <p class="lead">Reserve a court, join open play, or rent the venue for your next event — all online.</p>
      <div class="hero-actions">
        <a href="book.php" class="btn btn-lime">Book a court</a>
        <a href="open-play.php" class="btn btn-ghost-light">See open play schedule</a>
      </div>
    </div>
    <div class="hero-art">
      <div class="hero-video-wrap">
      <video class="hero-video" autoplay muted loop playsinline preload="metadata"
             poster="assets/video/apex-promo-poster.jpg" aria-label="Apex Pickleball Courts promo video">
        <source src="assets/video/apex-promo.mp4" type="video/mp4">
      </video>
      <button type="button" class="video-sound" data-video-sound aria-pressed="false" aria-label="Turn sound on">🔇 Sound off</button>
      </div>
    </div>
  </div>
</section>

<section class="container quick-book" aria-label="Quick booking">
  <form action="book.php" method="get">
    <label class="field">Date
      <input type="date" name="date" value="<?= e($today) ?>" min="<?= e($today) ?>" max="<?= e(date('Y-m-d', strtotime('+' . (DAYS_AHEAD - 1) . ' days'))) ?>" required>
    </label>
    <button type="submit" class="btn btn-primary">Check availability</button>
  </form>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <h2>What we offer</h2>
      <p class="muted">Play with friends, meet new players, or host your own event.</p>
    </div>
    <div class="card-grid">
      <article class="card">
        <div class="card-art" aria-hidden="true">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4M8 14l2.5 2.5L16 12"/></svg>
        </div>
        <div class="card-body">
          <h3>Court Reservations</h3>
          <p class="muted">Pick a date, time and court. Pay online or at the counter.</p>
          <?php if ($minCourtRate): ?><p class="price">From <?= money($minCourtRate) ?> / hour</p><?php endif; ?>
          <a href="book.php" class="link">Book a court →</a>
        </div>
      </article>
      <article class="card">
        <div class="card-art" aria-hidden="true">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M14.5 14.5c.8-.3 1.6-.5 2.5-.5 2.8 0 5 2.2 5 5"/></svg>
        </div>
        <div class="card-body">
          <h3>Open Play</h3>
          <p class="muted">Come solo and play with others at your skill level.</p>
          <?php if ($minOpenPlay): ?><p class="price">From <?= money($minOpenPlay) ?> per session</p><?php endif; ?>
          <a href="open-play.php" class="link">View schedule →</a>
        </div>
      </article>
      <article class="card">
        <div class="card-art" aria-hidden="true">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></svg>
        </div>
        <div class="card-body">
          <h3>Event Rentals</h3>
          <p class="muted">Rent courts for tournaments, team building or birthdays.</p>
          <?php if ($minPackage): ?><p class="price">Packages from <?= money($minPackage) ?></p><?php endif; ?>
          <a href="rentals.php" class="link">Plan an event →</a>
        </div>
      </article>
    </div>
  </div>
</section>

<section class="section" style="background: var(--mint); ">
  <div class="container">
    <div class="section-head"><h2>How booking works</h2></div>
    <div class="steps">
      <div class="step"><span class="step-num">1</span><h3>Choose a date</h3><p class="muted">See open slots for every court, up to <?= DAYS_AHEAD ?> days ahead.</p></div>
      <div class="step"><span class="step-num">2</span><h3>Pick a court &amp; time</h3><p class="muted">Select up to <?= MAX_HOURS ?> hourly slots.</p></div>
      <div class="step"><span class="step-num">3</span><h3>Choose how to pay</h3><p class="muted">GCash / Maya, card, or pay at the venue.</p></div>
      <div class="step"><span class="step-num">4</span><h3>Show up &amp; play</h3><p class="muted">Show your booking reference at the front desk.</p></div>
    </div>
  </div>
</section>

<?php if ($gallery): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><h2>Gallery</h2></div>
    <div class="gallery">
      <?php foreach (array_slice($gallery, 0, 8) as $img): ?>
        <img src="assets/img/gallery/<?= e(basename($img)) ?>" alt="Players at <?= e(SITE_NAME) ?>" loading="lazy">
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
