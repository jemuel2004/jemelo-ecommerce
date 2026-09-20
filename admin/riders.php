<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_panel();

// Admin-only: riders management
if ($_SESSION['user_role'] !== 'admin') redirect(SITE_URL . '/admin/index.php');

$page_title  = 'Riders';
$active_menu = 'riders';
$db = getDB();

// Handle flash
$flash = flash('rider_mgmt');

require_once __DIR__ . '/../includes/admin_header.php';

// Stats
$total    = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='rider'")->fetchColumn();
$active   = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='rider' AND status='active'")->fetchColumn();
$inactive = $total - $active;
$del_today= (int)$db->query("SELECT COUNT(*) FROM deliveries WHERE DATE(created_at)=CURDATE()")->fetchColumn();

// Filter & search
$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;

$where  = ["role='rider'"];
$params = [];
if ($q)      { $qS = '%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%'; $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)"; $params = array_merge($params, [$qS,$qS,$qS]); }
if ($status) { $where[] = "status=?"; $params[] = $status; }

$ws = 'WHERE '.implode(' AND ', $where);
$cntSt = $db->prepare("SELECT COUNT(*) FROM users $ws"); $cntSt->execute($params);
$total_rows = (int)$cntSt->fetchColumn();
$pag = paginate($total_rows, $limit, $page);

$listSt = $db->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM deliveries WHERE rider_id=u.id) AS total_deliveries,
           (SELECT COUNT(*) FROM deliveries WHERE rider_id=u.id AND status='delivered') AS delivered,
           (SELECT COUNT(*) FROM orders WHERE rider_id=u.id AND status IN ('processing','picked_up','out_for_delivery')) AS active_deliveries
    FROM users u $ws
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
$listSt->execute(array_merge($params, [$pag['per_page'], $pag['offset']]));
$riders = $listSt->fetchAll();
?>

