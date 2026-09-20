<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/auth.php';
}
require_once __DIR__ . '/../includes/functions.php';

// Rider browsing mode — session role is preserved, no buying allowed
$is_rider      = is_logged_in() && ($_SESSION['user_role'] ?? '') === 'rider';

$cart_count    = (!$is_rider && is_logged_in()) ? cart_count() : 0;
$chat_unread_c = is_logged_in() ? unread_chat_count($_SESSION['user_id'], 'customer') : 0;
$notif_count_c = is_logged_in() ? unread_notif_count($_SESSION['user_id']) : 0;
$hdr_avatar    = null;
if (is_logged_in()) {
    $hdr_av = getDB()->prepare("SELECT avatar FROM users WHERE id=?");
    $hdr_av->execute([$_SESSION['user_id']]);
    $hdr_avatar = $hdr_av->fetchColumn();
}
$page_title = $page_title ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title><?= e($page_title) ?> — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<script>window.SITE_URL = '<?= SITE_URL ?>';</script>
</head>
<body>

<?php if ($is_rider): ?>
<!-- ── Rider Browse-Mode Banner ───────────────────────── -->
<div style="background:#f59e0b;color:#1c1917;padding:9px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:.84rem;font-weight:600;position:sticky;top:0;z-index:1100;box-shadow:0 1px 4px rgba(0,0,0,.15)">
  <span style="display:flex;align-items:center;gap:8px">
    <i class="fas fa-motorcycle"></i>
    You are browsing as a Rider — buying features are disabled.
  </span>
  <a href="<?= SITE_URL ?>/rider/index.php"
     style="display:inline-flex;align-items:center;gap:6px;background:#1c1917;color:#fff;padding:6px 14px;border-radius:6px;text-decoration:none;font-size:.82rem;white-space:nowrap;transition:background .15s"
     onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#1c1917'">
    <i class="fas fa-arrow-left"></i> Back to Rider Dashboard
  </a>
</div>
<?php endif; ?>

