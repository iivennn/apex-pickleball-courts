</main>
<footer class="site-footer" id="contact">
  <div class="container footer-grid">
    <div class="footer-col">
      <img src="assets/img/logo.png" alt="<?= e(SITE_NAME) ?>" class="footer-logo">
      <p><?= e(SITE_ADDRESS) ?></p>
    </div>
    <div class="footer-col">
      <h2>Hours</h2>
      <p><?= e(SITE_HOURS) ?></p>
    </div>
    <div class="footer-col">
      <h2>Contact</h2>
      <p><a href="tel:<?= e(preg_replace('/\s+/', '', SITE_PHONE)) ?>"><?= e(SITE_PHONE) ?></a><br>
         <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a></p>
      <p><a href="<?= e(SITE_FACEBOOK) ?>">Facebook</a> · <a href="<?= e(SITE_INSTAGRAM) ?>">Instagram</a></p>
    </div>
    <?php if (SITE_MAP_EMBED !== ''): ?>
      <div class="footer-col">
        <iframe class="footer-map" src="<?= e(SITE_MAP_EMBED) ?>" loading="lazy" title="Map to <?= e(SITE_NAME) ?>" referrerpolicy="no-referrer-when-downgrade"></iframe>
      </div>
    <?php endif; ?>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
    <a href="admin/login.php">Staff login</a>
  </div>
</footer>
<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>
