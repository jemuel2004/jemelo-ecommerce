<?php
require_once __DIR__ . '/../includes/auth.php';
require_rider();   // Guard before any DB work or HTML output

$page_title  = 'Dashboard';
$active_menu = 'dashboard';
$db  = getDB();
$rid = (int)$_SESSION['user_id'];

// ── Stats ──────────────────────────────────────────────────
$stTotal = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE rider_id=?");
$stTotal->execute([$rid]);
$total = (int)$stTotal->fetchColumn();

$stToday = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE rider_id=? AND DATE(assigned_at)=CURDATE()");
$stToday->execute([$rid]);
$today = (int)$stToday->fetchColumn();

$stPend = $db->prepare("SELECT COUNT(*) FROM orders WHERE rider_id=? AND status IN ('processing','picked_up','out_for_delivery')");
$stPend->execute([$rid]);
$pending = (int)$stPend->fetchColumn();

$stDone = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE rider_id=? AND status='delivered'");
$stDone->execute([$rid]);
$delivered = (int)$stDone->fetchColumn();

$stFail = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE rider_id=? AND status='failed'");
$stFail->execute([$rid]);
$failed = (int)$stFail->fetchColumn();

// ── Active deliveries ──────────────────────────────────────
$actSt = $db->prepare("
    SELECT o.*, u.name AS customer_name, u.phone AS customer_phone,
           d.status AS d_status, d.assigned_at
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN deliveries d ON d.order_id = o.id AND d.rider_id = ?
    WHERE o.rider_id = ? AND o.status IN ('processing','picked_up','out_for_delivery')
    ORDER BY o.created_at DESC
    LIMIT 10
");
$actSt->execute([$rid, $rid]);
$active_orders = $actSt->fetchAll();

require_once __DIR__ . '/../includes/rider_header.php';
?>

<div class="rider-page-header">
  <div>
    <div class="rider-page-title">
      Welcome back, <?= e(explode(' ', $_SESSION['user_name'])[0]) ?>!
      <i class="fas fa-motorcycle" style="color:var(--rider-primary)"></i>
    </div>
    <div class="rider-page-sub"><?= date('l, F j, Y') ?></div>
  </div>
</div>

<!-- Stats -->
<div class="rider-stats-grid">
  <div class="rider-stat-card">
    <div class="rider-stat-icon orange"><i class="fas fa-motorcycle"></i></div>
    <div>
      <div class="rider-stat-value"><?= $pending ?></div>
      <div class="rider-stat-label">Active Deliveries</div>
    </div>
  </div>
  <div class="rider-stat-card">
    <div class="rider-stat-icon green"><i class="fas fa-check-circle"></i></div>
    <div>
      <div class="rider-stat-value"><?= $delivered ?></div>
      <div class="rider-stat-label">Delivered</div>
    </div>
  </div>
  <div class="rider-stat-card">
    <div class="rider-stat-icon blue"><i class="fas fa-calendar-day"></i></div>
    <div>
      <div class="rider-stat-value"><?= $today ?></div>
      <div class="rider-stat-label">Today's Assignments</div>
    </div>
  </div>
  <div class="rider-stat-card">
    <div class="rider-stat-icon red"><i class="fas fa-times-circle"></i></div>
    <div>
      <div class="rider-stat-value"><?= $failed ?></div>
      <div class="rider-stat-label">Failed Deliveries</div>
    </div>
  </div>
</div>

<!-- Active Deliveries -->
<div class="rider-card">
  <div class="rider-card-header">
    <span class="rider-card-title">
      <i class="fas fa-truck" style="color:var(--rider-primary)"></i> Active Deliveries
    </span>
    <a href="<?= SITE_URL ?>/rider/deliveries.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="rider-card-body no-pad">
    <?php if (empty($active_orders)): ?>
    <div class="rider-empty">
      <i class="fas fa-box-open"></i>
      <h3>No active deliveries</h3>
      <p>You have no pending or in-progress deliveries right now.</p>
    </div>
    <?php else: foreach ($active_orders as $o): ?>
    <?php $dStatus = $o['d_status'] ?? 'pending'; ?>
    <div class="delivery-item">
      <div class="delivery-item-icon"><i class="fas fa-box"></i></div>
      <div class="delivery-item-body">
        <div class="delivery-order-num"><?= e($o['order_number']) ?></div>
        <div class="delivery-customer"><?= e($o['customer_name']) ?></div>
        <div class="delivery-address">
          <i class="fas fa-map-marker-alt"></i> <?= e($o['shipping_address']) ?>
        </div>
        <div class="delivery-meta">
          <span><i class="fas fa-phone"></i><?= e($o['customer_phone'] ?: $o['shipping_phone']) ?></span>
          <span><i class="fas fa-money-bill"></i><?= currency((float)$o['total_amount']) ?></span>
          <span><i class="fas fa-clock"></i><?= time_ago($o['assigned_at'] ?? $o['created_at']) ?></span>
        </div>
        <!-- Action button based on current status -->
        <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
          <?php if ($o['status'] === 'processing'): ?>
          <a href="<?= SITE_URL ?>/rider/deliveries.php?id=<?= $o['id'] ?>"
             class="btn btn-sm btn-primary">
            <i class="fas fa-motorcycle"></i> Pick Up Order
          </a>
          <?php elseif ($o['status'] === 'picked_up'): ?>
          <a href="<?= SITE_URL ?>/rider/deliveries.php?id=<?= $o['id'] ?>"
             class="btn btn-sm btn-warning">
            <i class="fas fa-truck"></i> Start Delivery
          </a>
          <?php elseif ($o['status'] === 'out_for_delivery'): ?>
          <a href="<?= SITE_URL ?>/rider/deliveries.php?id=<?= $o['id'] ?>"
             class="btn btn-sm btn-success">
            <i class="fas fa-check-circle"></i> Mark Delivered
          </a>
          <?php endif; ?>
        </div>
      </div>
      <div class="delivery-actions">
        <?= delivery_status_badge($dStatus) ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Performance Summary -->
<?php if ($total > 0): ?>
<div class="rider-card" style="margin-top:20px">
  <div class="rider-card-header">
    <span class="rider-card-title">
      <i class="fas fa-chart-bar" style="color:var(--rider-primary)"></i> Performance Summary
    </span>
  </div>
  <div class="rider-card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:16px">
      <?php
      $rate = $total > 0 ? round($delivered / $total * 100) : 0;
      $stats_perf = [
          ['Total Assigned',  $total,     'fa-list-ul',      '#3b82f6'],
          ['Success Rate',    $rate.'%',  'fa-percentage',   '#10b981'],
          ['Total Delivered', $delivered, 'fa-check-circle', '#10b981'],
          ['Failed',          $failed,    'fa-times-circle', '#ef4444'],
      ];
      foreach ($stats_perf as [$lbl, $val, $ico, $col]): ?>
      <div style="text-align:center;padding:14px;background:var(--bg);border-radius:var(--radius-lg)">
        <i class="fas <?= $ico ?>" style="font-size:1.4rem;color:<?= $col ?>;margin-bottom:8px;display:block"></i>
        <div style="font-size:1.4rem;font-weight:800;color:var(--text)"><?= $val ?></div>
        <div style="font-size:.74rem;color:var(--text-muted)"><?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/rider_footer.php'; ?>
