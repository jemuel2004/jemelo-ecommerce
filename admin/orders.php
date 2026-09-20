<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_panel();

$page_title  = 'Orders';
$active_menu = 'orders';
$db = getDB();

// ── Bulk status update — before any HTML output ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    verify_csrf();
    $ids     = array_map('intval', $_POST['order_ids'] ?? []);
    $status  = $_POST['bulk_status'] ?? '';
    $allowed = ['pending','processing','picked_up','out_for_delivery','shipped','delivered','failed','cancelled'];
    if ($ids && in_array($status, $allowed)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("UPDATE orders SET status=? WHERE id IN ($ph)")->execute(array_merge([$status], $ids));
        flash('order_msg', count($ids) . ' order(s) updated to ' . ucfirst(str_replace('_',' ',$status)) . '.', 'success');
    }
    redirect(SITE_URL . '/admin/orders.php');
}

require_once __DIR__ . '/../includes/admin_header.php';

$q        = trim($_GET['q'] ?? '');
$status_f = $_GET['status'] ?? '';
$date_f   = $_GET['date']   ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 15;

$where  = ['1=1'];
$params = [];
if ($q)        { $qS = '%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%'; $where[] = "(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)"; $params = array_merge($params, [$qS,$qS,$qS]); }
if ($status_f) { $where[] = "o.status = ?"; $params[] = $status_f; }
if ($date_f)   { $where[] = "DATE(o.created_at) = ?"; $params[] = $date_f; }

$ws = 'WHERE ' . implode(' AND ', $where);

$count_st = $db->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id=u.id LEFT JOIN users r ON o.rider_id=r.id $ws");
$count_st->execute($params);
$total = (int)$count_st->fetchColumn();
$pag   = paginate($total, $limit, $page);

