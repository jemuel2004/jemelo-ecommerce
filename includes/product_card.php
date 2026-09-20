<?php
// $p = product row array (must be set by including scope)
$current_price = $p['sale_price'] ? (float)$p['sale_price'] : (float)$p['price'];
$is_sale       = !empty($p['sale_price']) && $p['sale_price'] < $p['price'];
$discount_pct  = $is_sale ? round((1 - $p['sale_price'] / $p['price']) * 100) : 0;
?>
<div class="product-card">
  <a href="<?= SITE_URL ?>/customer/product.php?id=<?= $p['id'] ?>">
    <div class="product-img-wrap">
      <img src="<?= product_img($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
      <?php if ($is_sale): ?>
      <span class="product-badge">-<?= $discount_pct ?>%</span>
      <?php elseif ($p['featured']): ?>
      <span class="product-badge featured">Featured</span>
      <?php endif; ?>
      <?php if ($p['stock'] <= 0): ?>
      <div style="position:absolute;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center">
        <span style="color:#fff;font-weight:700;font-size:.85rem;background:rgba(0,0,0,.5);padding:6px 14px;border-radius:4px">Out of Stock</span>
      </div>
      <?php endif; ?>
    </div>
  </a>

  <div class="product-actions">
    <?php if (is_logged_in() && $p['stock'] > 0 && ($_SESSION['user_role'] ?? '') !== 'rider'): ?>
    <button class="product-action-btn" data-add-cart="<?= $p['id'] ?>" title="Add to cart">
      <i class="fas fa-shopping-cart"></i>
    </button>
    <?php endif; ?>
    <a href="<?= SITE_URL ?>/customer/product.php?id=<?= $p['id'] ?>" class="product-action-btn" title="View product">
      <i class="fas fa-eye"></i>
    </a>
  </div>

  <div class="product-info">
    <?php if (!empty($p['category_name'])): ?>
    <div class="product-category"><?= e($p['category_name']) ?></div>
    <?php endif; ?>
    <a href="<?= SITE_URL ?>/customer/product.php?id=<?= $p['id'] ?>" class="product-name" style="display:block;text-decoration:none;color:inherit">
      <?= e($p['name']) ?>
    </a>
    <div class="product-price">
      <span class="price-current"><?= currency($current_price) ?></span>
      <?php if ($is_sale): ?>
      <span class="price-original"><?= currency($p['price']) ?></span>
      <?php endif; ?>
    </div>
    <?php
    $is_rider_card = is_logged_in() && ($_SESSION['user_role'] ?? '') === 'rider';
    if ($p['stock'] > 0): ?>
    <?php if ($is_rider_card): ?>
    <a href="<?= SITE_URL ?>/customer/product.php?id=<?= $p['id'] ?>" class="btn btn-outline">
      <i class="fas fa-eye"></i> View Product
    </a>
    <?php elseif (is_logged_in()): ?>
    <button class="btn btn-primary" data-add-cart="<?= $p['id'] ?>">
      <i class="fas fa-cart-plus"></i> Add to Cart
    </button>
    <?php else: ?>
    <a href="<?= SITE_URL ?>/login.php" class="btn btn-outline">
      <i class="fas fa-sign-in-alt"></i> Login to Buy
    </a>
    <?php endif; ?>
    <?php else: ?>
    <button class="btn btn-outline" disabled>Out of Stock</button>
    <?php endif; ?>
  </div>
</div>
