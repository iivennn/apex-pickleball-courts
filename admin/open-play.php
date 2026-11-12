<?php
require __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$levels = ['Beginner', 'Intermediate', 'Advanced', 'All levels'];
$errors = [];
$form = ['title' => '', 'session_date' => date('Y-m-d', strtotime('+1 day')), 'start_time' => '07:00', 'end_time' => '10:00',
         'courts_label' => 'Courts 1–2', 'skill_level' => 'All levels', 'capacity' => 16, 'price' => ''];

/** When a player cancels, move the earliest waitlisted player into the session. */
function promote_waitlist(PDO $pdo, int $sessionId): void
{
    $cap = $pdo->prepare('SELECT capacity FROM open_play_sessions WHERE id = ?');
    $cap->execute([$sessionId]);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM open_play_signups WHERE session_id = ? AND status = 'joined'");
    $cnt->execute([$sessionId]);
    if ((int) $cnt->fetchColumn() < (int) $cap->fetchColumn()) {
        $next = $pdo->prepare("SELECT id FROM open_play_signups WHERE session_id = ? AND status = 'waitlist' ORDER BY created_at, id LIMIT 1");
        $next->execute([$sessionId]);
        if ($id = $next->fetchColumn()) {
            $pdo->prepare("UPDATE open_play_signups SET status = 'joined' WHERE id = ?")->execute([$id]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        foreach ($form as $k => $v) {
            $form[$k] = trim((string) ($_POST[$k] ?? ''));
        }
        if ($form['title'] === '') $errors[] = 'Title is required.';
        if (!valid_date($form['session_date'])) $errors[] = 'Please choose a valid date.';
        if (!preg_match('/^\d{2}:\d{2}$/', $form['start_time']) || !preg_match('/^\d{2}:\d{2}$/', $form['end_time']) || $form['end_time'] <= $form['start_time']) $errors[] = 'End time must be after start time.';
        if ($form['courts_label'] === '') $errors[] = 'Courts are required (e.g. "Courts 1–2").';
        if (!in_array($form['skill_level'], $levels, true)) $errors[] = 'Choose a skill level.';
        if ((int) $form['capacity'] < 1) $errors[] = 'Capacity must be at least 1.';
        if (!is_numeric($form['price']) || (float) $form['price'] < 0) $errors[] = 'Enter a valid price.';
        if (!$errors) {
            $pdo->prepare('INSERT INTO open_play_sessions (title, session_date, start_time, end_time, courts_label, skill_level, capacity, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$form['title'], $form['session_date'], $form['start_time'], $form['end_time'], $form['courts_label'], $form['skill_level'], (int) $form['capacity'], (float) $form['price']]);
            flash('success', 'Session "' . $form['title'] . '" created.');
            redirect('open-play.php');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM open_play_signups WHERE session_id = ?')->execute([(int) $_POST['id']]);
        $pdo->prepare('DELETE FROM open_play_sessions WHERE id = ?')->execute([(int) $_POST['id']]);
        flash('success', 'Session deleted.');
        redirect('open-play.php');
    } elseif ($action === 'cancel_signup') {
        $stmt = $pdo->prepare('SELECT session_id FROM open_play_signups WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        if ($sid = (int) $stmt->fetchColumn()) {
            $pdo->prepare("UPDATE open_play_signups SET status = 'cancelled' WHERE id = ?")->execute([(int) $_POST['id']]);
            promote_waitlist($pdo, $sid);
            flash('success', 'Player removed. The next person on the waitlist (if any) got the spot.');
            redirect('open-play.php?session=' . $sid);
        }
        redirect('open-play.php');
    }
}

// ---------- One session's player list ----------
$viewId = (int) ($_GET['session'] ?? 0);
$viewSession = null;
$signups = [];
if ($viewId) {
    $stmt = $pdo->prepare('SELECT * FROM open_play_sessions WHERE id = ?');
    $stmt->execute([$viewId]);
    $viewSession = $stmt->fetch();
    if ($viewSession) {
        $stmt = $pdo->prepare("SELECT * FROM open_play_signups WHERE session_id = ?
                               ORDER BY CASE status WHEN 'joined' THEN 0 WHEN 'waitlist' THEN 1 ELSE 2 END, created_at, id");
        $stmt->execute([$viewId]);
        $signups = $stmt->fetchAll();
    }
}

$showPast = isset($_GET['past']);
$stmt = $pdo->prepare("SELECT s.*,
        (SELECT COUNT(*) FROM open_play_signups u WHERE u.session_id = s.id AND u.status = 'joined') AS joined,
        (SELECT COUNT(*) FROM open_play_signups u WHERE u.session_id = s.id AND u.status = 'waitlist') AS waiting
      FROM open_play_sessions s WHERE s.session_date " . ($showPast ? '<' : '>=') . ' ?
      ORDER BY s.session_date ' . ($showPast ? 'DESC' : 'ASC') . ', s.start_time LIMIT 100');
$stmt->execute([date('Y-m-d')]);
$sessions = $stmt->fetchAll();

$pageTitle = 'Open play sessions';
$adminPage = 'openplay';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-top"><h1>Open play sessions</h1></div>

<?php if ($viewSession): ?>
<section class="panel">
  <div class="panel-head">
    <h2><?= e($viewSession['title']) ?> — <?= e(fmt_date($viewSession['session_date'], 'D, M j')) ?>, <?= e(fmt_time($viewSession['start_time'])) ?></h2>
    <a href="open-play.php" style="font-weight: 600; font-size: 14px">← All sessions</a>
  </div>
  <?php if (!$signups): ?>
    <p class="muted">No one has signed up yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>#</th><th>Name</th><th>Mobile</th><th>Email</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($signups as $n => $u): ?>
        <tr>
          <td><?= $n + 1 ?></td>
          <td><strong><?= e($u['full_name']) ?></strong></td>
          <td><?= e($u['phone']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="tag <?= ['joined' => 'tag-green', 'waitlist' => 'tag-yellow', 'cancelled' => 'tag-red'][$u['status']] ?? 'tag-gray' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
          <td>
            <?php if ($u['status'] !== 'cancelled'): ?>
              <form method="post" onsubmit="return confirm('Remove this player?')"><?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel_signup"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Remove</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<div class="two-col">
  <section class="panel wide">
    <div class="panel-head">
      <h2><?= $showPast ? 'Past sessions' : 'Upcoming sessions' ?></h2>
      <a href="open-play.php<?= $showPast ? '' : '?past=1' ?>" style="font-weight: 600; font-size: 14px"><?= $showPast ? 'Show upcoming' : 'Show past' ?></a>
    </div>
    <?php if (!$sessions): ?>
      <p class="muted">No sessions to show.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Date &amp; time</th><th>Session</th><th>Level</th><th>Players</th><th>Price</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($sessions as $s): ?>
          <tr>
            <td><?= e(fmt_date($s['session_date'], 'D, M j')) ?><br><span class="muted"><?= e(fmt_time($s['start_time'])) ?> – <?= e(fmt_time($s['end_time'])) ?></span></td>
            <td><strong><?= e($s['title']) ?></strong><br><span class="muted"><?= e($s['courts_label']) ?></span></td>
            <td><span class="tag <?= level_class($s['skill_level']) ?>"><?= e($s['skill_level']) ?></span></td>
            <td><?= (int) $s['joined'] ?>/<?= (int) $s['capacity'] ?><?php if ($s['waiting']): ?><br><span class="muted">+<?= (int) $s['waiting'] ?> waitlist</span><?php endif; ?></td>
            <td><?= money($s['price']) ?></td>
            <td>
              <div class="actions">
                <a class="btn btn-sm btn-outline" href="open-play.php?session=<?= (int) $s['id'] ?>">Players</a>
                <form method="post" onsubmit="return confirm('Delete this session and its sign-ups?')"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <section class="panel narrow" aria-label="New session">
    <h2 style="font-size: 18px">Add a session</h2>
    <?php if ($errors): ?><div class="alert alert-error" role="alert" style="margin: 0"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
    <form method="post" style="display: flex; flex-direction: column; gap: 12px">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <label class="field">Title<input type="text" name="title" value="<?= e($form['title']) ?>" required placeholder="Evening Open Play"></label>
      <label class="field">Date<input type="date" name="session_date" value="<?= e($form['session_date']) ?>" required></label>
      <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px">
        <label class="field">Start<input type="time" name="start_time" value="<?= e($form['start_time']) ?>" required></label>
        <label class="field">End<input type="time" name="end_time" value="<?= e($form['end_time']) ?>" required></label>
      </div>
      <label class="field">Courts<input type="text" name="courts_label" value="<?= e($form['courts_label']) ?>" required></label>
      <label class="field">Skill level
        <select name="skill_level"><?php foreach ($levels as $l): ?><option <?= $form['skill_level'] === $l ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      </label>
      <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px">
        <label class="field">Max players<input type="number" name="capacity" value="<?= e($form['capacity']) ?>" min="1" required></label>
        <label class="field">Price (₱)<input type="number" name="price" value="<?= e($form['price']) ?>" min="0" step="1" required></label>
      </div>
      <button type="submit" class="btn btn-primary">Create session</button>
    </form>
  </section>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
