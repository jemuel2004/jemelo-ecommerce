<?php
require_once __DIR__ . '/../includes/auth.php';
require_customer();
$page_title = 'Shopping Cart';

$db = getDB();

// Handle direct form updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantities'] ?? [] as $pid => $qty) {
            $pid = (int)$pid;
            $qty = max(1, (int)$qty);
            $upd = $db->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=?");
            $upd->execute([$qty, $_SESSION['user_id'], $pid]);
        }
    }
    if (isset($_POST['clear_cart'])) {
        $db->prepare("DELETE FROM cart WHERE user_id=?")->execute([$_SESSION['user_id']]);
    }
    redirect(SITE_URL . '/customer/cart.php');
}

// Fetch cart items with product details
$st = $db->prepare("SELECT c.*, p.name, p.image, p.stock,
                    COALESCE(p.sale_price, p.price) AS unit_price,
                    p.status
                    FROM cart c
                    JOIN products p ON c.product_id = p.id
                    WHERE c.user_id = ?
                    ORDER BY c.created_at DESC");
$st->execute([$_SESSION['user_id']]);
$items = $st->fetchAll();

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['unit_price'] * $item['quantity'];
}
$shipping = $subtotal >= 500 ? 0 : 99;
$total    = $subtotal + $shipping;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1><i class="fas fa-shopping-cart" style="color:var(--primary)"></i> Shopping Cart</h1>
    <p><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?> in your cart</p>
  </div>
</div>

<div class="container">
  <?php show_flash('cart'); ?>

  <?php if ($items): ?>
  <div class="cart-layout">
    <!-- Cart items -->
    <div>
      <div class="card">
        <div class="card-header">
          <span>Cart Items</span>
          <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <button type="submit" name="clear_cart" class="btn btn-sm btn-outline" style="color:var(--danger);border-color:var(--danger)"
                    data-confirm="Remove all items from cart?">
              <i class="fas fa-trash"></i> Clear Cart
            </button>
          </form>
        </div>
        <div class="card-body">
          <form method="POST">
            <?= csrf_field() ?>
            <?php foreach ($items as $item): ?>
            <div class="cart-item" data-item-subtotal="<?= $item['unit_price'] * $item['quantity'] ?>">
              <a href="<?= SITE_URL ?>/customer/product.php?id=<?= $item['product_id'] ?>">
                <img src="<?= product_img($item['image']) ?>" alt="<?= e($item['name']) ?>" class="cart-item-img">
              </a>
              <div class="cart-item-info">
                <a href="<?= SITE_URL ?>/customer/product.php?id=<?= $item['product_id'] ?>" class="cart-item-name">
                  <?= e($item['name']) ?>
                </a>
                <div class="cart-item-price"><?= currency($item['unit_price']) ?> each</div>
                <?php if ($item['stock'] < $item['quantity']): ?>
                <div style="font-size:.75rem;color:var(--danger)"><i class="fas fa-exclamation-triangle"></i> Only <?= $item['stock'] ?> left</div>
                <?php endif; ?>
              </div>

              <!-- Qty -->
              <div class="cart-item-qty">
                <button type="button" class="qty-btn" data-action="dec"
                        onclick="adjustQty(this, -1)">−</button>
                <input type="number" name="quantities[<?= $item['product_id'] ?>]"
                       class="cart-qty-input"
                       data-product-id="<?= $item['product_id'] ?>"
                       value="<?= $item['quantity'] ?>"
                       min="1" max="<?= $item['stock'] ?>">
                <button type="button" class="qty-btn" data-action="inc"
                        onclick="adjustQty(this, 1)">+</button>
              </div>

              <!-- Subtotal -->
              <div class="cart-item-total" data-subtotal="<?= $item['product_id'] ?>">
                <?= currency($item['unit_price'] * $item['quantity']) ?>
              </div>

              <!-- Remove -->
              <button type="button" class="cart-remove" data-remove-cart="<?= $item['product_id'] ?>" title="Remove">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <?php endforeach; ?>

            <div style="display:flex;justify-content:flex-end;margin-top:16px">
              <button type="submit" name="update_cart" class="btn btn-outline btn-sm">
                <i class="fas fa-sync"></i> Update Cart
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Order Summary -->
    <div>
      <div class="card order-summary">
        <div class="card-header">Order Summary</div>
        <div class="card-body">
          <div class="summary-row">
            <span>Subtotal</span>
            <span id="cart-total"><?= currency($subtotal) ?></span>
          </div>
          <div class="summary-row">
            <span>Shipping</span>
            <span><?= $shipping === 0 ? '<span class="text-success">Free</span>' : currency($shipping) ?></span>
          </div>
          <?php if ($shipping > 0): ?>
          <div style="font-size:.75rem;color:var(--text-muted);padding:6px 0;border-bottom:1px solid var(--border)">
            <i class="fas fa-info-circle"></i> Free shipping on orders ₱500+
          </div>
          <?php endif; ?>
          <div class="summary-row summary-total" style="margin-top:8px">
            <span>Total</span>
            <span style="font-size:1.2rem"><?= currency($total) ?></span>
          </div>
          <a href="<?= SITE_URL ?>/customer/checkout.php" class="btn btn-primary btn-block btn-lg mt-16">
            <i class="fas fa-lock"></i> Proceed to Checkout
          </a>
          <a href="<?= SITE_URL ?>/customer/index.php" class="btn btn-outline-dark btn-block mt-8">
            <i class="fas fa-arrow-left"></i> Continue Shopping
          </a>
        </div>
      </div>

      <div class="card mt-16" style="padding:16px">
        <div style="display:flex;align-items:center;gap:10px;font-size:.82rem;color:var(--text-muted)">
          <i class="fas fa-shield-alt" style="color:var(--primary);font-size:1.1rem"></i>
          <span>Secure checkout. Your data is protected.</span>
        </div>
      </div>
    </div>
  </div>

  <?php else: ?>
  <div class="empty-state card mt-24">
    <div class="card-body">
      <div class="empty-icon"><i class="fas fa-shopping-cart"></i></div>
      <h3>Your cart is empty</h3>
      <p>Looks like you haven't added anything yet.</p>
      <a href="<?= SITE_URL ?>/customer/index.php" class="btn btn-primary mt-16">
        <i class="fas fa-shopping-bag"></i> Start Shopping
      </a>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function adjustQty(btn, delta) {
  const input = btn.parentElement.querySelector('input');
  if (!input) return;
  let val = parseInt(input.value) + delta;
  val = Math.max(parseInt(input.min || 1), Math.min(parseInt(input.max || 999), val));
  input.value = val;
  // Update displayed subtotal
  const price = parseFloat(btn.closest('.cart-item')?.querySelector('.cart-item-price')?.textContent?.replace(/[^0-9.]/g, '') || 0);
  const subEl = btn.closest('.cart-item')?.querySelector('[data-subtotal]');
  if (subEl && price) {
    const subtotal = price * val;
    subEl.textContent = '₱' + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
