<?php
require_once __DIR__ . '/../includes/auth.php';
require_customer();
$page_title = 'My Profile';
$db = getDB();

$me_st = $db->prepare("SELECT * FROM users WHERE id=?");
$me_st->execute([$_SESSION['user_id']]);
$me = $me_st->fetch();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['update_profile'])) {
        $name    = trim($_POST['name']    ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $address = trim($_POST['address'] ?? '');

        if (!$name) $errors[] = 'Name is required.';

        if (!$errors) {
            $newAvatar = $me['avatar'];
            if (!empty($_FILES['avatar']['name'])) {
                $uploaded = upload_image($_FILES['avatar'], 'avatars');
                if ($uploaded === false) {
                    $errors[] = 'Avatar must be JPEG, PNG, or WebP and under 3 MB.';
                } else {
                    if ($me['avatar'] && $me['avatar'] !== 'default.png') {
                        @unlink(UPLOAD_PATH . 'avatars/' . $me['avatar']);
                    }
                    $newAvatar = $uploaded;
                }
            }
            if (!$errors) {
                $upd = $db->prepare("UPDATE users SET name=?, phone=?, address=?, avatar=? WHERE id=?");
                $upd->execute([$name, $phone, $address, $newAvatar, $_SESSION['user_id']]);
                $_SESSION['user_name'] = $name;
                flash('profile_ok', 'Profile updated successfully!', 'success');
                redirect(SITE_URL . '/customer/profile.php');
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $current  = $_POST['current_pw']  ?? '';
        $new_pw   = $_POST['new_pw']      ?? '';
        $confirm  = $_POST['confirm_pw']  ?? '';

        if (!password_verify($current, $me['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new_pw) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new_pw !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            $upd = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->execute([password_hash($new_pw, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            flash('profile_ok', 'Password changed successfully!', 'success');
            redirect(SITE_URL . '/customer/profile.php');
        }
    }
}

// Stats
$order_count  = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");
$order_count->execute([$_SESSION['user_id']]);
$orders_total = (int)$order_count->fetchColumn();

$spend_st = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=? AND status NOT IN ('cancelled')");
$spend_st->execute([$_SESSION['user_id']]);
$total_spent = (float)$spend_st->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1><i class="fas fa-user-circle" style="color:var(--primary)"></i> My Profile</h1>
    <p>Manage your account settings</p>
  </div>
</div>

<div class="container" style="padding-bottom:60px">
  <?php show_flash('profile_ok'); ?>
  <?php foreach ($errors as $e): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($e) ?></div>
  <?php endforeach; ?>

  <div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start">

    <!-- Profile sidebar -->
    <div>
      <div class="card mb-16">
        <div class="card-body" style="text-align:center;padding:28px">
          <div class="profile-avatar-wrap">
            <img src="<?= avatar_url($me['avatar']) ?>" alt="avatar" class="profile-avatar-img" id="cust-avatar-preview">
            <label for="cust-avatar-upload" class="avatar-edit-btn" title="Change photo"><i class="fas fa-camera"></i></label>
          </div>
          <h3 style="margin-bottom:4px;font-size:1rem;margin-top:12px"><?= e($me['name']) ?></h3>
          <p style="font-size:.8rem;margin:0"><?= e($me['email']) ?></p>
          <div style="font-size:.75rem;color:var(--text-muted);margin-top:6px">
            Member since <?= date('M Y', strtotime($me['created_at'])) ?>
          </div>
        </div>
      </div>

      <div class="card mb-16">
        <div class="card-body">
          <div style="display:flex;gap:12px;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
            <div style="width:40px;height:40px;background:var(--primary-light);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;color:var(--primary)">
              <i class="fas fa-box"></i>
            </div>
            <div>
              <div style="font-size:1.2rem;font-weight:800"><?= $orders_total ?></div>
              <div style="font-size:.75rem;color:var(--text-muted)">Total Orders</div>
            </div>
          </div>
          <div style="display:flex;gap:12px;align-items:center;padding:8px 0">
            <div style="width:40px;height:40px;background:#D1FAE5;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;color:var(--success)">
              <i class="fas fa-peso-sign"></i>
            </div>
            <div>
              <div style="font-size:1.2rem;font-weight:800"><?= currency($total_spent) ?></div>
              <div style="font-size:.75rem;color:var(--text-muted)">Total Spent</div>
            </div>
          </div>
        </div>
      </div>

      <a href="<?= SITE_URL ?>/customer/orders.php" class="btn btn-outline btn-block">
        <i class="fas fa-box"></i> View My Orders
      </a>
    </div>

    <!-- Forms -->
    <div>
      <!-- Profile form -->
      <div class="card mb-24">
        <div class="card-header">Edit Profile</div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="file" id="cust-avatar-upload" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:none">
            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-control" value="<?= e($me['name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" class="form-control" value="<?= e($me['email']) ?>" disabled
                     style="background:var(--bg-alt);cursor:not-allowed">
              <div class="form-hint">Email cannot be changed.</div>
            </div>
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input type="text" name="phone" class="form-control" value="<?= e($me['phone'] ?? '') ?>" placeholder="+63 9xx xxx xxxx">
            </div>
            <div class="form-group">
              <label class="form-label">Delivery Address</label>
              <textarea name="address" class="form-control" rows="3"><?= e($me['address'] ?? '') ?></textarea>
            </div>
            <button type="submit" name="update_profile" class="btn btn-primary" id="cust-save-btn">
              <i class="fas fa-save"></i> Save Changes
            </button>
          </form>
          <script>
          document.getElementById('cust-avatar-upload').addEventListener('change', function() {
            if (this.files && this.files[0]) {
              document.getElementById('cust-avatar-preview').src = URL.createObjectURL(this.files[0]);
              document.getElementById('cust-save-btn').click();
            }
          });
          </script>
        </div>
      </div>

      <!-- Change password -->
      <div class="card mb-24">
        <div class="card-header">Change Password</div>
        <div class="card-body">
          <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_pw" class="form-control" required>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_pw" class="form-control" placeholder="Min. 8 characters" required>
              </div>
              <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_pw" class="form-control" required>
              </div>
            </div>
            <button type="submit" name="change_password" class="btn btn-dark">
              <i class="fas fa-lock"></i> Update Password
            </button>
          </form>
        </div>
      </div>

      <!-- Active Sessions -->
      <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
          <span><i class="fas fa-shield-alt" style="color:var(--primary);margin-right:6px"></i> Active Sessions</span>
          <button id="revoke-all-btn" class="btn btn-sm btn-outline" style="color:var(--danger);border-color:var(--danger);font-size:.76rem;padding:5px 12px">
            <i class="fas fa-sign-out-alt"></i> Sign Out All Others
          </button>
        </div>
        <div class="card-body" style="padding-top:6px">
          <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:14px">
            These are the devices where your account is remembered. Revoke any session you don't recognize.
          </p>
          <div id="sessions-list">
            <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:.84rem">
              <i class="fas fa-spinner fa-spin"></i> Loading sessions…
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.session-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
}
.session-item:last-child { border-bottom: none; }
.session-icon {
  width: 40px; height: 40px; flex-shrink: 0;
  background: var(--bg-alt); border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: var(--text-muted); font-size: 1rem;
}
.session-meta { flex: 1; min-width: 0; }
.session-device { font-size: .84rem; font-weight: 600; color: var(--text); margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.session-detail { font-size: .74rem; color: var(--text-muted); }
.session-revoke { flex-shrink: 0; background: none; border: 1.5px solid #fca5a5; color: var(--danger); border-radius: 7px; padding: 5px 11px; font-size: .74rem; font-weight: 600; cursor: pointer; transition: all .2s; }
.session-revoke:hover { background: #fef2f2; }
</style>

<script>
(function() {
  var siteUrl = window.SITE_URL;
  var csrf    = document.querySelector('meta[name="csrf-token"]')?.content || '';
  var list    = document.getElementById('sessions-list');
  var revokeAllBtn = document.getElementById('revoke-all-btn');

  function deviceIcon(info) {
    if (!info) return 'fa-desktop';
    var u = info.toLowerCase();
    if (u.includes('mobile') || u.includes('android') || u.includes('iphone')) return 'fa-mobile-alt';
    if (u.includes('tablet') || u.includes('ipad')) return 'fa-tablet-alt';
    return 'fa-desktop';
  }

  function shortDevice(info) {
    if (!info || info === 'Unknown device') return 'Unknown device';
    // Extract browser name
    var browser = 'Unknown browser';
    if (info.includes('Chrome') && !info.includes('Chromium') && !info.includes('Edg')) browser = 'Chrome';
    else if (info.includes('Firefox')) browser = 'Firefox';
    else if (info.includes('Safari') && !info.includes('Chrome')) browser = 'Safari';
    else if (info.includes('Edg')) browser = 'Edge';
    else if (info.includes('Opera') || info.includes('OPR')) browser = 'Opera';
    // OS
    var os = '';
    if (info.includes('Windows')) os = 'Windows';
    else if (info.includes('Mac OS')) os = 'macOS';
    else if (info.includes('Android')) os = 'Android';
    else if (info.includes('iPhone') || info.includes('iPad')) os = 'iOS';
    else if (info.includes('Linux')) os = 'Linux';
    return browser + (os ? ' on ' + os : '');
  }

  function timeAgo(dateStr) {
    var d = new Date(dateStr.replace(' ', 'T'));
    var sec = Math.floor((Date.now() - d) / 1000);
    if (sec < 60)   return 'just now';
    if (sec < 3600) return Math.floor(sec/60) + ' min ago';
    if (sec < 86400) return Math.floor(sec/3600) + ' hr ago';
    return Math.floor(sec/86400) + ' days ago';
  }

  function loadSessions() {
    fetch(siteUrl + '/ajax/sessions.php?action=list')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.success || !data.sessions.length) {
          list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:.84rem"><i class="fas fa-info-circle"></i> No remembered sessions found. Sessions are created when you use "Keep me signed in".</div>';
          return;
        }
        var html = '';
        data.sessions.forEach(function(s) {
          html += '<div class="session-item" data-id="' + s.id + '">' +
            '<div class="session-icon"><i class="fas ' + deviceIcon(s.device_info) + '"></i></div>' +
            '<div class="session-meta">' +
              '<div class="session-device">' + shortDevice(s.device_info) + '</div>' +
              '<div class="session-detail">' +
                '<i class="fas fa-map-marker-alt" style="width:12px"></i> ' + s.ip_address +
                ' &nbsp;·&nbsp; <i class="fas fa-clock" style="width:12px"></i> Last used ' + timeAgo(s.last_used) +
                ' &nbsp;·&nbsp; Expires ' + new Date(s.expires_at.replace(' ','T')).toLocaleDateString() +
              '</div>' +
            '</div>' +
            '<button class="session-revoke" data-id="' + s.id + '"><i class="fas fa-times"></i> Revoke</button>' +
            '</div>';
        });
        list.innerHTML = html;

        // Revoke individual
        list.querySelectorAll('.session-revoke').forEach(function(btn) {
          btn.addEventListener('click', function() {
            var id = this.dataset.id;
            var item = this.closest('.session-item');
            if (!confirm('Revoke this session? That device will be signed out on next visit.')) return;
            var fd = new FormData();
            fd.append('action', 'revoke');
            fd.append('session_id', id);
            fd.append('csrf_token', csrf);
            fetch(siteUrl + '/ajax/sessions.php', { method: 'POST', body: fd })
              .then(function(r) { return r.json(); })
              .then(function(d) {
                if (d.success) { item.remove(); if (!list.querySelector('.session-item')) loadSessions(); }
              });
          });
        });
      })
      .catch(function() {
        list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--danger);font-size:.84rem"><i class="fas fa-exclamation-triangle"></i> Failed to load sessions.</div>';
      });
  }

  revokeAllBtn.addEventListener('click', function() {
    if (!confirm('Sign out all other sessions? Your current session will remain active.')) return;
    var fd = new FormData();
    fd.append('action', 'revoke_all');
    fd.append('csrf_token', csrf);
    fetch(siteUrl + '/ajax/sessions.php', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.success) loadSessions(); });
  });

  loadSessions();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
