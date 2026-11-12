<?php
require __DIR__ . '/includes/functions.php';

$ref = strtoupper(trim($_GET['ref'] ?? ''));
$stmt = db()->prepare('SELECT b.*, c.name AS court_name, c.court_type FROM bookings b JOIN courts c ON c.id = b.court_id WHERE b.reference = ?');
$stmt->execute([$ref]);
$booking = $stmt->fetch();

$pageTitle = 'Booking confirmed — ' . SITE_NAME;
$activePage = 'book';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <?php if (!$booking): ?>
      <div class="confirm-card">
        <h1>Booking not found</h1>
        <p class="muted">We couldn't find a booking with that reference. Please check the code or contact us at <?= e(SITE_PHONE) ?>.</p>
        <a href="book.php" class="btn btn-primary">Book a court</a>
      </div>
    <?php else: ?>
      <div class="confirm-card">
        <div class="check-circle" aria-hidden="true">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
        </div>
        <h1><?= $booking['status'] === 'cancelled' ? 'This booking was cancelled' : 'You\'re booked!' ?></h1>
        <p class="muted">Show this booking reference at the front desk:</p>
        <p class="ref"><?= e($booking['reference']) ?></p>
        <div class="summary-row"><span class="muted">Name</span><strong><?= e($booking['customer_name']) ?></strong></div>
        <div class="summary-row"><span class="muted">Date</span><strong><?= e(fmt_date($booking['booking_date'], 'l, F j, Y')) ?></strong></div>
        <div class="summary-row"><span class="muted">Court</span><strong><?= e($booking['court_name']) ?> (<?= e($booking['court_type']) ?>)</strong></div>
        <div class="summary-row"><span class="muted">Time</span><strong><?= e($booking['time_label']) ?></strong></div>
        <div class="summary-row"><span class="muted">Players</span><strong><?= (int) $booking['players'] ?></strong></div>
        <div class="summary-row"><span class="muted">Payment</span><strong><?= e(payment_label($booking['payment_method'])) ?> · <?= $booking['payment_status'] === 'paid' ? 'Paid' : 'Not yet paid' ?></strong></div>
        <hr style="border: none; border-top: 1px solid var(--line); margin: 0">
        <div class="summary-row" style="font-size: 20px"><span>Total</span><strong><?= money($booking['total']) ?></strong></div>
        <?php if ($booking['payment_method'] === 'gcash' && $booking['payment_status'] !== 'paid'): ?>
          <div class="alert alert-info">To pay by GCash / Maya, send <?= money($booking['total']) ?> to <?= e(SITE_PHONE) ?> and use <strong><?= e($booking['reference']) ?></strong> as the message. We'll mark it paid once received.</div>
        <?php endif; ?>
        <div style="display: flex; gap: 12px; flex-wrap: wrap">
          <a href="index.php" class="btn btn-outline">Back to home</a>
          <a href="book.php" class="btn btn-primary">Book another court</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
