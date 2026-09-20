<?php
$page_title  = 'Staff Management';
$active_menu = 'staff';
require_once __DIR__ . '/../includes/admin_header.php';

// Only full admins can manage staff
if ($_SESSION['user_role'] !== 'admin') {
    echo '<div class="alert alert-danger" style="margin:24px"><i class="fas fa-ban"></i> Access denied. Admin only.</div>';
    require_once __DIR__ . '/../includes/admin_footer.php';
    exit;
}

$db = getDB();

// ── Flash messages ────────────────────────────────────────────
$flash_msg  = '';
$flash_type = 'success';
if (!empty($_SESSION['staff_flash'])) {
    $data = $_SESSION['staff_flash'];
    unset($_SESSION['staff_flash']);
    if (is_array($data)) {
        $flash_msg  = $data['msg']  ?? '';
        $flash_type = $data['type'] ?? 'success';
    } else {
        $flash_msg = $data; // backward compat with plain string
    }
}

// ── Filters & pagination ──────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$status  = in_array($_GET['status'] ?? '', ['active','inactive','']) ? ($_GET['status'] ?? '') : '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where  = ["role = 'staff'"];
$params = [];
if ($search) {
    $sSafe    = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $search) . '%';
    $where[]  = "(name LIKE ? OR email LIKE ?)";
    $params[] = $sSafe;
    $params[] = $sSafe;
}
if ($status) { $where[] = "status = ?"; $params[] = $status; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stCount = $db->prepare("SELECT COUNT(*) FROM users $whereSQL");
$stCount->execute($params);
$total  = (int)$stCount->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$stList = $db->prepare("SELECT * FROM users $whereSQL ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stList->execute(array_merge($params, [$perPage, $offset]));
$staffList = $stList->fetchAll();

// ── Stats ─────────────────────────────────────────────────────
$totalStaff    = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
$activeStaff   = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='staff' AND status='active'")->fetchColumn();
$inactiveStaff = $totalStaff - $activeStaff;
?>

<div class="admin-page-header">
  <div>
    <div class="admin-page-title">Staff Management</div>
    <div class="admin-page-subtitle">Create and manage your store staff accounts</div>
  </div>
  <button class="btn btn-primary" onclick="openModal('add-staff-modal')">
    <i class="fas fa-user-plus"></i> Add Staff
  </button>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);max-width:600px;margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-icon" style="background:#eff6ff"><i class="fas fa-users" style="color:#2563eb"></i></div>
    <div><div class="stat-value"><?= $totalStaff ?></div><div class="stat-label">Total Staff</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#d1fae5"><i class="fas fa-user-check" style="color:#10b981"></i></div>
    <div><div class="stat-value"><?= $activeStaff ?></div><div class="stat-label">Active</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fee2e2"><i class="fas fa-user-slash" style="color:#ef4444"></i></div>
    <div><div class="stat-value"><?= $inactiveStaff ?></div><div class="stat-label">Inactive</div></div>
  </div>
</div>

<?php if ($flash_msg): ?>
<div class="alert alert-<?= e($flash_type) ?>" style="margin-bottom:20px">
  <i class="fas <?= $flash_type === 'danger' ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i> <?= e($flash_msg) ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="admin-card" style="padding:16px 20px;margin-bottom:20px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <div style="flex:1;min-width:200px;position:relative">
      <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-light);font-size:.8rem"></i>
      <input type="text" name="q" class="form-control" placeholder="Search name, email, username…"
             value="<?= e($search) ?>" style="padding-left:34px">
    </div>
    <select name="status" class="form-control" style="width:auto" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
    <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-search"></i> Search</button>
    <?php if ($search || $status): ?>
    <a href="<?= SITE_URL ?>/admin/staff.php" class="btn btn-sm" style="background:var(--bg-alt);color:var(--text-muted)">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Staff table -->
