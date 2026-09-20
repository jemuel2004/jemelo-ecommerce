<?php
require_once __DIR__ . '/../includes/auth.php';
require_rider();   // Guard before any DB work

$page_title  = 'My Deliveries';
$active_menu = 'deliveries';
$db  = getDB();
$rid = (int)$_SESSION['user_id'];

// ── Single delivery detail view ───────────────────────────
$view_id = (int)($_GET['id'] ?? 0);
$view_order = null;
if ($view_id) {
    $vs = $db->prepare("
        SELECT o.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email,
               d.status AS d_status, d.assigned_at, d.picked_up_at, d.out_for_delivery_at, d.delivered_at, d.notes AS d_notes
        FROM orders o
        JOIN users u ON u.id = o.user_id
        LEFT JOIN deliveries d ON d.order_id = o.id AND d.rider_id = ?
        WHERE o.id = ? AND o.rider_id = ?
    ");
    $vs->execute([$rid, $view_id, $rid]);
    $view_order = $vs->fetch();
    if (!$view_order) redirect(SITE_URL . '/rider/deliveries.php');
    $active_menu = 'deliveries';
}

// ── List filters ──────────────────────────────────────────
$filter = $_GET['filter'] ?? 'active';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 12;

$where  = ['o.rider_id = ?'];
$params = [$rid];

if ($filter === 'active') {
    $where[] = "o.status IN ('processing','picked_up','out_for_delivery')";
} elseif ($filter === 'delivered') {
    $where[] = "o.status = 'delivered'";
    $active_menu = 'history';
} elseif ($filter === 'failed') {
    $where[] = "o.status = 'failed'";
    $active_menu = 'history';
}

$ws  = 'WHERE ' . implode(' AND ', $where);
$cnt = $db->prepare("SELECT COUNT(*) FROM orders o $ws");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pag   = paginate($total, $limit, $page);

$st = $db->prepare("
    SELECT o.*, u.name AS customer_name, u.phone AS customer_phone,
           d.status AS d_status, d.assigned_at, d.picked_up_at, d.delivered_at
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN deliveries d ON d.order_id = o.id AND d.rider_id = ?
    $ws
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
");
$st->execute(array_merge([$rid], $params, [$pag['per_page'], $pag['offset']]));
$orders = $st->fetchAll();

require_once __DIR__ . '/../includes/rider_header.php';
?>

<?php if ($view_order): ?>
<!-- ── Single Delivery Detail ──────────────────────────── -->
<div class="rider-page-header">
  <div>
    <div class="rider-page-title">Delivery: <?= e($view_order['order_number']) ?></div>
    <div class="rider-page-sub"><?= e($view_order['customer_name']) ?> &mdash; <?= currency((float)$view_order['total_amount']) ?></div>
  </div>
  <a href="<?= SITE_URL ?>/rider/deliveries.php" class="btn btn-outline btn-sm">
    <i class="fas fa-arrow-left"></i> Back
  </a>
</div>

<?php
$dStatus = $view_order['d_status'] ?? 'pending';
$oStatus = $view_order['status'];
$isCod   = in_array($view_order['payment_method'] ?? '', ['cod', 'cash_on_delivery']);
$stepsMap = [
    ['key' => 'processing',        'label' => 'Assigned',        'icon' => 'fa-clipboard-check'],
    ['key' => 'picked_up',         'label' => 'Picked Up',       'icon' => 'fa-box'],
    ['key' => 'out_for_delivery',  'label' => 'Out for Delivery','icon' => 'fa-motorcycle'],
    ['key' => 'delivered',         'label' => 'Delivered',       'icon' => 'fa-check-circle'],
];
$stOrder = ['processing' => 0, 'picked_up' => 1, 'out_for_delivery' => 2, 'delivered' => 3, 'failed' => 3];
$curStep  = $stOrder[$oStatus] ?? 0;
?>

<!-- Progress steps -->
<div class="rider-card mb-20">
  <div class="rider-card-body">
    <div class="status-steps">
      <?php foreach ($stepsMap as $i => $step): ?>
      <div class="status-step <?= $i < $curStep ? 'done' : ($i === $curStep ? 'active' : '') ?>">
        <div class="step-dot"><i class="fas <?= $step['icon'] ?>"></i></div>
        <div class="step-label"><?= $step['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($oStatus === 'failed'): ?>
    <div class="alert alert-danger mt-12">
      <i class="fas fa-times-circle"></i> This delivery was marked as failed.
    </div>
    <?php elseif ($oStatus === 'delivered'): ?>
    <div class="alert alert-success mt-12">
      <i class="fas fa-check-circle"></i> Delivery completed successfully!
      <?php if ($isCod ?? false): ?>
      COD payment of <strong><?= currency((float)$view_order['total_amount']) ?></strong> collected.
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- COD badge -->
    <?php if ($isCod && $oStatus !== 'delivered' && $oStatus !== 'failed'): ?>
    <div style="display:flex;align-items:center;gap:8px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:8px 14px;margin-top:12px">
      <i class="fas fa-money-bill-wave" style="color:#f97316"></i>
      <span style="font-size:.83rem;font-weight:600;color:#92400e">
        COD — Collect <strong><?= currency((float)$view_order['total_amount']) ?></strong> on delivery
      </span>
    </div>
    <?php endif; ?>

    <!-- Action buttons -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;justify-content:center">
      <?php if ($oStatus === 'processing'): ?>
      <button class="btn btn-primary btn-lg"
              data-update-delivery="<?= $view_order['id'] ?>"
              data-status="picked_up"
              data-confirm="Confirm you have picked up this order from the store?">
        <i class="fas fa-box"></i> Confirm Pick Up
      </button>
      <?php elseif ($oStatus === 'picked_up'): ?>
      <button class="btn btn-warning btn-lg"
              data-update-delivery="<?= $view_order['id'] ?>"
              data-status="out_for_delivery"
              data-confirm="Confirm you are now on your way to deliver this order?">
        <i class="fas fa-motorcycle"></i> Start Delivery
      </button>
      <?php elseif ($oStatus === 'out_for_delivery'): ?>
      <button class="btn btn-success btn-lg"
              data-update-delivery="<?= $view_order['id'] ?>"
              data-status="delivered"
              data-confirm="<?= $isCod
                ? 'Confirm: Order delivered AND ₱' . number_format((float)$view_order['total_amount'], 2) . ' COD cash collected?'
                : 'Confirm this order has been delivered?' ?>">
        <i class="fas fa-check-circle"></i> Mark as Delivered<?= $isCod ? ' & Paid' : '' ?>
      </button>
      <button class="btn btn-danger"
              data-update-delivery="<?= $view_order['id'] ?>"
              data-status="failed"
              data-confirm="Mark this delivery as failed? This cannot be undone.">
        <i class="fas fa-times-circle"></i> Delivery Failed
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">
  <div>
    <!-- Customer Details -->
    <div class="rider-card mb-20">
      <div class="rider-card-header"><span class="rider-card-title">Customer Details</span></div>
      <div class="rider-card-body">
        <div style="display:flex;flex-direction:column;gap:10px">
          <div style="display:flex;gap:12px;align-items:center">
            <i class="fas fa-user" style="color:var(--rider-primary);width:18px"></i>
            <span class="font-bold"><?= e($view_order['customer_name']) ?></span>
          </div>
          <div style="display:flex;gap:12px;align-items:center">
            <i class="fas fa-phone" style="color:var(--rider-primary);width:18px"></i>
            <a href="tel:<?= e($view_order['customer_phone'] ?: $view_order['shipping_phone']) ?>" style="color:var(--rider-primary)">
              <?= e($view_order['customer_phone'] ?: $view_order['shipping_phone']) ?>
            </a>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start">
            <i class="fas fa-map-marker-alt" style="color:var(--rider-primary);width:18px;margin-top:2px"></i>
            <div>
              <div class="font-bold"><?= e($view_order['shipping_name']) ?></div>
              <div style="color:var(--text-muted);font-size:.83rem"><?= e($view_order['shipping_address']) ?></div>
            </div>
          </div>
          <?php if ($view_order['notes']): ?>
          <div style="background:var(--bg);border-radius:var(--radius);padding:10px;font-size:.83rem;color:var(--text-muted)">
            <i class="fas fa-sticky-note" style="color:var(--rider-warning)"></i> <?= e($view_order['notes']) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Order Items -->
    <?php
    $items_st = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
    $items_st->execute([$view_order['id']]);
    $items = $items_st->fetchAll();
    ?>
    <div class="rider-card">
      <div class="rider-card-header"><span class="rider-card-title">Items (<?= count($items) ?>)</span></div>
      <div class="rider-card-body no-pad">
        <div class="rider-table-wrap">
          <table class="rider-table">
            <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
            <tbody>
              <?php foreach ($items as $item): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:8px">
                    <img src="<?= product_img($item['product_image'] ?? '') ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
                    <span style="font-size:.84rem;font-weight:600"><?= e($item['product_name']) ?></span>
                  </div>
                </td>
                <td><?= $item['quantity'] ?></td>
                <td><?= currency($item['price']) ?></td>
                <td class="font-bold"><?= currency($item['subtotal']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="padding:14px 20px;text-align:right;border-top:1px solid var(--border)">
          <strong style="font-size:1rem">Total to Collect: <?= currency((float)$view_order['total_amount']) ?></strong>
        </div>
      </div>
    </div>
  </div>

  <!-- Right sidebar -->
  <div>
    <div class="rider-card mb-16">
      <div class="rider-card-header"><span class="rider-card-title">Order Info</span></div>
      <div class="rider-card-body" style="font-size:.84rem">
        <table style="width:100%;border-collapse:collapse">
          <tr><td style="padding:5px 0;color:var(--text-muted)">Order #</td><td class="font-bold" style="color:var(--rider-primary)"><?= e($view_order['order_number']) ?></td></tr>
          <tr><td style="padding:5px 0;color:var(--text-muted)">Status</td><td><?= status_badge($view_order['status']) ?></td></tr>
          <tr><td style="padding:5px 0;color:var(--text-muted)">Payment</td><td><?= ucfirst($view_order['payment_method'] ?? 'COD') ?></td></tr>
          <tr><td style="padding:5px 0;color:var(--text-muted)">Pay Status</td><td><?= status_badge($view_order['payment_status']) ?></td></tr>
          <tr><td style="padding:5px 0;color:var(--text-muted)">Amount</td><td class="font-bold"><?= currency((float)$view_order['total_amount']) ?></td></tr>
        </table>
      </div>
    </div>

    <div class="rider-card">
      <div class="rider-card-header"><span class="rider-card-title">Delivery Timeline</span></div>
      <div class="rider-card-body" style="font-size:.82rem">
        <?php
        $timeline = [
            ['Assigned',         $view_order['assigned_at']],
            ['Picked Up',        $view_order['picked_up_at']],
            ['Out for Delivery', $view_order['out_for_delivery_at']],
            ['Delivered',        $view_order['delivered_at']],
        ];
        foreach ($timeline as [$tl, $dt]):
        if (!$dt) continue;
        ?>
        <div style="display:flex;gap:10px;margin-bottom:10px">
          <i class="fas fa-circle" style="color:var(--rider-primary);font-size:.5rem;margin-top:6px;flex-shrink:0"></i>
          <div>
            <div class="font-bold"><?= $tl ?></div>
            <div style="color:var(--text-muted)"><?= date('M j, Y g:i A', strtotime($dt)) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$view_order['assigned_at']): ?>
        <div class="text-muted text-center">No timeline yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ── Delivery List ────────────────────────────────────── -->
<div class="rider-page-header">
  <div>
    <div class="rider-page-title">My Deliveries</div>
    <div class="rider-page-sub"><?= $total ?> deliveries found</div>
  </div>
</div>

<!-- Filter tabs -->
<div style="display:flex;gap:6px;margin-bottom:20px">
  <a href="?filter=active"    class="btn btn-sm <?= $filter==='active'    ? 'btn-primary' : 'btn-outline' ?>">Active</a>
  <a href="?filter=delivered" class="btn btn-sm <?= $filter==='delivered' ? 'btn-primary' : 'btn-outline' ?>">Delivered</a>
  <a href="?filter=failed"    class="btn btn-sm <?= $filter==='failed'    ? 'btn-primary' : 'btn-outline' ?>">Failed</a>
  <a href="?filter=all"       class="btn btn-sm <?= $filter==='all'       ? 'btn-primary' : 'btn-outline' ?>">All</a>
</div>

<div class="rider-card">
  <div class="rider-card-body no-pad">
    <?php if (empty($orders)): ?>
    <div class="rider-empty">
      <i class="fas fa-motorcycle"></i>
      <h3>No deliveries found</h3>
      <p>No deliveries match the current filter.</p>
    </div>
    <?php else: foreach ($orders as $o): ?>
    <div class="delivery-item">
      <div class="delivery-item-icon"><i class="fas fa-box"></i></div>
      <div class="delivery-item-body">
        <div class="delivery-order-num"><?= e($o['order_number']) ?></div>
        <div class="delivery-customer"><?= e($o['customer_name']) ?></div>
        <div class="delivery-address"><i class="fas fa-map-marker-alt"></i> <?= e($o['shipping_address']) ?></div>
        <div class="delivery-meta">
          <span><i class="fas fa-phone"></i><?= e($o['customer_phone'] ?: $o['shipping_phone']) ?></span>
          <span><i class="fas fa-money-bill"></i><?= currency((float)$o['total_amount']) ?></span>
          <span><i class="fas fa-clock"></i><?= time_ago($o['assigned_at'] ?? $o['created_at']) ?></span>
        </div>
      </div>
      <div class="delivery-actions" style="flex-direction:column;align-items:flex-end;gap:6px">
        <?= delivery_status_badge($o['d_status'] ?? 'pending') ?>
        <a href="?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">
          <i class="fas fa-eye"></i> View
        </a>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <?php if ($pag['pages'] > 1): ?>
  <div class="rider-pagination">
    <?php $base = "?filter=$filter"; ?>
    <?php if ($pag['current']>1): ?><a href="<?= $base ?>&page=<?= $pag['current']-1 ?>"><i class="fas fa-chevron-left"></i></a><?php else: ?><span class="disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
    <?php for ($i=max(1,$pag['current']-2);$i<=min($pag['pages'],$pag['current']+2);$i++): ?>
    <?php if ($i===$pag['current']): ?><span class="active"><?=$i?></span><?php else: ?><a href="<?=$base?>&page=<?=$i?>"><?=$i?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($pag['current']<$pag['pages']): ?><a href="<?= $base ?>&page=<?= $pag['current']+1 ?>"><i class="fas fa-chevron-right"></i></a><?php else: ?><span class="disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/rider_footer.php'; ?>
