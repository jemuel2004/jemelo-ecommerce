<?php
$page_title  = 'Dashboard';
$active_menu = 'dashboard';
require_once __DIR__ . '/../includes/admin_header.php';
$db = getDB();

// ── Stats ────────────────────────────────────────────────────
$revenue  = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled') AND payment_status='paid'")->fetchColumn();
$revenue_all = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled')")->fetchColumn();
$orders_total = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$orders_today = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$products_cnt = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$customers_cnt = $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$pending_cnt  = $db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$low_stock    = $db->query("SELECT COUNT(*) FROM products WHERE stock <= 5 AND stock > 0")->fetchColumn();
$out_stock    = $db->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();

// ── 7-day chart data ─────────────────────────────────────────
$chart_st = $db->query("SELECT DATE(created_at) AS d, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS rev
    FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status != 'cancelled'
    GROUP BY DATE(created_at) ORDER BY d");
$chart_raw = $chart_st->fetchAll();
$chart_labels = [];
$chart_orders = [];
$chart_revenue = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M j', strtotime($date));
    $chart_labels[] = $label;
    $found = array_values(array_filter($chart_raw, fn($r) => $r['d'] === $date));
    $chart_orders[]  = $found[0]['cnt']    ?? 0;
    $chart_revenue[] = (float)($found[0]['rev'] ?? 0);
}

// ── Recent orders ─────────────────────────────────────────────
$recent_orders = $db->query("SELECT o.*, u.name AS customer_name FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC LIMIT 6")->fetchAll();

