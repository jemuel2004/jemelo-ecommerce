<?php
require_once __DIR__ . '/../includes/auth.php';
require_rider();   // Guard before any DB work

$page_title  = 'My Profile';
$active_menu = 'profile';
$db = getDB();

$st = $db->prepare("SELECT * FROM users WHERE id=?");
$st->execute([$_SESSION['user_id']]);
$me = $st->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['update_profile'])) {
        $name  = trim($_POST['name']  ?? '');
        $phone = trim($_POST['phone'] ?? '');
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
                $db->prepare("UPDATE users SET name=?,phone=?,avatar=? WHERE id=?")
                   ->execute([$name, $phone, $newAvatar, $_SESSION['user_id']]);
                $_SESSION['user_name'] = $name;
                flash('rider_profile', 'Profile updated!', 'success');
                redirect(SITE_URL . '/rider/profile.php');
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $current = $_POST['current_pw'] ?? '';
        $new_pw  = $_POST['new_pw']     ?? '';
        $confirm = $_POST['confirm_pw'] ?? '';
        if (!password_verify($current, $me['password'])) $errors[] = 'Current password incorrect.';
        elseif (strlen($new_pw) < 6) $errors[] = 'New password must be 6+ characters.';
        elseif ($new_pw !== $confirm) $errors[] = 'Passwords do not match.';
        else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")
               ->execute([password_hash($new_pw, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            flash('rider_profile', 'Password changed!', 'success');
            redirect(SITE_URL . '/rider/profile.php');
        }
    }
}

require_once __DIR__ . '/../includes/rider_header.php';

// Delivery stats
$rid       = (int)$_SESSION['user_id'];
$stDel = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE rider_id=?"); $stDel->execute([$rid]); $del_total = (int)$stDel->fetchColumn();
$stDone = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE rider_id=? AND status='delivered'"); $stDone->execute([$rid]); $del_done = (int)$stDone->fetchColumn();
$del_rate  = $del_total > 0 ? round($del_done / $del_total * 100) : 0;
?>

<div class="rider-page-header">
  <div class="rider-page-title">My Profile</div>
</div>

<?php $f = flash('rider_profile'); if ($f): ?>
<div class="alert alert-<?= e($f['type']) ?>"><i class="fas fa-check-circle"></i> <?= e($f['msg']) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div style="display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start">
  <!-- Left -->
  <div>
    <div class="rider-card mb-16">
      <div class="rider-card-body" style="text-align:center;padding:28px">
        <div class="rider-profile-avatar-wrap">
          <img src="<?= avatar_url($me['avatar']) ?>" alt="avatar" class="rider-profile-avatar" id="avatar-preview">
          <label for="avatar-upload" class="rider-avatar-edit" title="Change photo"><i class="fas fa-camera"></i></label>
        </div>
        <h3 style="margin:12px 0 4px;font-size:1rem"><?= e($me['name']) ?></h3>
        <p style="font-size:.8rem;color:var(--text-muted)"><?= e($me['email']) ?></p>
        <span class="badge badge-primary" style="margin-top:8px;background:var(--rider-primary-l);color:var(--rider-primary)">
          <i class="fas fa-motorcycle"></i> Rider
        </span>
      </div>
    </div>
    <div class="rider-card">
      <div class="rider-card-body">
        <div style="text-align:center;padding:8px 0">
          <div style="font-size:2rem;font-weight:800;color:var(--rider-primary)"><?= $del_rate ?>%</div>
          <div style="font-size:.78rem;color:var(--text-muted)">Delivery Success Rate</div>
          <div style="display:flex;justify-content:center;gap:20px;margin-top:14px">
            <div style="text-align:center">
              <div style="font-size:1.2rem;font-weight:700"><?= $del_total ?></div>
              <div style="font-size:.72rem;color:var(--text-muted)">Assigned</div>
            </div>
            <div style="text-align:center">
              <div style="font-size:1.2rem;font-weight:700;color:var(--rider-success)"><?= $del_done ?></div>
              <div style="font-size:.72rem;color:var(--text-muted)">Delivered</div>
            </div>
            <div style="text-align:center">
              <div style="font-size:1.2rem;font-weight:700;color:var(--rider-danger)"><?= $del_total - $del_done ?></div>
              <div style="font-size:.72rem;color:var(--text-muted)">Failed/Pending</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right -->
  <div>
    <div class="rider-card mb-20">
      <div class="rider-card-header"><span class="rider-card-title">Edit Profile</span></div>
      <div class="rider-card-body">
        <form method="POST" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="file" id="avatar-upload" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:none" data-preview="avatar-preview">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-control" value="<?= e($me['name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= e($me['phone'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" value="<?= e($me['email']) ?>" disabled style="background:var(--bg-alt)">
            <div class="form-hint">Email cannot be changed here.</div>
          </div>
          <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </form>
      </div>
    </div>

    <div class="rider-card">
      <div class="rider-card-header"><span class="rider-card-title">Change Password</span></div>
      <div class="rider-card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_pw" class="form-control" required>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label class="form-label">New Password</label>
              <input type="password" name="new_pw" class="form-control" placeholder="Min. 6 chars" required>
            </div>
            <div class="form-group">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_pw" class="form-control" required>
            </div>
          </div>
          <button type="submit" name="change_password" class="btn btn-outline"><i class="fas fa-lock"></i> Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/rider_footer.php'; ?>
