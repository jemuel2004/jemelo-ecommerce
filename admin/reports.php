<?php
$page_title  = 'Sales Reports';
$active_menu = 'reports';
require_once __DIR__ . '/../includes/admin_header.php';
$db = getDB();

$range = $_GET['range'] ?? '30';
$from  = $_GET['from']  ?? date('Y-m-d', strtotime("-{$range} days"));
$to    = $_GET['to']    ?? date('Y-m-d');

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

// Summary stats
$summary = $db->prepare("SELECT
    COUNT(*) AS total_orders,
    COALESCE(SUM(total_amount),0) AS total_revenue,
    COALESCE(AVG(total_amount),0) AS avg_order,
    COUNT(DISTINCT user_id) AS unique_customers
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'");
$summary->execute([$from, $to]);
$s = $summary->fetch();

// Daily revenue for chart
$daily = $db->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS orders, SUM(total_amount) AS revenue
    FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'
    GROUP BY DATE(created_at) ORDER BY d");
$daily->execute([$from, $to]);
$daily_data = $daily->fetchAll();

// Build chart arrays
$chart_days = []; $chart_orders = []; $chart_rev = [];
$current_day = new DateTime($from);
$end_day     = new DateTime($to);
$daily_map   = array_column($daily_data, null, 'd');
while ($current_day <= $end_day) {
    $key = $current_day->format('Y-m-d');
    $chart_days[]   = $current_day->format('M j');
    $chart_orders[] = (int)(($daily_map[$key] ?? [])['orders'] ?? 0);
    $chart_rev[]    = (float)(($daily_map[$key] ?? [])['revenue'] ?? 0);
    $current_day->modify('+1 day');
}

// Revenue by category
$by_category = $db->prepare("SELECT c.name, COALESCE(SUM(oi.subtotal),0) AS revenue, COALESCE(SUM(oi.quantity),0) AS items_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id=o.id
    JOIN products p ON oi.product_id=p.id
    JOIN categories c ON p.category_id=c.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'cancelled'
    GROUP BY c.id ORDER BY revenue DESC LIMIT 6");
$by_category->execute([$from, $to]);
$categories = $by_category->fetchAll();

// Top products
$top_products = $db->prepare("SELECT p.name, p.image, SUM(oi.quantity) AS sold, SUM(oi.subtotal) AS revenue
    FROM order_items oi JOIN orders o ON oi.order_id=o.id JOIN products p ON oi.product_id=p.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status!='cancelled'
    GROUP BY oi.product_id ORDER BY sold DESC LIMIT 8");
$top_products->execute([$from, $to]);
$top_prods = $top_products->fetchAll();

// Order status distribution
$status_dist = $db->prepare("SELECT status, COUNT(*) cnt, SUM(total_amount) rev FROM orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status");
$status_dist->execute([$from, $to]);
$status_rows = $status_dist->fetchAll();
?>

<div class="admin-page-header">
  <div>
    <div class="admin-page-title">Sales Reports</div>
    <div class="admin-page-subtitle">Analytics & performance insights</div>
  </div>
</div>

<!-- Date range filter -->
<div class="admin-card mb-20">
  <div class="admin-card-body">
    <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach (['7'=>'7 Days','30'=>'30 Days','90'=>'3 Months','365'=>'1 Year'] as $v => $l): ?>
        <a href="?range=<?= $v ?>" class="btn btn-sm <?= $range === $v ? 'btn-primary' : 'btn-outline-dark' ?>"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;align-items:center;gap:8px;margin-left:auto;flex-wrap:wrap">
        <label style="font-size:.82rem;color:var(--text-muted)">Custom range:</label>
        <input type="date" name="from" class="form-control" value="<?= $from ?>" style="width:auto;font-size:.85rem;padding:8px 12px">
        <span style="color:var(--text-muted)">to</span>
        <input type="date" name="to"   class="form-control" value="<?= $to ?>"   style="width:auto;font-size:.85rem;padding:8px 12px">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
      </div>
    </form>
  </div>
</div>

<!-- Summary cards -->
<div class="stats-grid mb-20">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-peso-sign"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Revenue</div>
      <div class="stat-value"><?= currency((float)$s['total_revenue']) ?></div>
      <div class="stat-change" style="color:var(--text-muted)">Excl. cancelled</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-shopping-bag"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Orders</div>
      <div class="stat-value"><?= number_format($s['total_orders']) ?></div>
      <div class="stat-change" style="color:var(--text-muted)">In selected range</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-receipt"></i></div>
    <div class="stat-info">
      <div class="stat-label">Avg. Order Value</div>
      <div class="stat-value"><?= currency((float)$s['avg_order']) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-users"></i></div>
    <div class="stat-info">
      <div class="stat-label">Unique Customers</div>
      <div class="stat-value"><?= number_format($s['unique_customers']) ?></div>
    </div>
  </div>
</div>

<!-- Revenue chart -->
<div class="admin-card mb-20">
  <div class="admin-card-header">
    <span class="admin-card-title"><i class="fas fa-chart-area" style="color:var(--primary)"></i> Daily Revenue & Orders</span>
    <span style="font-size:.8rem;color:var(--text-muted)"><?= date('M j', strtotime($from)) ?> – <?= date('M j, Y', strtotime($to)) ?></span>
  </div>
  <div class="admin-card-body">
    <div class="chart-wrap" style="height:300px"><canvas id="revenueChart"></canvas></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
  <!-- Top products table -->
  <div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title">Top Selling Products</span></div>
    <div class="admin-card-body no-pad">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Product</th><th>Sold</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($top_prods as $tp): ?>
            <tr>
              <td style="font-size:.84rem;font-weight:600">
                <div style="display:flex;gap:10px;align-items:center">
                  <img src="<?= product_img($tp['image']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
                  <?= e($tp['name']) ?>
                </div>
              </td>
              <td><?= number_format($tp['sold']) ?></td>
              <td style="font-weight:700"><?= currency($tp['revenue']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$top_prods): ?>
            <tr><td colspan="3" style="text-align:center;padding:24px;color:var(--text-muted)">No data</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Category breakdown -->
  <div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title">Revenue by Category</span></div>
    <div class="admin-card-body">
      <?php if ($categories): ?>
      <?php $max_rev = max(array_column($categories,'revenue') ?: [1]); ?>
      <?php foreach ($categories as $cat): ?>
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;font-size:.84rem;margin-bottom:4px">
          <span style="font-weight:600"><?= e($cat['name']) ?></span>
          <span style="color:var(--primary);font-weight:700"><?= currency($cat['revenue']) ?></span>
        </div>
        <div style="background:var(--bg-alt);border-radius:4px;height:8px;overflow:hidden">
          <div style="height:100%;background:var(--primary);border-radius:4px;width:<?= $max_rev > 0 ? round($cat['revenue']/$max_rev*100) : 0 ?>%;transition:width .6s ease"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <p style="text-align:center;color:var(--text-muted);padding:20px 0">No data for this period.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Status distribution table -->
<div class="admin-card">
  <div class="admin-card-header"><span class="admin-card-title">Order Status Breakdown</span></div>
  <div class="admin-card-body no-pad">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Status</th><th>Count</th><th>Revenue</th><th>% of Total Orders</th></tr></thead>
        <tbody>
          <?php $grand_total = array_sum(array_column($status_rows,'cnt')) ?: 1; ?>
          <?php foreach ($status_rows as $sr): ?>
          <tr>
            <td><?= status_badge($sr['status']) ?></td>
            <td><?= number_format($sr['cnt']) ?></td>
            <td><?= currency($sr['rev']) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="background:var(--bg-alt);border-radius:4px;height:6px;flex:1;overflow:hidden">
                  <div style="height:100%;background:var(--primary);border-radius:4px;width:<?= round($sr['cnt']/$grand_total*100) ?>%"></div>
                </div>
                <span style="font-size:.8rem;color:var(--text-muted);min-width:35px"><?= round($sr['cnt']/$grand_total*100) ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
const ctx = document.getElementById('revenueChart')?.getContext('2d');
if (ctx) {
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($chart_days) ?>,
      datasets: [
        { label: 'Revenue (₱)', data: <?= json_encode($chart_rev) ?>, borderColor: '#2563EB', backgroundColor: 'rgba(37,99,235,.08)', borderWidth: 2.5, pointRadius: 3, fill: true, tension: .35, yAxisID: 'y' },
        { label: 'Orders',      data: <?= json_encode($chart_orders) ?>, borderColor: '#10B981', backgroundColor: 'transparent', borderWidth: 2, pointRadius: 3, tension: .35, yAxisID: 'y1' }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top' } },
      scales: {
        y:  { beginAtZero: true, grid: { color: '#F1F5F9' } },
        y1: { beginAtZero: true, position: 'right', grid: { display: false } }
      }
    }
  });
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
