<?php
require_once __DIR__ . '/../includes/auth.php';
require_customer();
$page_title = 'My Orders';
$db = getDB();

$status_filter = $_GET['status'] ?? '';
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 8;

$where  = 'WHERE o.user_id = ?';
$params = [$_SESSION['user_id']];
if ($status_filter) { $where .= ' AND o.status = ?'; $params[] = $status_filter; }

$total = $db->prepare("SELECT COUNT(*) FROM orders o $where");
$total->execute($params);
$count = (int)$total->fetchColumn();
$pag   = paginate($count, $limit, $page);

$st = $db->prepare("SELECT o.*, COUNT(oi.id) AS item_count FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id $where GROUP BY o.id ORDER BY o.created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}");
$st->execute($params);
$orders = $st->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1><i class="fas fa-box" style="color:var(--primary)"></i> My Orders</h1>
    <p>Track and manage your purchases</p>
  </div>
</div>

<div class="container" style="padding-bottom:60px">
  <!-- Status filter tabs -->
  <div style="display:flex;gap:8px;margin-bottom:20px;overflow-x:auto;padding-bottom:4px">
    <?php
    $statuses = [''=> 'All Orders','pending'=>'Pending','processing'=>'Processing','shipped'=>'Shipped','delivered'=>'Delivered','cancelled'=>'Cancelled'];
    foreach ($statuses as $sv => $sl):
    ?>
    <a href="?status=<?= $sv ?>"
       style="padding:8px 18px;border-radius:20px;font-size:.82rem;font-weight:600;white-space:nowrap;border:2px solid <?= $status_filter === $sv ? 'var(--primary)' : 'var(--border)' ?>;color:<?= $status_filter === $sv ? 'var(--white)' : 'var(--text-muted)' ?>;background:<?= $status_filter === $sv ? 'var(--primary)' : 'var(--white)' ?>;text-decoration:none;transition:all .2s">
      <?= $sl ?>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($orders): ?>
  <div style="display:flex;flex-direction:column;gap:14px">
    <?php foreach ($orders as $order): ?>
    <div class="card">
      <div class="card-body" style="padding:18px 22px">
        <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:10px">
          <div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
              <span style="font-weight:700;color:var(--primary)"><?= e($order['order_number']) ?></span>
              <?= status_badge($order['status']) ?>
              <?= status_badge($order['payment_status']) ?>
            </div>
            <div style="font-size:.8rem;color:var(--text-muted)">
              <i class="fas fa-clock" style="margin-right:4px"></i>
              <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
              &nbsp;·&nbsp; <?= $order['item_count'] ?> item<?= $order['item_count'] !== '1' ? 's' : '' ?>
              &nbsp;·&nbsp; <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?>
            </div>
          </div>
          <div style="text-align:right">
            <div style="font-size:1.2rem;font-weight:800;color:var(--dark)"><?= currency($order['total_amount']) ?></div>
            <a href="<?= SITE_URL ?>/customer/order_detail.php?id=<?= $order['id'] ?>"
               class="btn btn-outline btn-sm mt-8">
              <i class="fas fa-eye"></i> View Details
            </a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($pag['pages'] > 1): ?>
  <div class="pagination">
    <?php if ($pag['current'] > 1): ?>
    <a href="?status=<?= $status_filter ?>&page=<?= $pag['current'] - 1 ?>"><i class="fas fa-chevron-left"></i></a>
    <?php else: ?><span class="disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
    <?php for ($i = max(1, $pag['current']-2); $i <= min($pag['pages'], $pag['current']+2); $i++): ?>
    <?php if ($i === $pag['current']): ?>
    <span class="active"><?= $i ?></span>
    <?php else: ?>
    <a href="?status=<?= $status_filter ?>&page=<?= $i ?>"><?= $i ?></a>
    <?php endif; ?>
    <?php endfor; ?>
    <?php if ($pag['current'] < $pag['pages']): ?>
    <a href="?status=<?= $status_filter ?>&page=<?= $pag['current'] + 1 ?>"><i class="fas fa-chevron-right"></i></a>
    <?php else: ?><span class="disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="empty-state card">
    <div class="card-body">
      <div class="empty-icon"><i class="fas fa-box-open"></i></div>
      <h3>No orders found</h3>
      <p>You haven't placed any orders yet.</p>
      <a href="<?= SITE_URL ?>/customer/index.php" class="btn btn-primary mt-16">Start Shopping</a>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
