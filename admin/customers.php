<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_panel();

$page_title  = 'Customers';
$active_menu = 'customers';
$db = getDB();

// ── Toggle status — runs before any HTML output ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    verify_csrf();
    $cid = (int)($_POST['customer_id'] ?? 0);
    $db->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=? AND role='customer'")->execute([$cid]);
    flash('cust', 'Customer status updated.', 'success');
    redirect(SITE_URL . '/admin/customers.php');
}

require_once __DIR__ . '/../includes/admin_header.php';

$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;

$where  = ["role='customer'"];
$params = [];
if ($q)      { $qS = '%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%'; $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)"; $params = array_merge($params, [$qS,$qS,$qS]); }
if ($status) { $where[] = "status = ?"; $params[] = $status; }

$ws = 'WHERE ' . implode(' AND ', $where);
$count_st = $db->prepare("SELECT COUNT(*) FROM users $ws"); $count_st->execute($params);
$total = (int)$count_st->fetchColumn();
$pag   = paginate($total, $limit, $page);

$st = $db->prepare("SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id=u.id) AS order_count, (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=u.id AND status!='cancelled') AS total_spent FROM users u $ws ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
$st->execute(array_merge($params, [$pag['per_page'], $pag['offset']]));
$customers = $st->fetchAll();
?>

<div class="admin-page-header">
  <div>
    <div class="admin-page-title">Customers</div>
    <div class="admin-page-subtitle"><?= $total ?> registered customers</div>
  </div>
</div>

<?php show_flash('cust'); ?>

<div class="admin-card">
  <div class="admin-card-header">
    <form method="GET" class="filter-bar" style="width:100%">
      <div class="search-input-wrap">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Search by name, email, phone…" value="<?= e($q) ?>">
      </div>
      <select name="status" class="filter-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="<?= SITE_URL ?>/admin/customers.php" class="btn btn-outline-dark btn-sm">Reset</a>
    </form>
  </div>
  <div class="admin-card-body no-pad">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Customer</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody id="table-body">
          <?php foreach ($customers as $c): ?>
          <tr>
            <td style="color:var(--text-muted)"><?= $c['id'] ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:38px;height:38px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--primary);font-size:.85rem;flex-shrink:0">
                  <?= strtoupper(substr($c['name'],0,1)) ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:.88rem"><?= e($c['name']) ?></div>
                  <div style="font-size:.75rem;color:var(--text-muted)"><?= e($c['email']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-size:.84rem"><?= e($c['phone'] ?? '—') ?></td>
            <td><span class="badge badge-primary"><?= $c['order_count'] ?></span></td>
            <td style="font-weight:600"><?= currency((float)$c['total_spent']) ?></td>
            <td style="font-size:.8rem;color:var(--text-muted)"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
            <td><?= status_badge($c['status']) ?></td>
            <td>
              <div style="display:flex;gap:6px">
                <a href="<?= SITE_URL ?>/admin/orders.php?q=<?= urlencode($c['email']) ?>" class="btn btn-sm btn-outline" title="View orders">
                  <i class="fas fa-box"></i>
                </a>
                <form method="POST" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                  <button type="submit" name="toggle_status"
                          class="btn btn-sm <?= $c['status']==='active' ? 'btn-danger' : 'btn-success' ?>"
                          data-confirm="<?= $c['status']==='active' ? 'Deactivate' : 'Activate' ?> this customer?"
                          title="<?= $c['status']==='active' ? 'Deactivate' : 'Activate' ?>">
                    <i class="fas fa-<?= $c['status']==='active' ? 'ban' : 'check' ?>"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$customers): ?>
          <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">No customers found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($pag['pages'] > 1): ?>
  <div class="card-footer">
    <div class="pagination" style="padding:8px 0 0">
      <?php $base = "?q=$q&status=$status"; ?>
      <?php if ($pag['current']>1): ?><a href="<?= $base ?>&page=<?= $pag['current']-1 ?>"><i class="fas fa-chevron-left"></i></a><?php else: ?><span class="disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
      <?php for ($i=max(1,$pag['current']-2);$i<=min($pag['pages'],$pag['current']+2);$i++): ?>
      <?php if($i===$pag['current']): ?><span class="active"><?=$i?></span><?php else: ?><a href="<?=$base?>&page=<?=$i?>"><?=$i?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($pag['current']<$pag['pages']): ?><a href="<?=$base?>&page=<?=$pag['current']+1?>"><i class="fas fa-chevron-right"></i></a><?php else: ?><span class="disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
