<!-- ── Footer ─────────────────────────────────────────── -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">Jeme<span>lo</span></div>
        <p>Your trusted online marketplace for electronics, fashion, books, and more. Quality products at great prices.</p>
        <div class="d-flex gap-12 mt-16">
          <a href="#" class="nav-icon-btn" style="color:rgba(255,255,255,.5);width:36px;height:36px;background:rgba(255,255,255,.08);border-radius:8px">
            <i class="fab fa-facebook-f"></i>
          </a>
          <a href="#" class="nav-icon-btn" style="color:rgba(255,255,255,.5);width:36px;height:36px;background:rgba(255,255,255,.08);border-radius:8px">
            <i class="fab fa-twitter"></i>
          </a>
          <a href="#" class="nav-icon-btn" style="color:rgba(255,255,255,.5);width:36px;height:36px;background:rgba(255,255,255,.08);border-radius:8px">
            <i class="fab fa-instagram"></i>
          </a>
        </div>
      </div>

      <div>
        <h4>Shop</h4>
        <ul class="footer-links">
          <li><a href="<?= SITE_URL ?>/customer/index.php">All Products</a></li>
          <?php foreach (($nav_categories ?? []) as $c): ?>
          <li><a href="<?= SITE_URL ?>/customer/index.php?category=<?= $c['id'] ?>"><?= e($c['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div>
        <h4>Account</h4>
        <ul class="footer-links">
          <?php
          $footer_is_rider = is_logged_in() && ($_SESSION['user_role'] ?? '') === 'rider';
          if (is_logged_in()):
            if ($footer_is_rider): ?>
          <li><a href="<?= SITE_URL ?>/rider/index.php">Rider Dashboard</a></li>
          <li><a href="<?= SITE_URL ?>/rider/profile.php">My Profile</a></li>
            <?php else: ?>
          <li><a href="<?= SITE_URL ?>/customer/profile.php">My Profile</a></li>
          <li><a href="<?= SITE_URL ?>/customer/orders.php">My Orders</a></li>
          <li><a href="<?= SITE_URL ?>/customer/cart.php">Shopping Cart</a></li>
            <?php endif; ?>
          <li><a href="<?= SITE_URL ?>/logout.php">Sign Out</a></li>
          <?php else: ?>
          <li><a href="<?= SITE_URL ?>/login.php">Login</a></li>
          <li><a href="<?= SITE_URL ?>/register.php">Register</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <div>
        <h4>Contact</h4>
        <ul class="footer-links">
          <li><a href="#"><i class="fas fa-envelope" style="margin-right:6px"></i>jemuelbruzonravelo10@gmail.com</a></li>
          <li><a href="#"><i class="fas fa-phone" style="margin-right:6px"></i>0985006822</a></li>
          <li><a href="#"><i class="fas fa-map-marker-alt" style="margin-right:6px"></i>Cantilan Surigao del Sur, Philippines</a></li>
        </ul>
        <div style="margin-top:16px;font-size:.8rem;color:rgba(255,255,255,.35)">
          <i class="fas fa-shield-alt" style="margin-right:4px;color:var(--primary)"></i>
          Secure &amp; encrypted checkout
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> Jemz. All rights reserved.</span>
      <span>Made with <i class="fas fa-heart" style="color:var(--primary)"></i> for the best shopping experience</span>
    </div>
  </div>
</footer>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>

<!-- ── Cookie Consent Banner ───────────────────────────────── -->
<div id="cookie-banner" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:8888;background:#0f172a;color:#e2e8f0;padding:16px 24px;box-shadow:0 -4px 20px rgba(0,0,0,.35);animation:cookieSlideUp .4s ease both">
  <style>
  @keyframes cookieSlideUp { from { transform:translateY(100%); opacity:0; } to { transform:translateY(0); opacity:1; } }
  .cookie-inner { max-width:1200px;margin:0 auto;display:flex;align-items:center;flex-wrap:wrap;gap:14px 24px; }
  .cookie-text  { flex:1;min-width:260px;font-size:.84rem;line-height:1.55;color:#cbd5e1; }
  .cookie-text a { color:#60a5fa;text-decoration:underline; }
  .cookie-btns  { display:flex;gap:10px;flex-shrink:0;flex-wrap:wrap; }
  .cookie-accept { padding:9px 22px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.84rem;cursor:pointer;transition:background .2s; }
  .cookie-accept:hover { background:#1d4ed8; }
  .cookie-decline { padding:9px 18px;background:transparent;color:#94a3b8;border:1.5px solid #334155;border-radius:8px;font-weight:500;font-size:.84rem;cursor:pointer;transition:all .2s; }
  .cookie-decline:hover { border-color:#64748b;color:#e2e8f0; }
  </style>
  <div class="cookie-inner">
    <div class="cookie-text">
      <i class="fas fa-cookie-bite" style="color:#f59e0b;margin-right:6px"></i>
      We use cookies to keep you signed in, remember your preferences, and improve your experience.
      By continuing to use Jemelo, you agree to our use of cookies.
    </div>
    <div class="cookie-btns">
      <button class="cookie-accept" id="cookie-accept-btn">Accept All</button>
      <button class="cookie-decline" id="cookie-decline-btn">Decline</button>
    </div>
  </div>
</div>

<script>
(function() {
  var COOKIE_NAME = 'se_cookie_consent';
  var COOKIE_DAYS = 365;

  function getCookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }
  function setCookie(name, value, days) {
    var exp = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + exp + '; path=/; SameSite=Strict';
  }
  function hideBanner() {
    var b = document.getElementById('cookie-banner');
    if (b) { b.style.animation = 'cookieSlideUp .3s ease reverse both'; setTimeout(function() { b.style.display = 'none'; }, 300); }
  }

  var consent = getCookie(COOKIE_NAME);
  if (!consent) {
    setTimeout(function() {
      var b = document.getElementById('cookie-banner');
      if (b) b.style.display = '';
    }, 1200);
  }

  document.getElementById('cookie-accept-btn').addEventListener('click', function() {
    setCookie(COOKIE_NAME, 'accepted', COOKIE_DAYS);
    hideBanner();
  });
  document.getElementById('cookie-decline-btn').addEventListener('click', function() {
    setCookie(COOKIE_NAME, 'declined', COOKIE_DAYS);
    hideBanner();
  });
})();
</script>
</body>
</html>
