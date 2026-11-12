<?php
require __DIR__ . '/includes/functions.php';

$pdo = db();
$packages = $pdo->query('SELECT * FROM event_packages ORDER BY sort_order, id')->fetchAll();
$eventTypes = ['Tournament', 'Team building', 'Birthday / private party', 'Clinic / training', 'Other'];
$errors = [];
$old = ['name' => '', 'organization' => '', 'email' => '', 'phone' => '', 'event_type' => $eventTypes[0],
        'package_id' => (int) ($_GET['package'] ?? 0), 'preferred_date' => '', 'guests' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($old as $k => $v) {
        $old[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $old['package_id'] = (int) $old['package_id'];
    $packageIds = array_map(fn($p) => (int) $p['id'], $packages);

    if ($old['name'] === '' || mb_strlen($old['name']) > 100) $errors[] = 'Please enter your full name.';
    if (mb_strlen($old['organization']) > 100) $errors[] = 'Organization name is too long.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $old['phone'])) $errors[] = 'Please enter a valid mobile number.';
    if (!in_array($old['event_type'], $eventTypes, true)) $errors[] = 'Please choose an event type.';
    if ($old['package_id'] && !in_array($old['package_id'], $packageIds, true)) $errors[] = 'Please choose a package.';
    if (!valid_date($old['preferred_date']) || $old['preferred_date'] < date('Y-m-d')) $errors[] = 'Please choose a date from today onwards.';
    if ((int) $old['guests'] < 1 || (int) $old['guests'] > 500) $errors[] = 'Please enter the number of guests (1–500).';
    if (mb_strlen($old['message']) > 2000) $errors[] = 'Message is too long (2000 characters max).';

    if (!$errors) {
        $pdo->prepare(
            'INSERT INTO event_inquiries (full_name, organization, email, phone, event_type, package_id, preferred_date, guests, message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$old['name'], $old['organization'] ?: null, $old['email'], $old['phone'], $old['event_type'],
                    $old['package_id'] ?: null, $old['preferred_date'], (int) $old['guests'], $old['message'] ?: null]);
        flash('success', 'Thanks, ' . $old['name'] . '! We received your inquiry and will get back to you within 24 hours.');
        redirect('rentals.php#inquiry');
    }
}

$pageTitle = 'Event Rentals — ' . SITE_NAME;
$activePage = 'rentals';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="container">
    <h1>Event Rentals</h1>
    <p>Host tournaments, company team building, birthdays or private clinics. Rent a few courts or the whole venue.</p>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <div class="section-head"><h2>Packages</h2></div>
    <div class="card-grid">
      <?php foreach ($packages as $p): ?>
        <article class="package <?= $p['is_popular'] ? 'popular' : '' ?>">
          <?php if ($p['is_popular']): ?><span class="tag tag-lime" style="align-self: flex-start">Most popular</span><?php endif; ?>
          <h3><?= e($p['name']) ?></h3>
          <p class="price"><?= $p['price'] !== null ? money($p['price']) : 'Custom quote' ?></p>
          <ul>
            <?php foreach (preg_split('/\r?\n|\\\\n/', $p['features']) as $f): if (trim($f) === '') continue; ?>
              <li><?= e($f) ?></li>
            <?php endforeach; ?>
          </ul>
          <a href="rentals.php?package=<?= (int) $p['id'] ?>#inquiry" class="btn <?= $p['is_popular'] ? 'btn-primary' : 'btn-outline' ?>">Choose <?= e($p['name']) ?></a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="inquiry" style="background: var(--mint)">
  <div class="container">
    <?= render_flash() ?>
    <?php if ($errors): ?>
      <div class="alert alert-error" role="alert"><?= implode('<br>', array_map('e', $errors)) ?></div>
    <?php endif; ?>
    <div class="inquiry-layout">
      <div class="inquiry-intro">
        <h2 style="font-size: 30px">Send an inquiry</h2>
        <p class="muted">Tell us about your event and we'll reply within 24 hours with availability and a quote.</p>
        <p class="muted">Prefer to call? Reach us at <a href="tel:<?= e(preg_replace('/\s+/', '', SITE_PHONE)) ?>"><?= e(SITE_PHONE) ?></a>.</p>
      </div>
      <form method="post" action="rentals.php#inquiry" class="inquiry-form" style="background: var(--white)">
        <?= csrf_field() ?>
        <div class="form-grid">
          <label class="field">Full name<input type="text" name="name" value="<?= e($old['name']) ?>" required maxlength="100" autocomplete="name"></label>
          <label class="field">Organization <span class="hint">(optional)</span><input type="text" name="organization" value="<?= e($old['organization']) ?>" maxlength="100" autocomplete="organization"></label>
          <label class="field">Email<input type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email"></label>
          <label class="field">Mobile number<input type="tel" name="phone" value="<?= e($old['phone']) ?>" required placeholder="09XX XXX XXXX" autocomplete="tel"></label>
          <label class="field">Event type
            <select name="event_type">
              <?php foreach ($eventTypes as $t): ?><option <?= $old['event_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="field">Package
            <select name="package_id">
              <option value="0">Not sure yet</option>
              <?php foreach ($packages as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $old['package_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="field">Preferred date<input type="date" name="preferred_date" value="<?= e($old['preferred_date']) ?>" min="<?= date('Y-m-d') ?>" required></label>
          <label class="field">Number of guests<input type="number" name="guests" value="<?= e($old['guests']) ?>" min="1" max="500" required></label>
          <label class="field full">Message <span class="hint">(optional — anything we should know?)</span><textarea name="message" rows="4" maxlength="2000"><?= e($old['message']) ?></textarea></label>
          <button type="submit" class="btn btn-primary full">Send inquiry</button>
        </div>
      </form>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
