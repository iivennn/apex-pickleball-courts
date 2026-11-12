<?php
require __DIR__ . '/includes/functions.php';

$pdo = db();
$today = date('Y-m-d');
$levels = ['Beginner', 'Intermediate', 'Advanced', 'All levels'];
$errors = [];
$openSession = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $sessionId = (int) ($_POST['session_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $openSession = $sessionId;

    $stmt = $pdo->prepare('SELECT * FROM open_play_sessions WHERE id = ? AND session_date >= ?');
    $stmt->execute([$sessionId, $today]);
    $session = $stmt->fetch();

    if (!$session) {
        $errors[] = 'That session is no longer available.';
    }
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Please enter your full name.';
    }
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $errors[] = 'Please enter a valid mobile number.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$errors) {
        $count = $pdo->prepare("SELECT COUNT(*) FROM open_play_signups WHERE session_id = ? AND status = 'joined'");
        $count->execute([$sessionId]);
        $status = (int) $count->fetchColumn() < (int) $session['capacity'] ? 'joined' : 'waitlist';
        try {
            $pdo->prepare('INSERT INTO open_play_signups (session_id, full_name, phone, email, status) VALUES (?, ?, ?, ?, ?)')
                ->execute([$sessionId, $name, $phone, $email, $status]);
            flash('success', $status === 'joined'
                ? "You're in! See you at {$session['title']} on " . fmt_date($session['session_date'], 'l, M j') . ' at ' . fmt_time($session['start_time']) . '. Pay ' . money($session['price']) . ' at the front desk.'
                : "That session is full, so you're on the waitlist for {$session['title']}. We'll text you if a spot opens up.");
            redirect('open-play.php' . (isset($_GET['level']) ? '?level=' . urlencode($_GET['level']) : '') . '#session-' . $sessionId);
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $errors[] = 'This email is already signed up for that session.';
            } else {
                throw $ex;
            }
        }
    }
}

$level = in_array($_GET['level'] ?? '', $levels, true) ? $_GET['level'] : '';
$sql = "SELECT s.*,
          (SELECT COUNT(*) FROM open_play_signups u WHERE u.session_id = s.id AND u.status = 'joined') AS joined
        FROM open_play_sessions s
        WHERE s.session_date >= ?" . ($level ? ' AND s.skill_level = ?' : '') . '
        ORDER BY s.session_date, s.start_time';
$stmt = $pdo->prepare($sql);
$stmt->execute($level ? [$today, $level] : [$today]);
$sessions = $stmt->fetchAll();

$pageTitle = 'Open Play — ' . SITE_NAME;
$activePage = 'openplay';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="container">
    <h1>Open Play</h1>
    <p>No partner? No problem. Join a session, get grouped with players at your level, and rotate through games.</p>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <?= render_flash() ?>
    <?php if ($errors): ?>
      <div class="alert alert-error" role="alert"><?= implode('<br>', array_map('e', $errors)) ?></div>
    <?php endif; ?>

    <nav class="filter-pills" aria-label="Filter by skill level">
      <a href="open-play.php" class="<?= $level === '' ? 'active' : '' ?>">All sessions</a>
      <?php foreach ($levels as $l): ?>
        <a href="open-play.php?level=<?= urlencode($l) ?>" class="<?= $level === $l ? 'active' : '' ?>"><?= e($l) ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if (!$sessions): ?>
      <div class="empty">No upcoming sessions<?= $level ? ' for ' . e($level) . ' players' : '' ?> yet. Check back soon or follow us on Facebook for updates.</div>
    <?php else: ?>
      <div class="session-list">
        <?php foreach ($sessions as $s):
          $joined = (int) $s['joined'];
          $cap = max(1, (int) $s['capacity']);
          $full = $joined >= $cap;
          $pct = min(100, round($joined / $cap * 100)); ?>
          <article class="session" id="session-<?= (int) $s['id'] ?>">
            <div class="session-row">
              <div class="session-date"><small><?= e(strtoupper(fmt_date($s['session_date'], 'D'))) ?></small><strong><?= e(fmt_date($s['session_date'], 'j')) ?></strong><small><?= e(fmt_date($s['session_date'], 'M')) ?></small></div>
              <div class="session-info">
                <strong><?= e($s['title']) ?></strong>
                <span class="muted"><?= e(fmt_time($s['start_time'])) ?> – <?= e(fmt_time($s['end_time'])) ?> · <?= e($s['courts_label']) ?></span>
              </div>
              <span class="tag <?= level_class($s['skill_level']) ?>"><?= e($s['skill_level']) ?></span>
              <div class="capacity">
                <span><?= $full ? 'Full' : $joined . ' of ' . $cap . ' spots taken' ?></span>
                <div class="bar" role="img" aria-label="<?= $pct ?>% full"><i style="width: <?= $pct ?>%"></i></div>
              </div>
              <strong><?= money($s['price']) ?></strong>
            </div>
            <details <?= $openSession === (int) $s['id'] ? 'open' : '' ?>>
              <summary class="join-toggle" style="padding: 0 20px 20px; display: flex; justify-content: flex-end">
                <span class="btn <?= $full ? 'btn-outline' : 'btn-primary' ?>"><?= $full ? 'Join waitlist' : 'Join session' ?></span>
              </summary>
              <form method="post" action="open-play.php<?= $level ? '?level=' . urlencode($level) : '' ?>" class="join-form">
                <?= csrf_field() ?>
                <input type="hidden" name="session_id" value="<?= (int) $s['id'] ?>">
                <div class="form-grid">
                  <label class="field">Full name<input type="text" name="name" required maxlength="100" autocomplete="name"></label>
                  <label class="field">Mobile number<input type="tel" name="phone" required placeholder="09XX XXX XXXX" autocomplete="tel"></label>
                  <label class="field">Email<input type="email" name="email" required autocomplete="email"></label>
                  <div class="field" style="justify-content: flex-end"><button type="submit" class="btn btn-primary"><?= $full ? 'Add me to the waitlist' : 'Confirm my spot' ?></button></div>
                </div>
              </form>
            </details>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="info-band">
      <div><strong>Who can join?</strong><span class="muted">Anyone — solo players are welcome. Pick a session that matches your level.</span></div>
      <div><strong>What to bring</strong><span class="muted">Paddle and court shoes. Paddle rentals are available at the front desk.</span></div>
      <div><strong>How games rotate</strong><span class="muted">Players queue with a paddle stack. Games go to 11, winners split up and losers move to the next court.</span></div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
