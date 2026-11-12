<?php
require __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$types = ['Indoor', 'Outdoor'];
$statuses = ['available' => 'Available', 'maintenance' => 'Maintenance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['court_type'] ?? '';
    $rate = $_POST['hourly_rate'] ?? '';
    $status = $_POST['status'] ?? 'available';
    $valid = $name !== '' && mb_strlen($name) <= 50 && in_array($type, $types, true)
          && is_numeric($rate) && (float) $rate >= 0 && isset($statuses[$status]);

    if (!$valid) {
        flash('error', 'Please fill in a name, type, valid rate and status.');
    } elseif (($_POST['action'] ?? '') === 'add') {
        $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM courts')->fetchColumn();
        $pdo->prepare('INSERT INTO courts (name, court_type, hourly_rate, status, sort_order) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $type, (float) $rate, $status, $next]);
        flash('success', "$name added.");
    } else {
        $pdo->prepare('UPDATE courts SET name = ?, court_type = ?, hourly_rate = ?, status = ? WHERE id = ?')
            ->execute([$name, $type, (float) $rate, $status, (int) ($_POST['id'] ?? 0)]);
        flash('success', "$name saved.");
    }
    redirect('courts.php');
}

$courts = $pdo->query('SELECT * FROM courts ORDER BY sort_order, id')->fetchAll();

$pageTitle = 'Courts & rates';
$adminPage = 'courts';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-top">
  <div><h1>Courts &amp; rates</h1><p class="muted">Set a court to "Maintenance" to stop new bookings on it.</p></div>
</div>

<section class="panel">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Court</th><th>Type</th><th>Rate per hour (₱)</th><th>Status</th><th><span class="sr-only">Save</span></th></tr></thead>
      <tbody>
      <?php foreach ($courts as $c): $fid = 'court-' . (int) $c['id']; ?>
        <tr>
          <td><input form="<?= $fid ?>" aria-label="Court name" class="field-input" type="text" name="name" value="<?= e($c['name']) ?>" required style="min-height: 40px; padding: 6px 10px; border: 1px solid #a1a1aa; border-radius: 6px; font: inherit"></td>
          <td><select form="<?= $fid ?>" aria-label="Court type" name="court_type" style="min-height: 40px; padding: 6px 10px; border: 1px solid #a1a1aa; border-radius: 6px; font: inherit">
            <?php foreach ($types as $t): ?><option <?= $c['court_type'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></td>
          <td><input form="<?= $fid ?>" aria-label="Hourly rate" type="number" name="hourly_rate" min="0" step="1" value="<?= e((float) $c['hourly_rate']) ?>" required style="min-height: 40px; width: 120px; padding: 6px 10px; border: 1px solid #a1a1aa; border-radius: 6px; font: inherit"></td>
          <td><select form="<?= $fid ?>" aria-label="Status" name="status" style="min-height: 40px; padding: 6px 10px; border: 1px solid #a1a1aa; border-radius: 6px; font: inherit">
            <?php foreach ($statuses as $v => $l): ?><option value="<?= $v ?>" <?= $c['status'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></td>
          <td>
            <form method="post" id="<?= $fid ?>"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button type="submit" class="btn btn-sm btn-primary">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="panel" style="max-width: 720px">
  <h2 style="font-size: 18px">Add a court</h2>
  <form method="post" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label class="field">Name<input type="text" name="name" required placeholder="Court 5"></label>
    <label class="field">Type<select name="court_type"><?php foreach ($types as $t): ?><option><?= $t ?></option><?php endforeach; ?></select></label>
    <label class="field">Rate per hour (₱)<input type="number" name="hourly_rate" min="0" step="1" required></label>
    <label class="field">Status<select name="status"><?php foreach ($statuses as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?></select></label>
    <button type="submit" class="btn btn-primary full">Add court</button>
  </form>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