<!-- ── Navbar ─────────────────────────────────────────── -->
<nav class="navbar">
  <div class="container">
    <div class="navbar-inner">
      <button class="mobile-menu-btn" id="search-toggle" aria-label="Search">
        <i class="fas fa-search"></i>
      </button>

      <a href="<?= SITE_URL ?>/customer/index.php" class="navbar-brand">
        <i class="fas fa-shopping-bag"></i>Jeme<span>lo</span>
      </a>

      <form class="navbar-search search-form" action="<?= SITE_URL ?>/customer/index.php" method="GET">
        <input type="text" name="q" placeholder="Search products…" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
        <button type="submit"><i class="fas fa-search"></i></button>
      </form>

      <div class="navbar-actions">
        <!-- Cart — hidden for riders -->
        <?php if (!$is_rider): ?>
        <a href="<?= SITE_URL ?>/customer/cart.php" class="nav-icon-btn" title="Cart">
          <i class="fas fa-shopping-cart"></i>
          <?php if ($cart_count > 0): ?>
          <span class="nav-badge cart-badge"><?= $cart_count ?></span>
          <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if (is_logged_in()): ?>
        <!-- Chat icon -->
        <a href="<?= SITE_URL ?>/customer/chat.php" class="nav-icon-btn" title="Chat Support">
          <i class="fas fa-comments"></i>
          <?php if ($chat_unread_c > 0): ?>
          <span class="nav-badge" style="background:var(--danger)"><?= $chat_unread_c ?></span>
          <?php endif; ?>
        </a>

        <!-- Notification Bell -->
        <div class="notif-bell-wrap" id="notif-wrap-cust">
          <button class="notif-bell-btn" id="notif-bell-cust" title="Notifications" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($notif_count_c > 0): ?>
            <span class="notif-badge" id="notif-badge-cust"><?= $notif_count_c > 99 ? '99+' : $notif_count_c ?></span>
            <?php else: ?>
            <span class="notif-badge" id="notif-badge-cust" style="display:none">0</span>
            <?php endif; ?>
          </button>
          <div class="notif-dropdown" id="notif-dropdown-cust">
            <div class="notif-dropdown-header">
              <span class="notif-dropdown-title"><i class="fas fa-bell"></i> Notifications</span>
              <button class="notif-mark-all" id="notif-mark-all-cust">Mark all read</button>
            </div>
            <div class="notif-list" id="notif-list-cust">
              <div class="notif-skeleton"><div class="notif-skeleton-icon"></div><div class="notif-skeleton-lines"><span></span><span></span></div></div>
              <div class="notif-skeleton"><div class="notif-skeleton-icon"></div><div class="notif-skeleton-lines"><span></span><span></span></div></div>
            </div>
            <div class="notif-dropdown-footer">
              <a href="<?= SITE_URL ?>/customer/notifications.php">View all notifications</a>
            </div>
          </div>
        </div>

        <!-- User dropdown -->
        <div class="dropdown">
          <button class="nav-user-btn" data-dropdown-toggle="user-menu">
            <div class="nav-avatar">
              <img src="<?= avatar_url($hdr_avatar) ?>" alt="avatar" class="avatar-img">
            </div>
            <span class="d-none-mobile"><?= e(explode(' ', $_SESSION['user_name'])[0]) ?></span>
            <i class="fas fa-chevron-down" style="font-size:.65rem;opacity:.6"></i>
          </button>
          <div class="dropdown-menu" id="user-menu">
            <?php if ($is_rider): ?>
            <!-- Rider-specific menu -->
            <a href="<?= SITE_URL ?>/rider/profile.php"><i class="fas fa-user-cog"></i> My Profile</a>
            <div class="dropdown-divider"></div>
            <a href="<?= SITE_URL ?>/rider/index.php"><i class="fas fa-motorcycle"></i> Rider Dashboard</a>
            <div class="dropdown-divider"></div>
            <?php else: ?>
            <!-- Customer menu -->
            <a href="<?= SITE_URL ?>/customer/profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="<?= SITE_URL ?>/customer/orders.php"><i class="fas fa-box"></i> My Orders</a>
            <a href="<?= SITE_URL ?>/customer/chat.php"><i class="fas fa-comments"></i> Chat Support</a>
            <?php if (is_admin()): ?>
            <div class="dropdown-divider"></div>
            <a href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-tachometer-alt"></i> Admin Panel</a>
            <?php endif; ?>
            <div class="dropdown-divider"></div>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
          </div>
        </div>
        <?php else: ?>
        <button class="btn btn-outline btn-sm" id="open-login-modal"
                style="color:#fff;border-color:rgba(255,255,255,.35);gap:7px">
          <i class="fas fa-sign-in-alt"></i> Login
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- ── Category bar ────────────────────────────────────── -->
<?php
$db_cats = getDB();
$cat_stmt = $db_cats->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name");
$nav_categories = $cat_stmt->fetchAll();
$active_cat = $_GET['category'] ?? '';
?>
<div class="cat-nav">
  <div class="container">
    <div class="cat-nav-inner">
      <a href="<?= SITE_URL ?>/customer/index.php" class="cat-nav-link <?= $active_cat === '' ? 'active' : '' ?>">
        <i class="fas fa-th"></i> All
      </a>
      <?php foreach ($nav_categories as $c): ?>
      <a href="<?= SITE_URL ?>/customer/index.php?category=<?= $c['id'] ?>"
         class="cat-nav-link <?= $active_cat == $c['id'] ? 'active' : '' ?>">
        <?= e($c['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if (!is_logged_in()): ?>
<!-- ── Login Modal ────────────────────────────────────────── -->
<div id="login-modal-overlay" class="lm-overlay" role="dialog" aria-modal="true" aria-label="Sign In">
  <div class="lm-card" id="login-modal-card">

    <!-- Close -->
    <button class="lm-close" id="lm-close-btn" aria-label="Close"><i class="fas fa-times"></i></button>

    <!-- Header -->
    <div class="lm-header">
      <div class="lm-logo"><i class="fas fa-shopping-bag"></i> Jeme<span>lo</span></div>
      <h2 class="lm-title">Welcome back!</h2>
      <p class="lm-sub">Sign in to your account to continue shopping.</p>
    </div>

    <!-- Error / Success -->
    <div id="lm-alert" class="lm-alert" style="display:none"></div>

    <!-- Form -->
    <form id="lm-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="lm-field">
        <label class="lm-label" for="lm-email">Email or Username</label>
        <div class="lm-input-wrap">
          <i class="fas fa-user lm-icon"></i>
          <input type="text" id="lm-email" name="email" class="lm-input"
                 placeholder="Email address or username" autocomplete="username" required>
        </div>
      </div>

      <div class="lm-field">
        <label class="lm-label" for="lm-password">
          Password
          <a href="#" class="lm-forgot" style="pointer-events:none;opacity:.45;cursor:default" title="Coming soon">Forgot password?</a>
        </label>
        <div class="lm-input-wrap">
          <i class="fas fa-lock lm-icon"></i>
          <input type="password" id="lm-password" name="password" class="lm-input lm-has-toggle"
                 placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" id="lm-toggle-pw" class="lm-pw-toggle" aria-label="Show password">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>

      <div class="lm-row-check">
        <label class="lm-check-label">
          <input type="checkbox" name="remember" id="lm-remember">
          Keep me signed in
        </label>
      </div>

      <button type="submit" class="lm-submit" id="lm-submit-btn">
        <span id="lm-btn-text"><i class="fas fa-sign-in-alt"></i> Sign In</span>
        <span id="lm-btn-loading" style="display:none"><i class="fas fa-spinner fa-spin"></i> Signing in…</span>
      </button>
    </form>

    <!-- Footer links -->
    <div class="lm-footer">
      <span>Don't have an account?</span>
      <a href="<?= SITE_URL ?>/register.php" class="lm-register-link">Create one free</a>
    </div>

    <!-- Divider + social proof -->
    <div class="lm-divider"><span>Trusted by thousands of shoppers</span></div>
    <div class="lm-perks">
      <div class="lm-perk"><i class="fas fa-shield-alt"></i> Secure</div>
      <div class="lm-perk"><i class="fas fa-truck"></i> Fast Delivery</div>
      <div class="lm-perk"><i class="fas fa-undo"></i> Easy Returns</div>
    </div>

  </div>
</div>

<style>
/* ── Login Modal ──────────────────────────────────────────── */
.lm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(10,20,50,.65);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  z-index: 5000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  opacity: 0;
  visibility: hidden;
  transition: opacity .3s ease, visibility .3s ease;
}
.lm-overlay.lm-open {
  opacity: 1;
  visibility: visible;
}
.lm-card {
  background: #fff;
  border-radius: 20px;
  width: 100%;
  max-width: 420px;
  padding: 36px 36px 28px;
  position: relative;
  box-shadow: 0 32px 80px rgba(0,0,0,.28), 0 4px 16px rgba(0,0,0,.12);
  transform: translateY(28px) scale(.96);
  transition: transform .35s cubic-bezier(.34,1.56,.64,1), opacity .3s ease;
  opacity: 0;
}
.lm-overlay.lm-open .lm-card {
  transform: translateY(0) scale(1);
  opacity: 1;
}
/* Close */
.lm-close {
  position: absolute;
  top: 16px; right: 16px;
  width: 32px; height: 32px;
  border-radius: 50%;
  background: #f1f5f9;
  border: none;
  color: #64748b;
  font-size: .85rem;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background .2s, color .2s, transform .2s;
}
.lm-close:hover { background: #fee2e2; color: #ef4444; transform: scale(1.1); }
/* Header */
.lm-header { text-align: center; margin-bottom: 24px; }
.lm-logo {
  font-size: 1.35rem; font-weight: 800;
  color: #1e293b; margin-bottom: 14px;
  display: inline-flex; align-items: center; gap: 7px;
}
.lm-logo i { color: #2563eb; }
.lm-logo span { color: #2563eb; }
.lm-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
.lm-sub   { font-size: .83rem; color: #64748b; }
/* Alert */
.lm-alert {
  padding: 11px 15px;
  border-radius: 10px;
  font-size: .83rem;
  font-weight: 500;
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 8px;
  animation: lmAlertIn .25s ease both;
}
@keyframes lmAlertIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
.lm-alert.error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.lm-alert.success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
/* Fields */
.lm-field { margin-bottom: 16px; }
.lm-label {
  display: flex; align-items: center; justify-content: space-between;
  font-size: .8rem; font-weight: 600; color: #334155; margin-bottom: 6px;
}
.lm-forgot { font-size: .76rem; font-weight: 500; color: #2563eb; }
.lm-forgot:hover { text-decoration: underline; }
.lm-input-wrap { position: relative; }
.lm-icon {
  position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
  color: #94a3b8; font-size: .8rem; pointer-events: none;
  transition: color .2s;
}
.lm-input {
  width: 100%;
  padding: 11px 14px 11px 38px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-family: inherit; font-size: .88rem; color: #0f172a;
  background: #f8fafc;
  outline: none;
  transition: border-color .2s, box-shadow .2s, background .2s;
}
.lm-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,.12);
  background: #fff;
}
.lm-input:focus ~ .lm-icon,
.lm-input-wrap:focus-within .lm-icon { color: #2563eb; }
.lm-input.lm-has-toggle { padding-right: 44px; }
.lm-pw-toggle {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; color: #94a3b8; cursor: pointer; font-size: .82rem;
  transition: color .2s; padding: 3px;
}
.lm-pw-toggle:hover { color: #2563eb; }
/* Remember me */
.lm-row-check {
  margin-bottom: 20px;
}
.lm-check-label {
  display: flex; align-items: center; gap: 8px;
  font-size: .8rem; color: #64748b; cursor: pointer;
}
.lm-check-label input[type=checkbox] {
  width: 15px; height: 15px;
  accent-color: #2563eb; cursor: pointer;
}
/* Submit */
.lm-submit {
  width: 100%;
  padding: 13px;
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  color: #fff;
  border: none;
  border-radius: 11px;
  font-family: inherit; font-size: .92rem; font-weight: 700;
  cursor: pointer;
  transition: all .2s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 4px 14px rgba(37,99,235,.3);
}
.lm-submit:hover {
  background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(37,99,235,.4);
}
.lm-submit:active { transform: translateY(0); }
.lm-submit:disabled { opacity: .7; cursor: not-allowed; transform: none; }
/* Footer */
.lm-footer {
  text-align: center; margin-top: 18px;
  font-size: .8rem; color: #94a3b8;
  display: flex; align-items: center; justify-content: center; gap: 6px;
}
.lm-register-link {
  color: #2563eb; font-weight: 600;
  transition: color .2s;
}
.lm-register-link:hover { color: #1d4ed8; text-decoration: underline; }
/* Divider */
.lm-divider {
  display: flex; align-items: center; gap: 12px;
  margin: 20px 0 14px; color: #cbd5e1; font-size: .72rem;
}
.lm-divider::before, .lm-divider::after {
  content: ''; flex: 1; height: 1px; background: #e2e8f0;
}
/* Perks */
.lm-perks {
  display: flex; justify-content: center; gap: 20px;
}
.lm-perk {
  display: flex; align-items: center; gap: 5px;
  font-size: .73rem; color: #94a3b8; font-weight: 500;
}
.lm-perk i { color: #2563eb; font-size: .72rem; }
/* Shake animation for wrong password */
@keyframes lm-shake {
  0%,100% { transform: translateY(0) scale(1); }
  20%      { transform: translateX(-6px) scale(1); }
  40%      { transform: translateX(6px) scale(1); }
  60%      { transform: translateX(-4px) scale(1); }
  80%      { transform: translateX(4px) scale(1); }
}
.lm-shake { animation: lm-shake .4s ease both; }
/* Mobile */
@media (max-width: 480px) {
  .lm-card { padding: 28px 20px 22px; border-radius: 16px; }
  .lm-title { font-size: 1.1rem; }
}
</style>

<script>
(function () {
  var overlay  = document.getElementById('login-modal-overlay');
  var openBtn  = document.getElementById('open-login-modal');
  var closeBtn = document.getElementById('lm-close-btn');
  var form     = document.getElementById('lm-form');
  var alert    = document.getElementById('lm-alert');
  var submitBtn= document.getElementById('lm-submit-btn');
  var btnText  = document.getElementById('lm-btn-text');
  var btnLoad  = document.getElementById('lm-btn-loading');
  var emailInp = document.getElementById('lm-email');
  var pwInp    = document.getElementById('lm-password');
  var card     = document.getElementById('login-modal-card');

  function openModal() {
    overlay.classList.add('lm-open');
    document.body.style.overflow = 'hidden';
    setTimeout(function () { emailInp && emailInp.focus(); }, 350);
  }
  function closeModal() {
    overlay.classList.remove('lm-open');
    document.body.style.overflow = '';
    hideAlert();
  }

  // Trigger open
  if (openBtn)  openBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  // Click outside to close
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeModal();
  });

  // Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.classList.contains('lm-open')) closeModal();
  });

  // Password toggle
  var toggleBtn = document.getElementById('lm-toggle-pw');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      var icon = this.querySelector('i');
      if (pwInp.type === 'password') { pwInp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
      else                           { pwInp.type = 'password'; icon.className = 'fas fa-eye'; }
    });
  }

  function showAlert(msg, type) {
    alert.className = 'lm-alert ' + type;
    alert.innerHTML = '<i class="fas ' + (type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle') + '"></i> ' + msg;
    alert.style.display = 'flex';
  }
  function hideAlert() { alert.style.display = 'none'; }

  function setLoading(on) {
    submitBtn.disabled = on;
    btnText.style.display  = on ? 'none'  : 'flex';
    btnLoad.style.display  = on ? 'flex' : 'none';
  }

  // Form submit — AJAX
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    hideAlert();

    var email = emailInp.value.trim();
    var pw    = pwInp.value;

    if (!email || !pw) {
      showAlert('Please enter your email or username and password.', 'error');
      return;
    }

    setLoading(true);

    var fd = new FormData(form);
    fd.append('action', 'login');
    if (returnUrl) fd.append('return', returnUrl);

    fetch(window.SITE_URL + '/ajax/login.php', {
      method: 'POST',
      body: fd
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success) {
        showAlert('Signed in successfully! Redirecting…', 'success');
        // Fade-out transition then redirect
        setTimeout(function () {
          document.documentElement.classList.add('js-page-exit');
          setTimeout(function () { window.location.href = data.redirect; }, 220);
        }, 500);
      } else {
        setLoading(false);
        showAlert(data.message || 'Invalid email or password.', 'error');
        // Shake the card
        card.classList.remove('lm-shake');
        void card.offsetWidth; // reflow
        card.classList.add('lm-shake');
        pwInp.value = '';
        pwInp.focus();
      }
    })
    .catch(function () {
      setLoading(false);
      showAlert('Network error. Please try again.', 'error');
    });
  });

  // Auto-open when redirected with ?modal=login (from require_login or register success)
  var urlParams  = new URLSearchParams(window.location.search);
  var returnUrl  = urlParams.get('return') || '';
  if (urlParams.get('modal') === 'login') {
    openModal();
    if (urlParams.get('reason') === 'timeout') {
      showAlert('Your session expired due to inactivity. Please sign in again.', 'error');
    } else if (urlParams.get('registered') === '1') {
      showAlert('Account created successfully! Please sign in.', 'success');
    } else {
      var flashMsg = document.querySelector('.flash-success, .alert-success');
      if (flashMsg) {
        showAlert(flashMsg.textContent.trim(), 'success');
        flashMsg.style.display = 'none';
      }
    }
  }
})();
</script>
<?php endif; ?>

