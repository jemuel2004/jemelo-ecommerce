<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_panel();

$page_title  = 'Admin Profile';
$active_menu = 'profile';
$db = getDB();

$me_st = $db->prepare("SELECT * FROM users WHERE id=?");
$me_st->execute([$_SESSION['user_id']]);
$me = $me_st->fetch();

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
                    $errors[] = 'Avatar must be JPEG, PNG, or WebP and under 2 MB.';
                } else {
                    if ($me['avatar'] && $me['avatar'] !== 'default.png') {
                        @unlink(UPLOAD_PATH . 'avatars/' . $me['avatar']);
                    }
                    $newAvatar = $uploaded;
                }
            }
            if (!$errors) {
                $db->prepare("UPDATE users SET name=?,phone=?,avatar=? WHERE id=?")->execute([$name, $phone, $newAvatar, $_SESSION['user_id']]);
                $_SESSION['user_name'] = $name;
                flash('admin_profile', 'Profile updated!', 'success');
                redirect(SITE_URL . '/admin/profile.php');
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $current = $_POST['current_pw'] ?? '';
        $new_pw  = $_POST['new_pw']     ?? '';
        $confirm = $_POST['confirm_pw'] ?? '';
        if (!password_verify($current, $me['password'])) $errors[] = 'Current password incorrect.';
        elseif (strlen($new_pw) < 6)  $errors[] = 'New password must be 6+ characters.';
        elseif ($new_pw !== $confirm)  $errors[] = 'Passwords do not match.';
        else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new_pw, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            flash('admin_profile', 'Password changed!', 'success');
            redirect(SITE_URL . '/admin/profile.php');
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';

// Quick stats
$total_orders    = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$total_revenue   = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status!='cancelled'")->fetchColumn();
$total_products  = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$total_customers = $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
?>

<div class="admin-page-header">
  <div class="admin-page-title">My Profile</div>
</div>

<?php show_flash('admin_profile'); ?>
<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start">
  <!-- Left -->
  <div>
    <div class="admin-card mb-16">
      <div class="admin-card-body" style="text-align:center;padding:28px">
        <div class="profile-avatar-wrap">
          <img src="<?= avatar_url($me['avatar']) ?>" alt="avatar" class="profile-avatar-img" id="avatar-preview">
          <label for="avatar-upload" class="avatar-edit-btn" title="Change photo"><i class="fas fa-camera"></i></label>
        </div>
        <h3 style="margin-bottom:4px;font-size:1rem;margin-top:12px"><?= e($me['name']) ?></h3>
        <p style="font-size:.8rem;margin:0"><?= e($me['email']) ?></p>
        <span class="badge badge-primary" style="margin-top:8px">Administrator</span>
      </div>
    </div>

    <div class="admin-card">
      <div class="admin-card-body">
        <?php
        $stats = [
            ['Orders',    number_format($total_orders),   'fas fa-shopping-bag', 'blue'],
            ['Revenue',   currency((float)$total_revenue),'fas fa-peso-sign',    'green'],
            ['Products',  number_format($total_products), 'fas fa-box-open',     'orange'],
            ['Customers', number_format($total_customers),'fas fa-users',        'purple'],
        ];
        foreach ($stats as [$label, $val, $icon, $color]):
        ?>
        <div style="display:flex;gap:12px;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
          <div class="stat-icon <?= $color ?>" style="width:36px;height:36px;font-size:.9rem;border-radius:8px">
            <i class="<?= $icon ?>"></i>
          </div>
          <div>
            <div style="font-size:1rem;font-weight:700"><?= $val ?></div>
            <div style="font-size:.72rem;color:var(--text-muted)"><?= $label ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Right -->
  <div>
    <div class="admin-card mb-20">
      <div class="admin-card-header"><span class="admin-card-title">Edit Profile</span></div>
      <div class="admin-card-body">
        <form method="POST" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="file" id="avatar-upload" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:none">
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
            <div class="form-hint">Email cannot be changed.</div>
          </div>
          <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </form>
        <script>
        document.getElementById('avatar-upload').addEventListener('change', function() {
          if (this.files && this.files[0]) {
            document.getElementById('avatar-preview').src = URL.createObjectURL(this.files[0]);
            this.closest('form').querySelector('[name=update_profile]').click();
          }
        });
        </script>
      </div>
    </div>

    <div class="admin-card">
      <div class="admin-card-header"><span class="admin-card-title">Change Password</span></div>
      <div class="admin-card-body">
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
          <button type="submit" name="change_password" class="btn btn-dark"><i class="fas fa-lock"></i> Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
