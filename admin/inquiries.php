<?php
require __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$statuses = ['new' => 'tag-lime', 'contacted' => 'tag-yellow', 'booked' => 'tag-green', 'closed' => 'tag-gray'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $newStatus = $_POST['status'] ?? '';
    if (isset($statuses[$newStatus])) {
        $pdo->prepare('UPDATE event_inquiries SET status = ? WHERE id = ?')->execute([$newStatus, (int) ($_POST['id'] ?? 0)]);
        flash('success', 'Inquiry updated.');
    }
    redirect('inquiries.php' . (!empty($_POST['f_status']) ? '?status=' . urlencode($_POST['f_status']) : ''));
}

$filter = isset($statuses[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$sql = 'SELECT i.*, p.name AS package_name FROM event_inquiries i LEFT JOIN event_packages p ON p.id = i.package_id'
     . ($filter ? ' WHERE i.status = ?' : '') . ' ORDER BY i.created_at DESC, i.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($filter ? [$filter] : []);
$inquiries = $stmt->fetchAll();

$pageTitle = 'Event inquiries';
$adminPage = 'inquiries';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-top"><h1>Event inquiries</h1></div>

<nav class="filter-pills" aria-label="Filter by status" style="margin: 0">
  <a href="inquiries.php" class="<?= $filter === '' ? 'active' : '' ?>">All</a>
  <?php foreach ($statuses as $s => $_): ?>
    <a href="inquiries.php?status=<?= $s ?>" class="<?= $filter === $s ? 'active' : '' ?>"><?= e(ucfirst($s)) ?></a>
  <?php endforeach; ?>
</nav>

<?php if (!$inquiries): ?>
  <div class="panel"><p class="muted">No inquiries found.</p></div>
<?php endif; ?>

<?php foreach ($inquiries as $i): ?>
  <article class="panel">
    <div class="panel-head">
      <h2><?= e($i['event_type']) ?> — <?= e($i['organization'] ?: $i['full_name']) ?></h2>
      <span class="tag <?= $statuses[$i['status']] ?? 'tag-gray' ?>"><?= e(ucfirst($i['status'])) ?></span>
    </div>
    <div class="form-grid" style="gap: 8px 24px; font-size: 14px">
      <div><span class="muted">Contact person</span><br><strong><?= e($i['full_name']) ?></strong></div>
      <div><span class="muted">Mobile / email</span><br><a href="tel:<?= e($i['phone']) ?>"><?= e($i['phone']) ?></a> · <a href="mailto:<?= e($i['email']) ?>"><?= e($i['email']) ?></a></div>
      <div><span class="muted">Preferred date</span><br><strong><?= e(fmt_date($i['preferred_date'], 'D, M j, Y')) ?></strong></div>
      <div><span class="muted">Package · guests</span><br><strong><?= e($i['package_name'] ?? 'Not sure yet') ?> · <?= (int) $i['guests'] ?> guests</strong></div>
    </div>
    <?php if ($i['message']): ?><p style="background: var(--mint); padding: 12px 14px; border-radius: 8px; white-space: pre-line"><?= e($i['message']) ?></p><?php endif; ?>
    <div class="panel-head">
      <span class="muted" style="font-size: 13px">Received <?= e(date('M j, Y g:i A', strtotime($i['created_at']))) ?></span>
      <form method="post" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $i['id'] ?>"><input type="hidden" name="f_status" value="<?= e($filter) ?>">
        <label for="st-<?= (int) $i['id'] ?>" class="muted" style="font-size: 14px">Status</label>
        <select name="status" id="st-<?= (int) $i['id'] ?>">
          <?php foreach ($statuses as $s => $_): ?><option value="<?= $s ?>" <?= $i['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary">Update</button>
      </form>
    </div>
  </article>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