<div class="admin-card" style="overflow:hidden">
  <div class="admin-card-header" style="display:flex;justify-content:space-between;align-items:center">
    <span class="admin-card-title">Staff Accounts (<?= $total ?>)</span>
  </div>

  <?php if (empty($staffList)): ?>
  <div style="text-align:center;padding:60px 20px;color:var(--text-muted)">
    <i class="fas fa-user-slash" style="font-size:2.5rem;display:block;margin-bottom:12px;color:var(--text-light)"></i>
    <h3 style="margin-bottom:6px">No staff accounts found</h3>
    <p style="font-size:.85rem">Click "Add Staff" to create the first staff account.</p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Staff Member</th>
          <th>Username</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Status</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($staffList as $s): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <img src="<?= avatar_url($s['avatar']) ?>" alt=""
                   style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid var(--border)">
              <div>
                <div style="font-weight:600;font-size:.88rem"><?= e($s['name']) ?></div>
                <?php if ($s['first_name'] || $s['last_name']): ?>
                <div style="font-size:.75rem;color:var(--text-muted)"><?= e(trim($s['first_name'].' '.$s['last_name'])) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="font-size:.85rem;color:var(--text-muted)"><?= $s['username'] ? '@'.e($s['username']) : '<span style="color:var(--text-light)">—</span>' ?></td>
          <td style="font-size:.85rem"><?= e($s['email']) ?></td>
          <td style="font-size:.85rem;color:var(--text-muted)"><?= e($s['phone'] ?: '—') ?></td>
          <td>
            <span class="badge <?= $s['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
              <?= ucfirst($s['status']) ?>
            </span>
          </td>
          <td style="font-size:.8rem;color:var(--text-muted)"><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn btn-sm btn-outline btn-icon"
                      onclick="editStaff(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"
                      title="Edit"><i class="fas fa-edit"></i></button>
              <form method="POST" action="<?= SITE_URL ?>/ajax/staff.php" style="margin:0">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn-sm btn-icon <?= $s['status']==='active' ? 'btn-outline' : 'btn-success' ?>"
                        title="<?= $s['status']==='active' ? 'Deactivate' : 'Activate' ?>"
                        style="<?= $s['status']==='active' ? 'color:var(--warning);border-color:var(--warning)' : '' ?>">
                  <i class="fas <?= $s['status']==='active' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                </button>
              </form>
              <form method="POST" action="<?= SITE_URL ?>/ajax/staff.php" style="margin:0">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn-sm btn-icon btn-danger"
                        data-confirm="Delete staff account for <?= e(addslashes($s['name'])) ?>? This cannot be undone."
                        title="Delete"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div style="display:flex;justify-content:center;gap:6px;padding:16px">
    <?php
    $base = '?q=' . urlencode($search) . '&status=' . $status;
    for ($p = 1; $p <= $pages; $p++):
    ?>
    <a href="<?= $base ?>&page=<?= $p ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- ── Add Staff Modal ──────────────────────────────────────── -->
<div class="modal-overlay" id="add-staff-modal" onclick="if(event.target===this)closeModal('add-staff-modal')">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-user-plus"></i> Add Staff Account</h3>
      <button class="modal-close" onclick="closeModal('add-staff-modal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/ajax/staff.php" enctype="multipart/form-data" id="add-staff-form">
        <input type="hidden" name="action" value="add">
        <?= csrf_field() ?>

        <!-- Avatar -->
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding:14px;background:var(--bg);border-radius:var(--radius-md);border:1.5px dashed var(--border)">
          <img src="<?= SITE_URL ?>/assets/images/default-avatar.svg" id="add-avatar-preview"
               style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
          <div>
            <div style="font-weight:600;font-size:.85rem;margin-bottom:4px">Profile Picture</div>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('add-avatar-file').click()">
              <i class="fas fa-camera"></i> Choose
            </button>
          </div>
          <input type="file" name="avatar" id="add-avatar-file" accept="image/jpeg,image/png,image/webp" style="display:none">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">First Name *</label>
            <input type="text" name="first_name" class="form-control" placeholder="Juan" required>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Last Name *</label>
            <input type="text" name="last_name" class="form-control" placeholder="dela Cruz" required>
          </div>
        </div>
        <div class="form-group mt-16">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" placeholder="staff@shop.com" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" placeholder="e.g. juan_staff" autocomplete="off">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" placeholder="09XX XXX XXXX">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Password *</label>
            <div style="position:relative">
              <input type="password" name="password" id="add-pwd" class="form-control" style="padding-right:42px"
                     placeholder="Min. 8 characters" required>
              <button type="button" onclick="togglePw('add-pwd',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Confirm Password *</label>
            <input type="password" name="confirm" id="add-conf" class="form-control" placeholder="Repeat password" required>
          </div>
        </div>
        <div class="form-group mt-16">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="modal-footer" style="margin-top:20px;padding:0;border:none;display:flex;gap:10px;justify-content:flex-end">
          <button type="button" class="btn btn-outline" onclick="closeModal('add-staff-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Staff</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Edit Staff Modal ─────────────────────────────────────── -->