// ── Top products ─────────────────────────────────────────────
$top_products = $db->query("SELECT p.name, p.image, SUM(oi.quantity) AS sold, SUM(oi.subtotal) AS revenue
    FROM order_items oi JOIN products p ON oi.product_id=p.id GROUP BY oi.product_id ORDER BY sold DESC LIMIT 5")->fetchAll();

// ── Low stock products ───────────────────────────────────────
$low_products = $db->query("SELECT * FROM products WHERE stock <= 5 ORDER BY stock ASC LIMIT 5")->fetchAll();
?>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-peso-sign"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Revenue</div>
      <div class="stat-value"><?= currency((float)$revenue_all) ?></div>
      <div class="stat-change up"><i class="fas fa-arrow-up"></i> All orders</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-shopping-bag"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Orders</div>
      <div class="stat-value"><?= number_format($orders_total) ?></div>
      <div class="stat-change up"><i class="fas fa-plus"></i> <?= $orders_today ?> today</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-box-open"></i></div>
    <div class="stat-info">
      <div class="stat-label">Products</div>
      <div class="stat-value"><?= number_format($products_cnt) ?></div>
      <?php if ($low_stock > 0): ?>
      <div class="stat-change down"><i class="fas fa-exclamation-triangle"></i> <?= $low_stock ?> low stock</div>
      <?php else: ?>
      <div class="stat-change up"><i class="fas fa-check"></i> All stocked</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-users"></i></div>
    <div class="stat-info">
      <div class="stat-label">Customers</div>
      <div class="stat-value"><?= number_format($customers_cnt) ?></div>
      <div class="stat-change up"><i class="fas fa-user-check"></i> Registered</div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
  <div class="admin-card">
    <div class="admin-card-header">
      <span class="admin-card-title"><i class="fas fa-chart-line" style="color:var(--primary)"></i> Orders & Revenue (Last 7 Days)</span>
    </div>
    <div class="admin-card-body">
      <div class="chart-wrap"><canvas id="salesChart"></canvas></div>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header">
      <span class="admin-card-title"><i class="fas fa-chart-pie" style="color:var(--primary)"></i> Order Status</span>
    </div>
    <div class="admin-card-body">
      <div class="chart-wrap" style="height:220px"><canvas id="statusChart"></canvas></div>
    </div>
  </div>
</div>

<!-- Bottom row -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <!-- Recent orders -->
  <div class="admin-card">
    <div class="admin-card-header">
      <span class="admin-card-title">Recent Orders</span>
      <a href="<?= SITE_URL ?>/admin/orders.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="admin-card-body no-pad">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($recent_orders as $o): ?>
            <tr>
              <td><a href="<?= SITE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>" style="font-weight:600;color:var(--primary)"><?= e($o['order_number']) ?></a><br><small><?= time_ago($o['created_at']) ?></small></td>
              <td><?= e($o['customer_name']) ?></td>
              <td><?= currency($o['total_amount']) ?></td>
              <td><?= status_badge($o['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Top products -->
  <div class="admin-card">
    <div class="admin-card-header">
      <span class="admin-card-title">Top Products</span>
      <a href="<?= SITE_URL ?>/admin/products.php" class="btn btn-outline btn-sm">Manage</a>
    </div>
    <div class="admin-card-body no-pad">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Product</th><th>Sold</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($top_products as $p): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <img src="<?= product_img($p['image']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
                  <span style="font-size:.84rem;font-weight:600"><?= e($p['name']) ?></span>
                </div>
              </td>
              <td><?= number_format($p['sold']) ?></td>
              <td><?= currency($p['revenue']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$top_products): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:20px">No sales data yet</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Alerts: Pending orders + Low stock -->
<?php if ($pending_cnt > 0 || $out_stock > 0): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <?php if ($pending_cnt > 0): ?>
  <div class="admin-card" style="border-left:4px solid var(--warning)">
    <div class="admin-card-body">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:44px;height:44px;background:#FEF3C7;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;color:var(--warning);font-size:1.2rem">
          <i class="fas fa-clock"></i>
        </div>
        <div>
          <div style="font-weight:700;font-size:1.1rem"><?= $pending_cnt ?> Pending Order<?= $pending_cnt > 1 ? 's' : '' ?></div>
          <div style="font-size:.82rem;color:var(--text-muted)">Require your attention</div>
        </div>
        <a href="<?= SITE_URL ?>/admin/orders.php?status=pending" class="btn btn-sm btn-outline" style="margin-left:auto;color:var(--warning);border-color:var(--warning)">Review</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($out_stock > 0): ?>
  <div class="admin-card" style="border-left:4px solid var(--danger)">
    <div class="admin-card-body">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:44px;height:44px;background:#FEE2E2;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;color:var(--danger);font-size:1.2rem">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div>
          <div style="font-weight:700;font-size:1.1rem"><?= $out_stock ?> Out-of-Stock</div>
          <div style="font-size:.82rem;color:var(--text-muted)">Products need restocking</div>
        </div>
        <a href="<?= SITE_URL ?>/admin/products.php?stock=0" class="btn btn-sm btn-outline" style="margin-left:auto;color:var(--danger);border-color:var(--danger)">View</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Order status chart data -->
<?php
$status_data = $db->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$all_status_cfg = [
    'pending'          => ['Pending',           '#F59E0B'],
    'processing'       => ['Processing',         '#0EA5E9'],
    'picked_up'        => ['Picked Up',          '#8B5CF6'],
    'out_for_delivery' => ['Out for Delivery',   '#F97316'],
    'shipped'          => ['Shipped',            '#2563EB'],
    'delivered'        => ['Delivered',          '#10B981'],
    'failed'           => ['Failed',             '#EF4444'],
    'cancelled'        => ['Cancelled',          '#6B7280'],
];
$status_labels = $status_counts = $status_colors = [];
foreach ($all_status_cfg as $key => [$label, $color]) {
    $cnt = (int)($status_data[$key] ?? 0);
    if ($cnt > 0) { $status_labels[] = $label; $status_counts[] = $cnt; $status_colors[] = $color; }
}
if (empty($status_labels)) { $status_labels = ['No orders']; $status_counts = [1]; $status_colors = ['#E2E8F0']; }
?>

<script>
const salesCtx = document.getElementById('salesChart')?.getContext('2d');
if (salesCtx) {
  new Chart(salesCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($chart_labels) ?>,
      datasets: [
        { label: 'Orders', data: <?= json_encode($chart_orders) ?>, backgroundColor: 'rgba(37,99,235,.15)', borderColor: '#2563EB', borderWidth: 2, borderRadius: 6, yAxisID: 'y' },
        { label: 'Revenue (₱)', data: <?= json_encode($chart_revenue) ?>, type: 'line', borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,.08)', borderWidth: 2, pointRadius: 4, fill: true, tension: .4, yAxisID: 'y1' }
      ]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true, grid: { color: '#F1F5F9' } }, y1: { beginAtZero: true, position: 'right', grid: { display: false } } } }
  });
}

const statusCtx = document.getElementById('statusChart')?.getContext('2d');
if (statusCtx) {
  new Chart(statusCtx, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($status_labels) ?>,
      datasets: [{ data: <?= json_encode($status_counts) ?>, backgroundColor: <?= json_encode($status_colors) ?>, borderWidth: 2, borderColor: '#fff' }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } } }, cutout: '65%' }
  });
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