<div class="admin-page-header">
  <div>
    <div class="admin-page-title">Riders</div>
    <div class="admin-page-subtitle"><?= $total_rows ?> riders registered</div>
  </div>
  <button class="btn btn-primary" onclick="openModal('add-rider-modal')">
    <i class="fas fa-plus"></i> Add Rider
  </button>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>">
  <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
  <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
  <?php foreach ([
    ['Total Riders',     $total,     'fa-motorcycle',   'orange'],
    ['Active',           $active,    'fa-check-circle', 'green'],
    ['Inactive',         $inactive,  'fa-ban',          'red'],
    ["Today's Deliveries",$del_today,'fa-truck',        'blue'],
  ] as [$lbl,$val,$ico,$col]): ?>
  <div class="admin-card" style="padding:0">
    <div style="display:flex;align-items:center;gap:14px;padding:18px 20px">
      <div class="stat-icon <?= $col ?>" style="width:42px;height:42px;border-radius:10px;font-size:.95rem">
        <i class="fas <?= $ico ?>"></i>
      </div>
      <div>
        <div style="font-size:1.5rem;font-weight:800;line-height:1"><?= $val ?></div>
        <div style="font-size:.74rem;color:var(--text-muted)"><?= $lbl ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="admin-card">
  <div class="admin-card-header">
    <form method="GET" class="filter-bar" style="width:100%">
      <div class="search-input-wrap">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Search by name, email, phone…" value="<?= e($q) ?>">
      </div>
      <select name="status" class="filter-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="active"   <?= $status==='active'   ? 'selected':'' ?>>Active</option>
        <option value="inactive" <?= $status==='inactive' ? 'selected':'' ?>>Inactive</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="<?= SITE_URL ?>/admin/riders.php" class="btn btn-outline-dark btn-sm">Reset</a>
    </form>
  </div>
  <div class="admin-card-body no-pad">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Rider</th><th>Phone</th><th>Total</th><th>Delivered</th><th>Active Now</th>
            <th>Rate</th><th>Joined</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($riders as $r): ?>
          <?php $rate = $r['total_deliveries'] > 0 ? round($r['delivered'] / $r['total_deliveries'] * 100) : 0; ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <img src="<?= avatar_url($r['avatar'] ?? null) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #fed7aa">
                <div>
                  <div style="font-weight:600;font-size:.88rem"><?= e($r['name']) ?></div>
                  <div style="font-size:.74rem;color:var(--text-muted)"><?= e($r['email']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-size:.84rem"><?= e($r['phone'] ?? '—') ?></td>
            <td><span class="badge badge-info"><?= $r['total_deliveries'] ?></span></td>
            <td><span class="badge badge-success"><?= $r['delivered'] ?></span></td>
            <td>
              <?php if ($r['active_deliveries'] > 0): ?>
              <span class="badge badge-warning"><?= $r['active_deliveries'] ?> active</span>
              <?php else: ?>
              <span style="color:var(--text-muted);font-size:.8rem">—</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:6px">
                <div style="width:50px;height:6px;background:var(--bg-alt);border-radius:3px">
                  <div style="width:<?= $rate ?>%;height:100%;background:var(--success);border-radius:3px"></div>
                </div>
                <span style="font-size:.78rem;font-weight:600"><?= $rate ?>%</span>
              </div>
            </td>
            <td style="font-size:.78rem;color:var(--text-muted)"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="btn btn-sm btn-outline" onclick='editRider(<?= json_encode($r) ?>)' title="Edit">
                  <i class="fas fa-edit"></i>
                </button>
                <form method="POST" action="<?= SITE_URL ?>/ajax/riders_admin.php" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button type="submit"
                          class="btn btn-sm <?= $r['status']==='active'?'btn-danger':'btn-success' ?>"
                          data-confirm="<?= $r['status']==='active'?'Deactivate':'Activate' ?> this rider?"
                          title="<?= $r['status']==='active'?'Deactivate':'Activate' ?>">
                    <i class="fas fa-<?= $r['status']==='active'?'ban':'check' ?>"></i>
                  </button>
                </form>
                <form method="POST" action="<?= SITE_URL ?>/ajax/riders_admin.php" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this rider account? This cannot be undone.">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$riders): ?>
          <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">No riders found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($pag['pages'] > 1): ?>
  <div class="card-footer">
    <div class="pagination" style="padding:8px 0 0">
      <?php $base = "?q=$q&status=$status"; ?>
      <?php if ($pag['current']>1): ?><a href="<?=$base?>&page=<?=$pag['current']-1?>"><i class="fas fa-chevron-left"></i></a><?php else: ?><span class="disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
      <?php for ($i=max(1,$pag['current']-2);$i<=min($pag['pages'],$pag['current']+2);$i++): ?>
      <?php if($i===$pag['current']): ?><span class="active"><?=$i?></span><?php else: ?><a href="<?=$base?>&page=<?=$i?>"><?=$i?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($pag['current']<$pag['pages']): ?><a href="<?=$base?>&page=<?=$pag['current']+1?>"><i class="fas fa-chevron-right"></i></a><?php else: ?><span class="disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ── Add Rider Modal ─────────────────────────────────── -->
<div class="modal-overlay" id="add-rider-modal">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <span class="modal-title">Add New Rider</span>
      <button class="modal-close"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="<?= SITE_URL ?>/ajax/riders_admin.php" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <!-- Avatar -->
        <div style="text-align:center;margin-bottom:16px">
          <img id="add-avatar-preview" src="<?= SITE_URL ?>/assets/images/default-avatar.svg"
               style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #fed7aa;margin-bottom:10px">
          <br>
          <label for="add-avatar-file" class="btn btn-outline btn-sm" style="cursor:pointer">
            <i class="fas fa-camera"></i> Upload Photo
          </label>
          <input type="file" id="add-avatar-file" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:none" data-preview="add-avatar-preview">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="margin:0">
            <label class="form-label">First Name <span style="color:var(--danger)">*</span></label>
            <input type="text" name="first_name" class="form-control" placeholder="Juan" required>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Last Name <span style="color:var(--danger)">*</span></label>
            <input type="text" name="last_name" class="form-control" placeholder="Dela Cruz" required>
          </div>
        </div>
        <div class="form-group mt-16">
          <label class="form-label">Email <span style="color:var(--danger)">*</span></label>
          <input type="email" name="email" class="form-control" placeholder="rider@example.com" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="margin:0">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" placeholder="juan_rider" autocomplete="off">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" placeholder="09XX XXX XXXX">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px">
          <div class="form-group" style="margin:0">
            <label class="form-label">Password <span style="color:var(--danger)">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Min. 8 chars" required>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Confirm Password <span style="color:var(--danger)">*</span></label>
            <input type="password" name="confirm_pw" class="form-control" required>
          </div>
        </div>
        <div class="form-group mt-16">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" onclick="closeModal('add-rider-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Rider</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Rider Modal ────────────────────────────────── -->
<div class="modal-overlay" id="edit-rider-modal">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <span class="modal-title">Edit Rider</span>
      <button class="modal-close"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="<?= SITE_URL ?>/ajax/riders_admin.php" enctype="multipart/form-data" id="edit-rider-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-rider-id">
      <div class="modal-body">
        <div style="text-align:center;margin-bottom:16px">
          <img id="edit-avatar-preview" src="<?= SITE_URL ?>/assets/images/default-avatar.svg"
               style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #fed7aa;margin-bottom:10px">
          <br>
          <label for="edit-avatar-file" class="btn btn-outline btn-sm" style="cursor:pointer">
            <i class="fas fa-camera"></i> Change Photo
          </label>
          <input type="file" id="edit-avatar-file" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:none" data-preview="edit-avatar-preview">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="margin:0">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" id="edit-first" class="form-control" required>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" id="edit-last" class="form-control" required>
          </div>
        </div>
        <div class="form-group mt-16">
          <label class="form-label">Email</label>
          <input type="email" name="email" id="edit-email" class="form-control" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="margin:0">
            <label class="form-label">Username</label>
            <input type="text" name="username" id="edit-username" class="form-control">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" id="edit-phone" class="form-control">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px">
          <div class="form-group" style="margin:0">
            <label class="form-label">New Password</label>
            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_pw" class="form-control">
          </div>
        </div>
        <div class="form-group mt-16">
          <label class="form-label">Status</label>
          <select name="status" id="edit-status" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" onclick="closeModal('edit-rider-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function editRider(r) {
  const f = document.getElementById('edit-rider-form');
  document.getElementById('edit-rider-id').value = r.id;
  document.getElementById('edit-first').value     = r.first_name || r.name?.split(' ')[0] || '';
  document.getElementById('edit-last').value      = r.last_name  || r.name?.split(' ').slice(1).join(' ') || '';
  document.getElementById('edit-email').value     = r.email || '';
  document.getElementById('edit-username').value  = r.username || '';
  document.getElementById('edit-phone').value     = r.phone || '';
  document.getElementById('edit-status').value    = r.status || 'active';
  const prev = document.getElementById('edit-avatar-preview');
  if (prev) prev.src = r.avatar && r.avatar !== 'default.png'
    ? '<?= SITE_URL ?>/assets/uploads/avatars/' + r.avatar
    : '<?= SITE_URL ?>/assets/images/default-avatar.svg';
  openModal('edit-rider-modal');
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