<?php if (is_logged_in()): ?>
<!-- ── Session Timeout Warning Modal ─────────────────────── -->
<div id="session-timeout-overlay" style="display:none;position:fixed;inset:0;background:rgba(10,20,50,.7);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:380px;padding:28px 28px 24px;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.28)">
    <div style="width:56px;height:56px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
      <i class="fas fa-clock" style="font-size:1.4rem;color:#d97706"></i>
    </div>
    <h3 style="font-size:1.1rem;font-weight:700;color:#0f172a;margin-bottom:6px">Session Expiring Soon</h3>
    <p style="font-size:.85rem;color:#64748b;margin-bottom:4px">You'll be signed out in</p>
    <div id="session-countdown" style="font-size:2rem;font-weight:800;color:#d97706;margin:10px 0">5:00</div>
    <p style="font-size:.8rem;color:#94a3b8;margin-bottom:20px">due to inactivity. Click below to stay signed in.</p>
    <button id="session-extend-btn" style="width:100%;padding:12px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:.92rem;cursor:pointer;transition:all .2s">
      <i class="fas fa-refresh"></i> Keep Me Signed In
    </button>
  </div>
</div>

<script>
(function() {
  var WARNING_BEFORE = 300;  // show warning 5 min before timeout
  var TIMEOUT        = 1800; // 30 min inactivity timeout (must match PHP)
  var HEARTBEAT_INTERVAL = 240000; // ping every 4 min

  var overlay   = document.getElementById('session-timeout-overlay');
  var countdown = document.getElementById('session-countdown');
  var extendBtn = document.getElementById('session-extend-btn');
  var warningTimer = null;
  var countdownTimer = null;
  var secondsLeft = 0;

  function sendHeartbeat() {
    fetch(window.SITE_URL + '/ajax/heartbeat.php')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.authenticated) {
          window.location.href = window.SITE_URL + '/customer/index.php?modal=login&reason=timeout';
        }
      })
      .catch(function() {});
  }

  function showWarning(seconds) {
    secondsLeft = seconds;
    overlay.style.display = 'flex';
    updateCountdown();
    countdownTimer = setInterval(function() {
      secondsLeft--;
      if (secondsLeft <= 0) {
        clearInterval(countdownTimer);
        overlay.style.display = 'none';
        window.location.href = window.SITE_URL + '/customer/index.php?modal=login&reason=timeout';
      } else {
        updateCountdown();
      }
    }, 1000);
  }

  function updateCountdown() {
    var m = Math.floor(secondsLeft / 60);
    var s = secondsLeft % 60;
    countdown.textContent = m + ':' + (s < 10 ? '0' : '') + s;
  }

  function resetTimer() {
    clearTimeout(warningTimer);
    clearInterval(countdownTimer);
    overlay.style.display = 'none';
    warningTimer = setTimeout(function() {
      showWarning(WARNING_BEFORE);
    }, (TIMEOUT - WARNING_BEFORE) * 1000);
  }

  extendBtn.addEventListener('click', function() {
    sendHeartbeat();
    resetTimer();
  });

  // Reset on user activity
  ['click','keydown','mousemove','touchstart'].forEach(function(ev) {
    document.addEventListener(ev, function() { resetTimer(); }, { passive: true });
  });

  // Heartbeat to keep PHP session alive during active use
  setInterval(sendHeartbeat, HEARTBEAT_INTERVAL);

  // Start the inactivity timer
  resetTimer();
})();
</script>
<?php endif; ?>

