<?php
require_once __DIR__ . '/../includes/auth.php';
require_customer();
$page_title = 'Order Details';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
$st = $db->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$st->execute([$id, $_SESSION['user_id']]);
$order = $st->fetch();
if (!$order) redirect(SITE_URL . '/customer/orders.php');

$items_st = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
$items_st->execute([$id]);
$items = $items_st->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= SITE_URL ?>/customer/orders.php">My Orders</a>
      <span>/</span>
      <span style="color:rgba(255,255,255,.8)"><?= e($order['order_number']) ?></span>
    </div>
    <h1 style="margin-top:8px">Order #<?= e($order['order_number']) ?></h1>
  </div>
</div>

<div class="container" style="padding-bottom:60px">
  <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start">

    <!-- Left -->
    <div>
      <!-- Status timeline -->
      <div class="card mb-24">
        <div class="card-header">Order Status</div>
        <div class="card-body">
          <?php
          $steps   = ['pending','processing','shipped','delivered'];
          $current = $order['status'];
          $step_i  = array_search($current, $steps) ?? 0;
          if ($current === 'cancelled') $step_i = -1;
          ?>
          <?php if ($current === 'cancelled'): ?>
          <div class="alert alert-danger" style="margin-bottom:0">
            <i class="fas fa-times-circle"></i> This order has been cancelled.
          </div>
          <?php else: ?>
          <div style="display:flex;align-items:center;position:relative;overflow:hidden">
            <?php foreach ($steps as $i => $step): ?>
            <?php $done = $i <= $step_i; ?>
            <div style="flex:1;text-align:center;position:relative">
              <?php if ($i > 0): ?>
              <div style="position:absolute;left:-50%;right:50%;top:17px;height:3px;background:<?= $done ? 'var(--primary)' : 'var(--border)' ?>"></div>
              <?php endif; ?>
              <div style="width:36px;height:36px;border-radius:50%;background:<?= $done ? 'var(--primary)' : 'var(--border)' ?>;color:<?= $done ? '#fff' : 'var(--text-muted)' ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;font-size:.85rem;font-weight:700;position:relative;z-index:1">
                <?php if ($done && $i < $step_i): ?><i class="fas fa-check"></i><?php else: ?><?= $i+1 ?><?php endif; ?>
              </div>
              <div style="font-size:.72rem;font-weight:600;color:<?= $done ? 'var(--primary)' : 'var(--text-muted)' ?>;text-transform:capitalize"><?= $step ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Items -->
      <div class="card mb-24">
        <div class="card-header">Items Ordered</div>
        <div class="card-body">
          <?php foreach ($items as $item): ?>
          <div style="display:flex;gap:14px;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)">
            <img src="<?= product_img($item['product_image'] ?? '') ?>"
                 style="width:60px;height:60px;object-fit:cover;border-radius:var(--radius);border:1px solid var(--border);flex-shrink:0">
            <div style="flex:1">
              <div style="font-weight:600;font-size:.9rem"><?= e($item['product_name']) ?></div>
              <div style="font-size:.78rem;color:var(--text-muted)">
                <?= currency($item['price']) ?> × <?= $item['quantity'] ?>
              </div>
            </div>
            <div style="font-weight:700;color:var(--primary)"><?= currency($item['subtotal']) ?></div>
          </div>
          <?php endforeach; ?>

          <div style="display:flex;justify-content:flex-end;margin-top:12px;gap:8px;flex-direction:column;align-items:flex-end">
            <div style="font-size:.88rem;color:var(--text-muted)">Subtotal: <?= currency(array_sum(array_column($items,'subtotal'))) ?></div>
            <div style="font-size:1rem;font-weight:700">Total Paid: <?= currency($order['total_amount']) ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right -->
    <div>
      <div class="card mb-16">
        <div class="card-header">Order Info</div>
        <div class="card-body" style="font-size:.86rem">
          <table style="width:100%;border-collapse:collapse">
            <tr><td style="padding:7px 0;color:var(--text-muted);width:45%">Order #</td><td style="font-weight:600;color:var(--primary)"><?= e($order['order_number']) ?></td></tr>
            <tr><td style="padding:7px 0;color:var(--text-muted)">Placed</td><td><?= date('M j, Y', strtotime($order['created_at'])) ?></td></tr>
            <tr><td style="padding:7px 0;color:var(--text-muted)">Status</td><td><?= status_badge($order['status']) ?></td></tr>
            <tr><td style="padding:7px 0;color:var(--text-muted)">Payment</td><td><?= ucfirst(str_replace('_',' ',$order['payment_method'])) ?></td></tr>
            <tr><td style="padding:7px 0;color:var(--text-muted)">Pay Status</td><td><?= status_badge($order['payment_status']) ?></td></tr>
          </table>
        </div>
      </div>

      <div class="card mb-16">
        <div class="card-header">Delivery To</div>
        <div class="card-body" style="font-size:.86rem">
          <div style="font-weight:600;margin-bottom:4px"><?= e($order['shipping_name']) ?></div>
          <div style="color:var(--text-muted)"><i class="fas fa-phone" style="margin-right:4px"></i><?= e($order['shipping_phone']) ?></div>
          <div style="color:var(--text-muted);margin-top:6px"><i class="fas fa-map-marker-alt" style="margin-right:4px"></i><?= e($order['shipping_address']) ?></div>
          <?php if ($order['notes']): ?>
          <div style="margin-top:8px;font-style:italic;color:var(--text-muted)">Note: <?= e($order['notes']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <a href="<?= SITE_URL ?>/customer/orders.php" class="btn btn-outline-dark btn-block">
        <i class="fas fa-arrow-left"></i> Back to Orders
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