$st = $db->prepare("
    SELECT o.*, u.name AS customer, u.email AS customer_email,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS items,
           r.name AS rider_name
    FROM orders o
    JOIN users u  ON o.user_id  = u.id
    LEFT JOIN users r ON o.rider_id = r.id
    $ws
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
");
$st->execute(array_merge($params, [$pag['per_page'], $pag['offset']]));
$orders = $st->fetchAll();

$status_counts = $db->query("SELECT status, COUNT(*) cnt FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="admin-page-header">
  <div>
    <div class="admin-page-title">Orders</div>
    <div class="admin-page-subtitle"><?= $total ?> total orders</div>
  </div>
</div>

<?php show_flash('order_msg'); ?>

<!-- Quick status tabs -->
<div style="display:flex;gap:8px;margin-bottom:18px;overflow-x:auto;padding-bottom:4px;flex-wrap:wrap">
  <?php
  $all_statuses = [
    ''                => 'All',
    'pending'         => 'Pending',
    'processing'      => 'Processing',
    'picked_up'       => 'Picked Up',
    'out_for_delivery'=> 'Out for Delivery',
    'shipped'         => 'Shipped',
    'delivered'       => 'Delivered',
    'failed'          => 'Failed',
    'cancelled'       => 'Cancelled',
  ];
  ?>
  <?php foreach ($all_statuses as $sv => $sl): ?>
  <a href="?status=<?= $sv ?>"
     style="padding:7px 16px;border-radius:20px;font-size:.8rem;font-weight:600;white-space:nowrap;text-decoration:none;
            border:2px solid <?= $status_f === $sv ? 'var(--primary)' : 'var(--border)' ?>;
            color:<?= $status_f === $sv ? 'var(--white)' : 'var(--text-muted)' ?>;
            background:<?= $status_f === $sv ? 'var(--primary)' : 'var(--white)' ?>">
    <?= $sl ?>
    <?php if ($sv && isset($status_counts[$sv]) && $status_counts[$sv] > 0): ?>
    <span style="opacity:.75">(<?= $status_counts[$sv] ?>)</span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="admin-card">
  <div class="admin-card-header">
    <form method="GET" class="filter-bar" style="width:100%">
      <div class="search-input-wrap">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Order number, customer…" value="<?= e($q) ?>">
      </div>
      <input type="date" name="date" class="form-control" value="<?= e($date_f) ?>"
             style="width:auto;font-size:.85rem;padding:9px 12px">
      <input type="hidden" name="status" value="<?= e($status_f) ?>">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="<?= SITE_URL ?>/admin/orders.php" class="btn btn-outline-dark btn-sm">Reset</a>
    </form>
  </div>

  <form method="POST">
    <?= csrf_field() ?>
    <div class="admin-card-body no-pad">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th><input type="checkbox" id="select-all"></th>
              <th>Order</th>
              <th>Customer</th>
              <th>Rider</th>
              <th>Items</th>
              <th>Total</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="table-body">
            <?php foreach ($orders as $o): ?>
            <tr>
              <td><input type="checkbox" name="order_ids[]" value="<?= $o['id'] ?>" class="row-check"></td>
              <td>
                <a href="<?= SITE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>"
                   style="font-weight:700;color:var(--primary)"><?= e($o['order_number']) ?></a>
              </td>
              <td>
                <div style="font-size:.86rem;font-weight:600"><?= e($o['customer']) ?></div>
                <div style="font-size:.75rem;color:var(--text-muted)"><?= e($o['customer_email']) ?></div>
              </td>
              <td>
                <?php if ($o['rider_name']): ?>
                <div style="display:flex;align-items:center;gap:6px;font-size:.82rem">
                  <i class="fas fa-motorcycle" style="color:#f97316;font-size:.75rem"></i>
                  <span style="font-weight:600"><?= e($o['rider_name']) ?></span>
                </div>
                <?php else: ?>
                <a href="<?= SITE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>#assign-rider"
                   class="btn btn-sm btn-outline"
                   style="font-size:.72rem;padding:3px 8px;white-space:nowrap"
                   title="Assign a rider to this order">
                  <i class="fas fa-motorcycle"></i> Assign
                </a>
                <?php endif; ?>
              </td>
              <td><?= $o['items'] ?></td>
              <td style="font-weight:700"><?= currency($o['total_amount']) ?></td>
              <td>
                <?= status_badge($o['payment_status']) ?>
                <br>
                <small style="color:var(--text-muted);font-size:.72rem">
                  <?= ucfirst(str_replace('_',' ',$o['payment_method'])) ?>
                </small>
              </td>
              <td>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <select class="filter-select order-status-select"
                        data-order-id="<?= $o['id'] ?>"
                        style="font-size:.8rem;padding:5px 24px 5px 8px;border-radius:6px;min-width:130px">
                  <?php foreach (['pending','processing','picked_up','out_for_delivery','shipped','delivered','failed','cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>>
                    <?= ucfirst(str_replace('_',' ',$s)) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <?php else: ?>
                <?= status_badge($o['status']) ?>
                <?php endif; ?>
              </td>
              <td style="font-size:.8rem;color:var(--text-muted)">
                <?= date('M j, Y', strtotime($o['created_at'])) ?><br>
                <?= date('g:i A', strtotime($o['created_at'])) ?>
              </td>
              <td>
                <a href="<?= SITE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>"
                   class="btn btn-sm btn-outline" title="View order details">
                  <i class="fas fa-eye"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
            <tr>
              <td colspan="10" style="text-align:center;padding:40px;color:var(--text-muted)">
                <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>
                No orders found.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($orders): ?>
    <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-size:.82rem;color:var(--text-muted)">Bulk action:</span>
        <select name="bulk_status" class="filter-select">
          <?php foreach (['pending','processing','picked_up','out_for_delivery','shipped','delivered','failed','cancelled'] as $s): ?>
          <option value="<?= $s ?>"><?= ucfirst(str_replace('_',' ',$s)) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" name="bulk_update" class="btn btn-dark btn-sm"
                data-confirm="Apply this status to all selected orders?">Apply</button>
      </div>
      <?php if ($pag['pages'] > 1): ?>
      <div class="pagination" style="padding:0">
        <?php $base = "?q=" . urlencode($q) . "&status=" . urlencode($status_f) . "&date=" . urlencode($date_f); ?>
        <?php if ($pag['current'] > 1): ?>
        <a href="<?= $base ?>&page=<?= $pag['current']-1 ?>"><i class="fas fa-chevron-left"></i></a>
        <?php else: ?>
        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
        <?php endif; ?>
        <?php for ($i = max(1,$pag['current']-2); $i <= min($pag['pages'],$pag['current']+2); $i++): ?>
        <?php if ($i === $pag['current']): ?>
        <span class="active"><?= $i ?></span>
        <?php else: ?>
        <a href="<?= $base ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
        <?php endfor; ?>
        <?php if ($pag['current'] < $pag['pages']): ?>
        <a href="<?= $base ?>&page=<?= $pag['current']+1 ?>"><i class="fas fa-chevron-right"></i></a>
        <?php else: ?>
        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
