<?php
require __DIR__ . '/includes/functions.php';

$pdo = db();
$today = date('Y-m-d');
$lastDay = date('Y-m-d', strtotime('+' . (DAYS_AHEAD - 1) . ' days'));
$errors = [];
$maxPlayers = defined('MAX_PLAYERS') ? (int) MAX_PLAYERS : 16;
$old = ['name' => '', 'phone' => '', 'email' => '', 'players' => 4, 'payment' => 'gcash', 'slots' => []];

// ---------- Which date / court are we looking at? ----------
$source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$date = $source['date'] ?? $today;
if (!valid_date($date) || $date < $today || $date > $lastDay) {
    $date = $today;
}
$type = in_array($_GET['type'] ?? '', ['Indoor', 'Outdoor'], true) ? $_GET['type'] : '';

$courts = $pdo->query('SELECT * FROM courts ORDER BY sort_order, id')->fetchAll();
$available = array_values(array_filter($courts, fn($c) => $c['status'] === 'available' && ($type === '' || $c['court_type'] === $type)));
if (!$available) {
    $available = array_values(array_filter($courts, fn($c) => $c['status'] === 'available'));
}
$court = null;
$wanted = (int) ($source['court'] ?? 0);
foreach ($available as $c) {
    if ((int) $c['id'] === $wanted) {
        $court = $c;
    }
}
$court = $court ?? ($available[0] ?? null);

// Hours that can't be booked: already taken, or already started today
function blocked_hours(PDO $pdo, int $courtId, string $date): array
{
    $stmt = $pdo->prepare(
        "SELECT s.slot_hour FROM booking_slots s JOIN bookings b ON b.id = s.booking_id
         WHERE s.court_id = ? AND s.booking_date = ? AND b.status <> 'cancelled'"
    );
    $stmt->execute([$courtId, $date]);
    $taken = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $past = [];
    if ($date === date('Y-m-d')) {
        for ($h = OPEN_HOUR; $h <= (int) date('G') && $h < CLOSE_HOUR; $h++) {
            $past[] = $h;
        }
    }
    return ['taken' => $taken, 'past' => $past];
}

// ---------- Handle the booking form ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = [
        'name' => trim($_POST['name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'players' => (int) ($_POST['players'] ?? 4),
        'payment' => $_POST['payment'] ?? '',
        'slots' => array_values(array_unique(array_map('intval', (array) ($_POST['slots'] ?? [])))),
    ];

    if (!$court || (int) $court['id'] !== (int) ($_POST['court'] ?? 0)) {
        $errors[] = 'Please choose an available court.';
    }
    if (!$old['slots']) {
        $errors[] = 'Please choose at least one time slot.';
    } elseif (count($old['slots']) > MAX_HOURS) {
        $errors[] = 'You can book up to ' . MAX_HOURS . ' hours at a time.';
    }
    if ($court) {
        $blocked = blocked_hours($pdo, (int) $court['id'], $date);
        foreach ($old['slots'] as $h) {
            if ($h < OPEN_HOUR || $h >= CLOSE_HOUR || in_array($h, $blocked['past'], true)) {
                $errors[] = 'One of the selected times is not available.';
                break;
            }
            if (in_array($h, $blocked['taken'], true)) {
                $errors[] = 'Sorry, ' . fmt_hour($h) . ' was just booked by someone else. Please pick another time.';
                break;
            }
        }
    }
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors[] = 'Please enter your full name.';
    }
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $old['phone'])) {
        $errors[] = 'Please enter a valid mobile number.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['players'] < 1 || $old['players'] > $maxPlayers) {
        $errors[] = 'Number of players must be between 1 and ' . $maxPlayers . '.';
    }
    if (!in_array($old['payment'], ['gcash', 'card', 'venue'], true)) {
        $errors[] = 'Please choose a payment method.';
    }

    if (!$errors) {
        $reference = generate_reference();
        $total = count($old['slots']) * (float) $court['hourly_rate'];
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO bookings (reference, court_id, booking_date, start_hour, hours, time_label, customer_name, phone, email, players, payment_method, total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$reference, $court['id'], $date, min($old['slots']), count($old['slots']), fmt_slots($old['slots']), $old['name'], $old['phone'], $old['email'], $old['players'], $old['payment'], $total]);
            $bookingId = (int) $pdo->lastInsertId();
            $slotStmt = $pdo->prepare('INSERT INTO booking_slots (booking_id, court_id, booking_date, slot_hour) VALUES (?, ?, ?, ?)');
            foreach ($old['slots'] as $h) {
                $slotStmt->execute([$bookingId, $court['id'], $date, $h]);
            }
            $pdo->commit();
            $_SESSION['my_bookings'][] = $reference;
            redirect('booking-success.php?ref=' . urlencode($reference));
        } catch (PDOException $ex) {
            $pdo->rollBack();
            if ($ex->getCode() === '23000') {
                $errors[] = 'Sorry, one of those times was just booked by someone else. Please pick another time.';
            } else {
                throw $ex;
            }
        }
    }
}

$blocked = $court ? blocked_hours($pdo, (int) $court['id'], $date) : ['taken' => [], 'past' => []];
$query = fn(array $extra) => 'book.php?' . http_build_query(array_merge(['date' => $date, 'court' => $court['id'] ?? null, 'type' => $type ?: null], $extra));