<?php if (is_logged_in()): ?>
<script>
(function() {
  const siteUrl   = window.SITE_URL;
  const bell      = document.getElementById('notif-bell-cust');
  const dropdown  = document.getElementById('notif-dropdown-cust');
  const list      = document.getElementById('notif-list-cust');
  const badge     = document.getElementById('notif-badge-cust');
  const markAllBtn= document.getElementById('notif-mark-all-cust');
  let loaded = false;

  function updateBadge(count) {
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = '';
    } else {
      badge.style.display = 'none';
    }
  }

  // Toggle dropdown
  bell.addEventListener('click', function(e) {
    e.stopPropagation();
    const isOpen = dropdown.classList.toggle('open');
    if (isOpen && !loaded) {
      fetchNotifs();
      loaded = true;
    }
  });

  // Close on outside click
  document.addEventListener('click', function(e) {
    if (!dropdown.contains(e.target) && e.target !== bell) {
      dropdown.classList.remove('open');
    }
  });

  // Fetch notifications
  function fetchNotifs() {
    fetch(siteUrl + '/ajax/notifications.php?action=fetch')
      .then(r => r.json())
      .then(data => {
        list.innerHTML = data.html;
        updateBadge(data.unread);
        // Attach click handlers for mark-read
        list.querySelectorAll('.notif-item[data-id]').forEach(item => {
          item.addEventListener('click', function() {
            const id = this.dataset.id;
            fetch(siteUrl + '/ajax/notifications.php', {
              method: 'POST',
              headers: {'Content-Type':'application/x-www-form-urlencoded'},
              body: `action=mark_read&id=${id}`
            }).then(r => r.json()).then(d => {
              this.classList.remove('notif-item--unread');
              this.querySelector('.notif-dot')?.remove();
              updateBadge(d.unread);
            });
          });
        });
      }).catch(() => {
        list.innerHTML = '<div class="notif-empty"><i class="fas fa-exclamation-circle"></i><p>Failed to load</p></div>';
      });
  }

  // Mark all read
  markAllBtn.addEventListener('click', function() {
    fetch(siteUrl + '/ajax/notifications.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'action=mark_all_read'
    }).then(r => r.json()).then(() => {
      updateBadge(0);
      loaded = false;
      if (dropdown.classList.contains('open')) fetchNotifs();
    });
  });

  // Poll every 30s
  setInterval(function() {
    fetch(siteUrl + '/ajax/notifications.php?action=poll')
      .then(r => r.json())
      .then(d => {
        updateBadge(d.unread);
        if (d.unread > 0 && loaded) { loaded = false; } // refresh on next open
      });
  }, 30000);
})();
</script>
<?php endif; ?>
