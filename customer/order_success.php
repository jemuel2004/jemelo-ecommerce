<?php
require_once __DIR__ . '/../includes/auth.php';
require_customer();
$page_title = 'Order Placed!';
$db = getDB();

$order_num = trim($_GET['order'] ?? '');
if (!$order_num) redirect(SITE_URL . '/customer/orders.php');

$st = $db->prepare("SELECT * FROM orders WHERE order_number=? AND user_id=?");
$st->execute([$order_num, $_SESSION['user_id']]);
$order = $st->fetch();
if (!$order) redirect(SITE_URL . '/customer/orders.php');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
  <div class="success-box card">
    <div class="card-body" style="text-align:center;padding:60px 40px">
      <div class="success-icon">
        <i class="fas fa-check"></i>
      </div>
      <h2 style="margin-bottom:8px">Order Placed Successfully!</h2>
      <p>Thank you for your purchase. We'll process your order right away.</p>

      <div style="background:var(--bg-alt);border-radius:var(--radius-md);padding:20px;margin:28px 0;text-align:left">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:.88rem">
          <div>
            <div style="color:var(--text-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px">Order Number</div>
            <div style="font-weight:700;color:var(--primary)"><?= e($order['order_number']) ?></div>
          </div>
          <div>
            <div style="color:var(--text-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px">Total Amount</div>
            <div style="font-weight:700"><?= currency($order['total_amount']) ?></div>
          </div>
          <div>
            <div style="color:var(--text-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px">Payment Method</div>
            <div style="font-weight:600"><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></div>
          </div>
          <div>
            <div style="color:var(--text-muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px">Status</div>
            <div><?= status_badge($order['status']) ?></div>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a href="<?= SITE_URL ?>/customer/order_detail.php?id=<?= $order['id'] ?>" class="btn btn-primary">
          <i class="fas fa-eye"></i> View Order Details
        </a>
        <a href="<?= SITE_URL ?>/customer/index.php" class="btn btn-outline-dark">
          <i class="fas fa-shopping-bag"></i> Continue Shopping
        </a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