$pageTitle = 'Book a Court — ' . SITE_NAME;
$activePage = 'book';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="container">
    <h1>Book a Court</h1>
    <p>Choose a date and court, then tap the time slots you want. Up to <?= MAX_HOURS ?> hours per booking.</p>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <?php if ($errors): ?>
      <div class="alert alert-error" role="alert"><?= implode('<br>', array_map('e', array_unique($errors))) ?></div>
    <?php endif; ?>

    <?php if (!$court): ?>
      <div class="empty">No courts are open for booking right now. Please call us at <?= e(SITE_PHONE) ?>.</div>
    <?php else: ?>
    <form method="post" action="<?= e($query([])) ?>" class="book-layout" id="booking-form" data-rate="<?= e($court['hourly_rate']) ?>" data-max="<?= MAX_HOURS ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <input type="hidden" name="court" value="<?= e($court['id']) ?>">

      <div class="book-main">
        <div class="book-step">
          <h2>1. Choose a date</h2>
          <nav class="date-strip" aria-label="Dates">
            <?php for ($i = 0; $i < DAYS_AHEAD; $i++):
              $d = date('Y-m-d', strtotime("+$i days")); ?>
              <a class="date-pill <?= $d === $date ? 'active' : '' ?>" href="<?= e($query(['date' => $d])) ?>" <?= $d === $date ? 'aria-current="date"' : '' ?>>
                <small><?= $i === 0 ? 'Today' : e(fmt_date($d, 'D')) ?></small>
                <strong><?= e(fmt_date($d, 'j')) ?></strong>
                <small><?= e(fmt_date($d, 'M')) ?></small>
              </a>
            <?php endfor; ?>
          </nav>
        </div>

        <div class="book-step">
          <h2>2. Choose a court</h2>
          <div class="court-grid">
            <?php foreach ($courts as $c):
              $isActive = (int) $c['id'] === (int) $court['id'];
              $isMaint = $c['status'] !== 'available'; ?>
              <a class="court-option <?= $isActive ? 'active' : '' ?> <?= $isMaint ? 'disabled' : '' ?>"
                 href="<?= e($query(['court' => $c['id'], 'type' => null])) ?>" <?= $isActive ? 'aria-current="true"' : '' ?> <?= $isMaint ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                <strong><?= e($c['name']) ?></strong>
                <span><?= e($c['court_type']) ?> · <?= $isMaint ? 'Under maintenance' : money($c['hourly_rate']) . '/hr' ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <fieldset class="book-step" style="border: none; padding: 0; margin: 0">
          <legend><h2>3. Choose time slots — <?= e(fmt_date($date, 'l, F j')) ?></h2></legend>
          <div class="legend">
            <span><i></i>Available</span><span><i class="sel"></i>Selected</span><span><i class="booked"></i>Booked / past</span>
          </div>
          <div class="slot-grid">
            <?php for ($h = OPEN_HOUR; $h < CLOSE_HOUR; $h++):
              $off = in_array($h, $blocked['taken'], true) || in_array($h, $blocked['past'], true); ?>
              <label class="slot">
                <input type="checkbox" name="slots[]" value="<?= $h ?>" data-hour="<?= $h ?>" <?= $off ? 'disabled' : '' ?> <?= !$off && in_array($h, $old['slots'], true) ? 'checked' : '' ?>>
                <span><?= e(fmt_hour($h)) ?><?php if (in_array($h, $blocked['taken'], true)): ?><span class="sr-only"> (booked)</span><?php endif; ?></span>
              </label>
            <?php endfor; ?>
          </div>
        </fieldset>

        <div class="book-step">
          <h2>4. Your details</h2>
          <div class="form-grid">
            <label class="field">Full name<input type="text" name="name" value="<?= e($old['name']) ?>" required maxlength="100" autocomplete="name"></label>
            <label class="field">Mobile number<input type="tel" name="phone" value="<?= e($old['phone']) ?>" required placeholder="09XX XXX XXXX" autocomplete="tel"></label>
            <label class="field">Email<input type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email"></label>
            <label class="field">Number of players<input type="number" name="players" value="<?= e($old['players']) ?>" min="1" max="<?= $maxPlayers ?>" required></label>
          </div>
        </div>
      </div>

      <aside class="summary" aria-label="Booking summary">
        <h2>Booking summary</h2>
        <div class="summary-row"><span>Date</span><strong><?= e(fmt_date($date, 'D, M j')) ?></strong></div>
        <div class="summary-row"><span>Court</span><strong><?= e($court['name']) ?> (<?= e($court['court_type']) ?>)</strong></div>
        <div class="summary-row"><span>Time</span><strong id="sum-time">Pick a time</strong></div>
        <div class="summary-row"><span>Rate</span><strong><span id="sum-hours">0</span> hr × <?= money($court['hourly_rate']) ?></strong></div>
        <hr>
        <div class="summary-row total"><span>Total</span><strong id="sum-total"><?= money(0) ?></strong></div>
        <fieldset class="radio-list">
          <legend>Payment method</legend>
          <?php foreach (['gcash' => 'GCash / Maya', 'card' => 'Credit / debit card', 'venue' => 'Pay at the venue'] as $val => $label): ?>
            <label><input type="radio" name="payment" value="<?= $val ?>" <?= $old['payment'] === $val ? 'checked' : '' ?> required> <?= e($label) ?></label>
          <?php endforeach; ?>
        </fieldset>
        <button type="submit" class="btn btn-primary btn-block" id="confirm-btn">Confirm booking</button>
        <p class="muted" style="font-size: 13px">Free cancellation up to <?= CANCEL_HOURS ?> hours before your slot. Online payments are confirmed by our staff.</p>
      </aside>
    </form>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
