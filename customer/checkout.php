<?php
require_once __DIR__ . '/../includes/auth.php';
require_customer();
$page_title = 'Checkout';
$db = getDB();

// Fetch cart items
$st = $db->prepare("SELECT c.*, p.name, p.image, p.stock, COALESCE(p.sale_price,p.price) AS unit_price
                    FROM cart c JOIN products p ON c.product_id=p.id
                    WHERE c.user_id=?");
$st->execute([$_SESSION['user_id']]);
$items = $st->fetchAll();

if (!$items) redirect(SITE_URL . '/customer/cart.php');

$subtotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['quantity'], $items));
$shipping = $subtotal >= 500 ? 0 : 99;
$total    = $subtotal + $shipping;

// Fetch user info
$user = $db->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$_SESSION['user_id']]);
$me = $user->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $ship_name    = trim($_POST['ship_name']   ?? '');
    $ship_phone   = trim($_POST['ship_phone']  ?? '');
    $ship_address = trim($_POST['ship_address'] ?? '');
    $payment      = $_POST['payment_method']   ?? 'cod';
    $notes        = trim($_POST['notes']        ?? '');

    if (!$ship_name)    $errors[] = 'Full name is required.';
    if (!$ship_phone)   $errors[] = 'Phone number is required.';
    if (!$ship_address) $errors[] = 'Delivery address is required.';
    if (!in_array($payment, ['cod','gcash','bank_transfer'])) $errors[] = 'Invalid payment method.';

    if (!$errors) {
        // Re-validate stock
        foreach ($items as $item) {
            if ($item['stock'] < $item['quantity']) {
                $errors[] = '"' . $item['name'] . '" only has ' . $item['stock'] . ' units available.';
            }
        }
    }

    if (!$errors) {
        $db->beginTransaction();
        try {
            $order_num = generate_order_number();

            $ins = $db->prepare("INSERT INTO orders (user_id,order_number,total_amount,status,shipping_name,shipping_phone,shipping_address,payment_method,payment_status,notes)
                                  VALUES (?,?,?,'pending',?,?,?,?,?,?)");
            $pay_status = $payment === 'cod' ? 'pending' : 'pending';
            $ins->execute([$_SESSION['user_id'], $order_num, $total, $ship_name, $ship_phone, $ship_address, $payment, $pay_status, $notes]);
            $order_id = $db->lastInsertId();

            $item_ins = $db->prepare("INSERT INTO order_items (order_id,product_id,product_name,product_image,price,quantity,subtotal) VALUES (?,?,?,?,?,?,?)");
            $stock_upd = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

            foreach ($items as $item) {
                $item_ins->execute([
                    $order_id, $item['product_id'], $item['name'], $item['image'],
                    $item['unit_price'], $item['quantity'],
                    $item['unit_price'] * $item['quantity']
                ]);
                $stock_upd->execute([$item['quantity'], $item['product_id'], $item['quantity']]);

                // Low stock / out-of-stock alert after deduction
                $remaining = $item['stock'] - $item['quantity'];
                if ($remaining === 0) {
                    notify_admins('low_stock', 'Out of Stock', '"' . $item['name'] . '" is now out of stock.', SITE_URL . '/admin/products.php');
                } elseif ($remaining <= 5) {
                    notify_admins('low_stock', 'Low Stock Alert', '"' . $item['name'] . '" has only ' . $remaining . ' units left.', SITE_URL . '/admin/products.php');
                }
            }

            // Clear cart
            $db->prepare("DELETE FROM cart WHERE user_id=?")->execute([$_SESSION['user_id']]);
            $db->commit();

            // Notify all admins of new order
            notify_admins(
                'new_order',
                'New Order Placed',
                'Order ' . $order_num . ' by ' . $_SESSION['user_name'] . ' — ' . currency($total),
                SITE_URL . '/admin/order_detail.php?id=' . $order_id
            );

            redirect(SITE_URL . '/customer/order_success.php?order=' . urlencode($order_num));
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'Order could not be placed. Please try again.';
            error_log($e->getMessage());
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1><i class="fas fa-lock" style="color:var(--primary)"></i> Secure Checkout</h1>
    <p>Complete your order below</p>
  </div>
</div>

<div class="container">
  <?php foreach ($errors as $err): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="checkout-layout">
    <!-- Checkout form -->
    <div>
      <form method="POST">
        <?= csrf_field() ?>

        <div class="card mb-24">
          <div class="card-header"><i class="fas fa-map-marker-alt"></i> Delivery Information</div>
          <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
              <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Full Name *</label>
                <input type="text" name="ship_name" class="form-control"
                       value="<?= e($_POST['ship_name'] ?? $me['name']) ?>" required>
              </div>
              <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Phone Number *</label>
                <input type="text" name="ship_phone" class="form-control"
                       value="<?= e($_POST['ship_phone'] ?? $me['phone'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-group mt-16">
              <label class="form-label">Delivery Address *</label>
              <textarea name="ship_address" class="form-control" rows="3" required><?= e($_POST['ship_address'] ?? $me['address'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Order Notes (optional)</label>
              <input type="text" name="notes" class="form-control" placeholder="E.g., leave at gate, call before delivery"
                     value="<?= e($_POST['notes'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="card mb-24">
          <div class="card-header"><i class="fas fa-credit-card"></i> Payment Method</div>
          <div class="card-body">
            <?php
            $methods = ['cod'=>['Cash on Delivery','fas fa-money-bill-wave'],'gcash'=>['GCash','fas fa-mobile-alt'],'bank_transfer'=>['Bank Transfer','fas fa-university']];
            foreach ($methods as $val => [$label, $icon]):
            $sel = ($_POST['payment_method'] ?? 'cod') === $val;
            ?>
            <label style="display:flex;align-items:center;gap:14px;padding:14px;border:2px solid <?= $sel ? 'var(--primary)' : 'var(--border)' ?>;border-radius:var(--radius-md);cursor:pointer;margin-bottom:10px;transition:border-color .2s">
              <input type="radio" name="payment_method" value="<?= $val ?>" <?= $sel ? 'checked' : '' ?> style="accent-color:var(--primary);width:16px;height:16px">
              <i class="<?= $icon ?>" style="font-size:1.2rem;color:var(--primary);width:24px;text-align:center"></i>
              <div>
                <div style="font-weight:600;font-size:.9rem"><?= $label ?></div>
                <?php if ($val === 'cod'): ?>
                <div style="font-size:.75rem;color:var(--text-muted)">Pay when your order arrives</div>
                <?php elseif ($val === 'gcash'): ?>
                <div style="font-size:.75rem;color:var(--text-muted)">Send to 09XX-XXX-XXXX, then attach screenshot</div>
                <?php else: ?>
                <div style="font-size:.75rem;color:var(--text-muted)">BDO: 1234-5678-9012 (Jemelo Inc.)</div>
                <?php endif; ?>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
          <i class="fas fa-check-circle"></i> Place Order — <?= currency($total) ?>
        </button>
      </form>
    </div>

    <!-- Order Summary -->
    <div>
      <div class="card order-summary">
        <div class="card-header">Your Order (<?= count($items) ?>)</div>
        <div class="card-body">
          <?php foreach ($items as $item): ?>
          <div style="display:flex;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
            <img src="<?= product_img($item['image']) ?>" style="width:52px;height:52px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border)">
            <div style="flex:1;min-width:0">
              <div style="font-size:.84rem;font-weight:600;color:var(--dark)"><?= e($item['name']) ?></div>
              <div style="font-size:.75rem;color:var(--text-muted)">Qty: <?= $item['quantity'] ?></div>
            </div>
            <div style="font-size:.88rem;font-weight:700;color:var(--primary)"><?= currency($item['unit_price'] * $item['quantity']) ?></div>
          </div>
          <?php endforeach; ?>

          <div class="summary-row" style="margin-top:12px">
            <span>Subtotal</span><span><?= currency($subtotal) ?></span>
          </div>
          <div class="summary-row">
            <span>Shipping</span>
            <span><?= $shipping === 0 ? '<span class="text-success">Free</span>' : currency($shipping) ?></span>
          </div>
          <div class="summary-row summary-total">
            <span>Total</span><span style="font-size:1.15rem;color:var(--primary)"><?= currency($total) ?></span>
          </div>
        </div>
      </div>

      <div class="card mt-16" style="padding:14px">
        <div style="display:flex;gap:8px;font-size:.78rem;color:var(--text-muted)">
          <i class="fas fa-shield-alt" style="color:var(--success);margin-top:1px"></i>
          <span>Your information is secured with 256-bit SSL encryption.</span>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