<div class="modal-overlay" id="edit-staff-modal" onclick="if(event.target===this)closeModal('edit-staff-modal')">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-user-edit"></i> Edit Staff Account</h3>
      <button class="modal-close" onclick="closeModal('edit-staff-modal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/ajax/staff.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit-id">
        <?= csrf_field() ?>

        <!-- Avatar -->
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding:14px;background:var(--bg);border-radius:var(--radius-md);border:1.5px dashed var(--border)">
          <img src="<?= SITE_URL ?>/assets/images/default-avatar.svg" id="edit-avatar-preview"
               style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
          <div>
            <div style="font-weight:600;font-size:.85rem;margin-bottom:4px">Profile Picture</div>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('edit-avatar-file').click()">
              <i class="fas fa-camera"></i> Change
            </button>
          </div>
          <input type="file" name="avatar" id="edit-avatar-file" accept="image/jpeg,image/png,image/webp" style="display:none">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">First Name *</label>
            <input type="text" name="first_name" id="edit-first-name" class="form-control" required>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Last Name *</label>
            <input type="text" name="last_name" id="edit-last-name" class="form-control" required>
          </div>
        </div>
        <div class="form-group mt-16">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" id="edit-email" class="form-control" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Username</label>
            <input type="text" name="username" id="edit-username" class="form-control" placeholder="e.g. juan_staff" autocomplete="off">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" id="edit-phone" class="form-control">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">New Password <span style="font-weight:400;color:var(--text-muted)">(leave blank to keep)</span></label>
            <div style="position:relative">
              <input type="password" name="password" id="edit-pwd" class="form-control" style="padding-right:42px" placeholder="••••••••">
              <button type="button" onclick="togglePw('edit-pwd',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Status</label>
            <select name="status" id="edit-status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer" style="margin-top:20px;padding:0;border:none;display:flex;gap:10px;justify-content:flex-end">
          <button type="button" class="btn btn-outline" onclick="closeModal('edit-staff-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function togglePw(id, btn) {
  const inp  = document.getElementById(id);
  const icon = btn.querySelector('i');
  if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
  else                         { inp.type = 'password'; icon.className = 'fas fa-eye'; }
}

function editStaff(s) {
  document.getElementById('edit-id').value         = s.id;
  document.getElementById('edit-first-name').value = s.first_name || '';
  document.getElementById('edit-last-name').value  = s.last_name  || '';
  document.getElementById('edit-email').value       = s.email;
  document.getElementById('edit-username').value    = s.username || '';
  document.getElementById('edit-phone').value       = s.phone || '';
  document.getElementById('edit-status').value      = s.status;
  document.getElementById('edit-pwd').value         = '';
  // Avatar preview
  const siteUrl = '<?= SITE_URL ?>';
  const av = s.avatar && s.avatar !== 'default.png'
    ? siteUrl + '/assets/uploads/avatars/' + s.avatar
    : siteUrl + '/assets/images/default-avatar.svg';
  document.getElementById('edit-avatar-preview').src = av;
  openModal('edit-staff-modal');
}

// Avatar previews
['add','edit'].forEach(function(prefix) {
  const inp = document.getElementById(prefix + '-avatar-file');
  if (!inp) return;
  inp.addEventListener('change', function() {
    if (!this.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) { document.getElementById(prefix + '-avatar-preview').src = e.target.result; };
    reader.readAsDataURL(this.files[0]);
  });
});

</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
