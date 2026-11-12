<?php
require __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$today = date('Y-m-d');
$nowHour = (int) date('G');

// ---------- Today's numbers ----------
$stmt = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS revenue, COALESCE(SUM(hours), 0) AS hrs
                       FROM bookings WHERE booking_date = ? AND status <> 'cancelled'");
$stmt->execute([$today]);
$todayStats = $stmt->fetch();

$courts = $pdo->query('SELECT * FROM courts ORDER BY sort_order, id')->fetchAll();
$openCourts = count(array_filter($courts, fn($c) => $c['status'] === 'available'));
$capacityHours = max(1, $openCourts * (CLOSE_HOUR - OPEN_HOUR));
$occupancy = round($todayStats['hrs'] / $capacityHours * 100);
$newInquiryCount = (int) $pdo->query("SELECT COUNT(*) FROM event_inquiries WHERE status = 'new'")->fetchColumn();

// ---------- Today's bookings ----------
$stmt = $pdo->prepare("SELECT b.*, c.name AS court_name
                       FROM bookings b JOIN courts c ON c.id = b.court_id
                       WHERE b.booking_date = ? AND b.status <> 'cancelled'
                       ORDER BY b.start_hour, c.sort_order");
$stmt->execute([$today]);
$todayBookings = $stmt->fetchAll();

// ---------- Courts right now ----------
$stmt = $pdo->prepare("SELECT s.court_id, b.customer_name FROM booking_slots s JOIN bookings b ON b.id = s.booking_id
                       WHERE s.booking_date = ? AND s.slot_hour = ? AND b.status <> 'cancelled'");
$stmt->execute([$today, $nowHour]);
$inUse = [];
foreach ($stmt->fetchAll() as $row) {
    $inUse[(int) $row['court_id']] = $row['customer_name'];
}

// ---------- Today's open play ----------
$stmt = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM open_play_signups u WHERE u.session_id = s.id AND u.status = 'joined') AS joined
                       FROM open_play_sessions s WHERE s.session_date = ? ORDER BY s.start_time");
$stmt->execute([$today]);
$todaySessions = $stmt->fetchAll();

// ---------- Latest inquiries ----------
$latestInquiries = $pdo->query("SELECT i.*, p.name AS package_name FROM event_inquiries i LEFT JOIN event_packages p ON p.id = i.package_id
                                ORDER BY i.created_at DESC, i.id DESC LIMIT 4")->fetchAll();

// ---------- Bookings per day: today + next 6 days ----------
$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[date('Y-m-d', strtotime("+$i days"))] = 0;
}
$stmt = $pdo->prepare("SELECT booking_date, COUNT(*) AS n FROM bookings WHERE booking_date BETWEEN ? AND ? AND status <> 'cancelled' GROUP BY booking_date");
$stmt->execute([array_key_first($days), array_key_last($days)]);
foreach ($stmt->fetchAll() as $row) {
    $days[$row['booking_date']] = (int) $row['n'];
}
$maxDay = max(1, max($days));

$statusTags = ['confirmed' => 'tag-green', 'checked_in' => 'tag-lime', 'completed' => 'tag-gray', 'cancelled' => 'tag-red'];

$pageTitle = 'Dashboard';
$adminPage = 'dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-top">
  <div>
    <h1>Good <?= $nowHour < 12 ? 'morning' : ($nowHour < 18 ? 'afternoon' : 'evening') ?>, <?= e($_SESSION['admin_name'] ?? 'Admin') ?></h1>
    <p class="muted"><?= e(date('l, F j, Y')) ?></p>
  </div>
  <div style="display: flex; gap: 12px; flex-wrap: wrap">
    <form action="bookings.php" method="get" class="inline-form">
      <label class="sr-only" for="q">Search bookings</label>
      <input type="search" id="q" name="q" placeholder="Name, phone or reference" style="min-height: 44px; min-width: 220px">
      <button class="btn btn-outline" type="submit">Search</button>
    </form>
    <a href="../book.php" target="_blank" rel="noopener" class="btn btn-primary">+ Walk-in booking</a>
  </div>
</div>

<section class="stats" aria-label="Today at a glance">
  <div class="stat"><span>Bookings today</span><strong><?= (int) $todayStats['n'] ?></strong></div>
  <div class="stat"><span>Booked value today</span><strong><?= money($todayStats['revenue']) ?></strong></div>
  <div class="stat"><span>Court occupancy today</span><strong><?= $occupancy ?>%</strong></div>
  <div class="stat"><span>New event inquiries</span><strong><?= $newInquiryCount ?></strong></div>
</section>

<div class="two-col">
  <section class="panel wide" aria-label="Today's bookings">
    <div class="panel-head"><h2>Today's bookings</h2><a href="bookings.php?date=<?= e($today) ?>" style="font-weight: 600; font-size: 14px">Manage</a></div>
    <?php if (!$todayBookings): ?>
      <p class="muted">No bookings yet today.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Time</th><th>Court</th><th>Customer</th><th>Payment</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($todayBookings as $b): ?>
              <tr>
                <td><?= e($b['time_label']) ?></td>
                <td><?= e($b['court_name']) ?></td>
                <td><strong><?= e($b['customer_name']) ?></strong><br><span class="muted"><?= e($b['reference']) ?></span></td>
                <td><?= e(payment_label($b['payment_method'])) ?><br><span class="tag <?= $b['payment_status'] === 'paid' ? 'tag-green' : 'tag-orange' ?>"><?= $b['payment_status'] === 'paid' ? 'Paid' : 'Unpaid' ?></span></td>
                <td><span class="tag <?= $statusTags[$b['status']] ?? 'tag-gray' ?>"><?= e(ucwords(str_replace('_', ' ', $b['status']))) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <div class="narrow">
    <section class="panel" aria-label="Court status">
      <div class="panel-head"><h2>Courts right now</h2><span class="muted" style="font-size: 13px"><?= e(fmt_hour($nowHour)) ?></span></div>
      <div class="court-status">
        <?php foreach ($courts as $c):
          $id = (int) $c['id'];
          if ($c['status'] !== 'available') { $cls = 'maint'; $label = 'Maintenance'; }
          elseif (isset($inUse[$id])) { $cls = 'busy'; $label = 'In use · ' . $inUse[$id]; }
          else { $cls = ''; $label = 'Free'; } ?>
          <div class="<?= $cls ?>"><strong><?= e($c['name']) ?></strong><small><?= e($label) ?></small></div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="panel" aria-label="Today's open play">
      <div class="panel-head"><h2>Open play today</h2><a href="open-play.php" style="font-weight: 600; font-size: 14px">Manage</a></div>
      <?php if (!$todaySessions): ?>
        <p class="muted">No sessions today.</p>
      <?php endif; ?>
      <?php foreach ($todaySessions as $s): ?>
        <div class="list-item">
          <strong><?= e($s['title']) ?></strong>
          <span><?= e(fmt_time($s['start_time'])) ?> · <?= (int) $s['joined'] ?>/<?= (int) $s['capacity'] ?> players</span>
        </div>
      <?php endforeach; ?>
    </section>
  </div>
</div>

<div class="two-col">
  <section class="panel wide" aria-label="Bookings this week">
    <div class="panel-head"><h2>Bookings — next 7 days</h2></div>
    <div class="chart">
      <?php foreach ($days as $d => $n): ?>
        <div class="col <?= $d === $today ? 'today' : '' ?>">
          <b><?= $n ?></b>
          <i style="height: <?= round($n / $maxDay * 140) ?>px"></i>
          <span><?= $d === $today ? 'Today' : e(fmt_date($d, 'D j')) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel narrow" aria-label="Latest event inquiries">
    <div class="panel-head"><h2>Latest inquiries</h2><a href="inquiries.php" style="font-weight: 600; font-size: 14px">View all</a></div>
    <?php if (!$latestInquiries): ?><p class="muted">No inquiries yet.</p><?php endif; ?>
    <?php foreach ($latestInquiries as $i): ?>
      <div class="list-item">
        <strong><?= e($i['organization'] ?: $i['full_name']) ?> — <?= e($i['event_type']) ?> <?php if ($i['status'] === 'new'): ?><span class="tag tag-lime" style="font-size: 11px">NEW</span><?php endif; ?></strong>
        <span><?= e($i['package_name'] ?? 'No package yet') ?> · <?= e(fmt_date($i['preferred_date'], 'M j')) ?> · <?= (int) $i['guests'] ?> guests</span>
      </div>
    <?php endforeach; ?>
  </section>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
