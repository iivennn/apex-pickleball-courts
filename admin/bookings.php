<?php
require __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $updates = [
        'checkin' => ["status = 'checked_in'", 'Booking marked as checked in.'],
        'complete' => ["status = 'completed'", 'Booking marked as completed.'],
        'paid' => ["payment_status = 'paid'", 'Payment recorded.'],
        'unpaid' => ["payment_status = 'unpaid'", 'Booking marked as unpaid.'],
    ];
    if (isset($updates[$action])) {
        $pdo->prepare("UPDATE bookings SET {$updates[$action][0]} WHERE id = ? AND status <> 'cancelled'")->execute([$id]);
        flash('success', $updates[$action][1]);
    } elseif ($action === 'cancel') {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        $pdo->prepare('DELETE FROM booking_slots WHERE booking_id = ?')->execute([$id]); // frees the time slots
        $pdo->commit();
        flash('success', 'Booking cancelled. Its time slots are open again.');
    }
    redirect('bookings.php?' . http_build_query(array_filter([
        'date' => $_POST['f_date'] ?? '', 'status' => $_POST['f_status'] ?? '', 'q' => $_POST['f_q'] ?? '',
    ])));
}

// ---------- Filters ----------
$date = $_GET['date'] ?? '';
$date = valid_date($date) ? $date : '';
$status = in_array($_GET['status'] ?? '', ['confirmed', 'checked_in', 'completed', 'cancelled'], true) ? $_GET['status'] : '';
$q = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($date) {
    $where[] = 'b.booking_date = ?';
    $params[] = $date;
} elseif ($q === '') {
    $where[] = 'b.booking_date >= ?';          // default view: upcoming bookings
    $params[] = date('Y-m-d');
}
if ($status) {
    $where[] = 'b.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(b.customer_name LIKE ? OR b.phone LIKE ? OR b.email LIKE ? OR b.reference LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
$sql = 'SELECT b.*, c.name AS court_name FROM bookings b JOIN courts c ON c.id = b.court_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY b.booking_date, b.start_hour, c.sort_order LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$statusTags = ['confirmed' => 'tag-green', 'checked_in' => 'tag-lime', 'completed' => 'tag-gray', 'cancelled' => 'tag-red'];
$keep = fn() => '<input type="hidden" name="f_date" value="' . e($date) . '"><input type="hidden" name="f_status" value="' . e($status) . '"><input type="hidden" name="f_q" value="' . e($q) . '">';

function action_button(int $id, string $action, string $label, string $class, callable $keep, string $confirm = ''): string
{
    $onsubmit = $confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '';
    return '<form method="post"' . $onsubmit . '>' . csrf_field() . $keep()
        . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . e($action) . '">'
        . '<button type="submit" class="btn btn-sm ' . $class . '">' . e($label) . '</button></form>';
}

$pageTitle = 'Bookings';
$adminPage = 'bookings';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-top">
  <h1>Bookings</h1>
  <a href="../book.php" target="_blank" rel="noopener" class="btn btn-primary">+ Walk-in booking</a>
</div>

<form method="get" class="panel filters">
  <label class="field">Date<input type="date" name="date" value="<?= e($date) ?>"></label>
  <label class="field">Status
    <select name="status">
      <option value="">Any status</option>
      <?php foreach ($statusTags as $s => $_): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="field" style="flex: 1">Search<input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, phone, email or reference"></label>
  <button type="submit" class="btn btn-primary">Filter</button>
  <a href="bookings.php" class="btn btn-outline">Reset</a>
</form>

<section class="panel">
  <div class="panel-head">
    <h2><?= $date ? e(fmt_date($date, 'l, F j')) : ($q !== '' ? 'Search results' : 'Upcoming bookings') ?></h2>
    <span class="muted" style="font-size: 14px"><?= count($bookings) ?> booking<?= count($bookings) === 1 ? '' : 's' ?></span>
  </div>
  <?php if (!$bookings): ?>
    <p class="muted">No bookings found.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table" style="min-width: 900px">
      <thead><tr><th>Reference</th><th>Date &amp; time</th><th>Court</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
        <?php foreach ($bookings as $b): $id = (int) $b['id']; $active = $b['status'] !== 'cancelled'; ?>
          <tr>
            <td><strong><?= e($b['reference']) ?></strong></td>
            <td><?= e(fmt_date($b['booking_date'], 'D, M j')) ?><br><span class="muted"><?= e($b['time_label']) ?></span></td>
            <td><?= e($b['court_name']) ?></td>
            <td><?= e($b['customer_name']) ?><br><span class="muted"><?= e($b['phone']) ?> · <?= (int) $b['players'] ?> players</span></td>
            <td><?= money($b['total']) ?></td>
            <td><?= e(payment_label($b['payment_method'])) ?><br><span class="tag <?= $b['payment_status'] === 'paid' ? 'tag-green' : 'tag-orange' ?>"><?= $b['payment_status'] === 'paid' ? 'Paid' : 'Unpaid' ?></span></td>
            <td><span class="tag <?= $statusTags[$b['status']] ?? 'tag-gray' ?>"><?= e(ucwords(str_replace('_', ' ', $b['status']))) ?></span></td>
            <td>
              <?php if ($active): ?>
              <div class="actions">
                <?php if ($b['payment_status'] !== 'paid'): ?><?= action_button($id, 'paid', 'Mark paid', 'btn-outline', $keep) ?><?php endif; ?>
                <?php if ($b['status'] === 'confirmed'): ?><?= action_button($id, 'checkin', 'Check in', 'btn-primary', $keep) ?><?php endif; ?>
                <?php if ($b['status'] === 'checked_in'): ?><?= action_button($id, 'complete', 'Complete', 'btn-outline', $keep) ?><?php endif; ?>
                <?= action_button($id, 'cancel', 'Cancel', 'btn-danger', $keep, 'Cancel booking ' . $b['reference'] . '? This frees its time slots.') ?>
              </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
