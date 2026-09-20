<?php
require_once __DIR__ . '/../includes/auth.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(SITE_URL . '/customer/index.php');

$st = $db->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=? AND p.status='active'");
$st->execute([$id]);
$product = $st->fetch();
if (!$product) redirect(SITE_URL . '/customer/index.php');

// Reviews — sort
$sort = in_array($_GET['sort'] ?? '', ['highest','lowest']) ? $_GET['sort'] : 'newest';
$sort_sql = $sort === 'highest' ? 'r.rating DESC, r.created_at DESC'
          : ($sort === 'lowest'  ? 'r.rating ASC,  r.created_at DESC'
                                 : 'r.created_at DESC');
$rev_st = $db->prepare("SELECT r.*, u.name AS user_name, u.avatar FROM reviews r JOIN users u ON r.user_id=u.id WHERE r.product_id=? ORDER BY {$sort_sql}");
$rev_st->execute([$id]);
$reviews       = $rev_st->fetchAll();
$total_reviews = count($reviews);
$avg_rating    = $total_reviews ? array_sum(array_column($reviews,'rating')) / $total_reviews : 0;

// Rating breakdown
$rating_counts = [5=>0,4=>0,3=>0,2=>0,1=>0];
foreach ($reviews as $rv) $rating_counts[(int)$rv['rating']]++;

// Current user's existing review (to pre-fill form)
$my_review = null;
if (is_logged_in() && ($_SESSION['user_role'] ?? '') === 'customer') {
    $mr_st = $db->prepare("SELECT * FROM reviews WHERE user_id=? AND product_id=? LIMIT 1");
    $mr_st->execute([$_SESSION['user_id'], $id]);
    $my_review = $mr_st->fetch() ?: null;
}

// Related products
$rel_st = $db->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.category_id=? AND p.id!=? AND p.status='active' LIMIT 4");
$rel_st->execute([$product['category_id'], $id]);
$related = $rel_st->fetchAll();

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    require_customer();
    verify_csrf();
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating >= 1 && $rating <= 5) {
        try {
            $ins = $db->prepare("INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment)");
            $ins->execute([$_SESSION['user_id'], $id, $rating, $comment]);
            flash('review_ok', 'Thank you for your review!', 'success');
        } catch (PDOException $e) {
            flash('review_ok', 'Could not save review.', 'danger');
        }
    }
    redirect(SITE_URL . '/customer/product.php?id=' . $id . '#reviews');
}

$current_price = $product['sale_price'] ? (float)$product['sale_price'] : (float)$product['price'];
$is_sale       = !empty($product['sale_price']) && $product['sale_price'] < $product['price'];
$page_title    = $product['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= SITE_URL ?>/customer/index.php">Home</a>
      <span>/</span>
      <a href="<?= SITE_URL ?>/customer/index.php?category=<?= $product['category_id'] ?>"><?= e($product['category_name'] ?? 'Products') ?></a>
      <span>/</span>
      <span style="color:rgba(255,255,255,.8)"><?= e($product['name']) ?></span>
    </div>
  </div>
</div>

<div class="product-detail">
  <div class="container">

    <!-- Back button -->
    <div style="margin-bottom:20px">
      <button onclick="history.length > 1 ? history.back() : (window.location.href='<?= SITE_URL ?>/customer/index.php')"
              class="btn-back-product">
        <i class="fas fa-arrow-left"></i> Back
      </button>
    </div>

    <div class="product-detail-grid">

      <!-- Image -->
      <div class="product-gallery">
        <img src="<?= product_img($product['image']) ?>" alt="<?= e($product['name']) ?>" id="main-product-img"
             style="border-radius:var(--radius-lg);border:1px solid var(--border)">
      </div>

      <!-- Info -->
      <div class="product-detail-info">
        <?php if ($product['category_name']): ?>
        <div class="product-category"><?= e($product['category_name']) ?></div>
        <?php endif; ?>

        <h1><?= e($product['name']) ?></h1>

        <div style="margin-bottom:12px">
          <?= star_rating($avg_rating, $total_reviews) ?>
        </div>

        <div class="detail-price">
          <span class="price-current"><?= currency($current_price) ?></span>
          <?php if ($is_sale): ?>
          <span class="price-original" style="margin-left:8px"><?= currency($product['price']) ?></span>
          <span class="badge badge-danger" style="margin-left:8px">
            -<?= round((1 - $product['sale_price'] / $product['price']) * 100) ?>% OFF
          </span>
          <?php endif; ?>
        </div>

        <?php if ($product['description']): ?>
        <p style="color:var(--text-muted);line-height:1.8;margin-bottom:24px"><?= nl2br(e($product['description'])) ?></p>
        <?php endif; ?>

        <?php
        $is_rider_view = is_logged_in() && ($_SESSION['user_role'] ?? '') === 'rider';
        ?>
        <?php if ($product['stock'] > 0): ?>
        <div style="margin-bottom:16px">
          <span class="badge badge-success"><i class="fas fa-check"></i> In Stock (<?= $product['stock'] ?> available)</span>
        </div>

        <?php if ($is_rider_view): ?>
        <!-- Rider: browse only, no buying -->
        <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:.85rem;color:#854d0e;display:flex;align-items:center;gap:8px">
          <i class="fas fa-info-circle"></i>
          Browse only — riders cannot add items to cart.
        </div>
        <?php elseif (is_logged_in()): ?>
        <div class="qty-group" style="margin-bottom:20px">
          <button type="button" class="qty-btn" data-action="dec">−</button>
          <input type="number" id="product-qty" value="1" min="1" max="<?= $product['stock'] ?>">
          <button type="button" class="qty-btn" data-action="inc">+</button>
        </div>
        <div class="detail-actions">
          <button class="btn btn-primary btn-lg" data-add-cart="<?= $product['id'] ?>">
            <i class="fas fa-cart-plus"></i> Add to Cart
          </button>
          <a href="<?= SITE_URL ?>/customer/cart.php" class="btn btn-outline-dark btn-lg">
            <i class="fas fa-shopping-cart"></i> View Cart
          </a>
        </div>
        <?php else: ?>
        <button onclick="document.getElementById('open-login-modal')?.click() || (window.location.href='<?= SITE_URL ?>/customer/index.php?modal=login')"
                class="btn btn-primary btn-lg">
          <i class="fas fa-sign-in-alt"></i> Login to Purchase
        </button>
        <?php endif; ?>
        <?php else: ?>
        <div class="badge badge-danger" style="font-size:.9rem;padding:8px 16px">Out of Stock</div>
        <?php endif; ?>

        <div class="product-meta">
          <span><strong>Category:</strong> <?= e($product['category_name'] ?? 'N/A') ?></span>
          <span><strong>Stock:</strong> <?= $product['stock'] ?> units</span>
          <span><strong>Added:</strong> <?= date('F j, Y', strtotime($product['created_at'])) ?></span>
        </div>
      </div>
    </div>

    <!-- ── Reviews ─────────────────────────────────────── -->
    <section id="reviews" class="rv-section">

      <!-- Flash -->
      <?php show_flash('review_ok'); ?>

      <!-- Section header -->
      <div class="rv-head">
        <h2 class="rv-title">
          Customer Reviews
          <span class="rv-count-badge"><?= $total_reviews ?></span>
        </h2>
        <?php if ($total_reviews > 1): ?>
        <form class="rv-sort-form" method="GET">
          <input type="hidden" name="id" value="<?= $id ?>">
          <select name="sort" class="rv-sort-select" onchange="this.form.submit()">
            <option value="newest"  <?= $sort==='newest'  ? 'selected':'' ?>>Newest First</option>
            <option value="highest" <?= $sort==='highest' ? 'selected':'' ?>>Highest Rated</option>
            <option value="lowest"  <?= $sort==='lowest'  ? 'selected':'' ?>>Lowest Rated</option>
          </select>
        </form>
        <?php endif; ?>
      </div>

      <!-- Body: summary + write form side by side -->
      <div class="rv-body">

        <!-- Rating summary -->
        <?php if ($total_reviews > 0): ?>
        <div class="rv-summary">
          <div class="rv-avg-big"><?= number_format($avg_rating, 1) ?></div>
          <div class="rv-avg-stars">
            <?php for ($s=1;$s<=5;$s++): ?>
            <i class="fas fa-star<?= $avg_rating < $s ? ($avg_rating >= $s-0.5 ? '-half-alt' : '') : '' ?>"
               <?= $avg_rating < $s-0.5 ? 'class="far fa-star"' : '' ?>></i>
            <?php endfor; ?>
          </div>
          <div class="rv-avg-label">Based on <?= $total_reviews ?> review<?= $total_reviews!==1?'s':'' ?></div>
          <div class="rv-bars">
            <?php for ($s=5;$s>=1;$s--): ?>
            <?php $pct = $total_reviews ? round($rating_counts[$s]/$total_reviews*100) : 0; ?>
            <div class="rv-bar-row">
              <span class="rv-bar-label"><?= $s ?> <i class="fas fa-star"></i></span>
              <div class="rv-bar-track"><div class="rv-bar-fill" style="width:<?= $pct ?>%"></div></div>
              <span class="rv-bar-count"><?= $rating_counts[$s] ?></span>
            </div>
            <?php endfor; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Write / Edit review -->
        <?php if (is_logged_in() && ($_SESSION['user_role'] ?? '') === 'customer'): ?>
        <div class="rv-write-wrap">
          <div class="rv-write-head">
            <i class="fas fa-<?= $my_review ? 'edit' : 'pen-nib' ?>"></i>
            <?= $my_review ? 'Edit Your Review' : 'Write a Review' ?>
          </div>
          <form class="rv-write-form" method="POST" id="review-form">
            <?= csrf_field() ?>
            <input type="hidden" name="submit_review" value="1">

            <!-- Star picker -->
            <div class="rv-star-pick">
              <div class="rv-star-label">
                Your Rating
                <span class="rv-star-hint" id="rv-star-hint"><?= $my_review ? ['','Terrible','Poor','Average','Good','Excellent'][$my_review['rating']] : 'Click to rate' ?></span>
              </div>
              <div class="rv-stars-input" id="rv-stars-input">
                <?php for ($s=1;$s<=5;$s++): ?>
                <button type="button" class="rv-star-btn <?= ($my_review && $my_review['rating']>=$s)?'active':'' ?>"
                        data-val="<?= $s ?>" aria-label="<?= $s ?> star<?= $s>1?'s':'' ?>">
                  <i class="fas fa-star"></i>
                </button>
                <?php endfor; ?>
              </div>
              <input type="hidden" name="rating" id="rv-rating-input" value="<?= (int)($my_review['rating'] ?? 0) ?>">
            </div>

            <!-- Textarea -->
            <div class="rv-textarea-wrap">
              <textarea name="comment" id="rv-comment" class="rv-textarea" rows="4"
                        placeholder="Share your experience with this product… (optional)"
                        maxlength="1000"><?= e($my_review['comment'] ?? '') ?></textarea>
              <div class="rv-char-count"><span id="rv-char-num"><?= strlen($my_review['comment'] ?? '') ?></span>/1000</div>
            </div>

            <div class="rv-write-footer">
              <?php if ($my_review): ?>
              <span class="rv-edit-note"><i class="fas fa-info-circle"></i> Submitting will update your existing review.</span>
              <?php else: ?>
              <span></span>
              <?php endif; ?>
              <button type="submit" class="rv-submit-btn">
                <i class="fas fa-<?= $my_review ? 'sync-alt' : 'paper-plane' ?>"></i>
                <?= $my_review ? 'Update Review' : 'Submit Review' ?>
              </button>
            </div>
          </form>
        </div>

        <?php elseif (!is_logged_in()): ?>
        <div class="rv-login-prompt">
          <div class="rv-login-icon"><i class="fas fa-star"></i></div>
          <h4>Share Your Experience</h4>
          <p>Sign in to rate and review this product</p>
          <button onclick="document.getElementById('open-login-modal')?.click() || (window.location.href='<?= SITE_URL ?>/customer/index.php?modal=login')"
                  class="rv-login-btn">
            <i class="fas fa-sign-in-alt"></i> Sign In to Review
          </button>
        </div>
        <?php endif; ?>

      </div><!-- /.rv-body -->

      <!-- Review list -->
      <?php if ($reviews): ?>
      <div class="rv-list" id="rv-list">
        <?php
        $avatar_colors = ['#2563eb','#7c3aed','#059669','#d97706','#dc2626','#0891b2','#be185d','#0f766e'];
        foreach ($reviews as $idx => $r):
          $col      = $avatar_colors[abs(crc32($r['user_name'])) % count($avatar_colors)];
          $parts    = array_filter(explode(' ', $r['user_name']));
          $initials = implode('', array_map(fn($w)=>strtoupper($w[0]), array_slice($parts,0,2)));
          $is_mine  = is_logged_in() && $r['user_id'] === $_SESSION['user_id'];
        ?>
        <div class="rv-card <?= $is_mine ? 'rv-card--mine' : '' ?> <?= $idx >= 5 ? 'rv-card--hidden' : '' ?>">
          <div class="rv-card-top">
            <div class="rv-card-user">
              <?php if (!empty($r['avatar']) && $r['avatar'] !== 'default.png'): ?>
              <img src="<?= avatar_url($r['avatar']) ?>" alt="" class="rv-avatar-img">
              <?php else: ?>
              <div class="rv-avatar-init" style="background:<?= $col ?>"><?= $initials ?></div>
              <?php endif; ?>
              <div class="rv-user-info">
                <div class="rv-user-name">
                  <?= e($r['user_name']) ?>
                  <?php if ($is_mine): ?><span class="rv-you-badge">You</span><?php endif; ?>
                </div>
                <div class="rv-user-date"><?= time_ago($r['created_at']) ?></div>
              </div>
            </div>
            <div class="rv-card-stars">
              <?php for($s=1;$s<=5;$s++): ?>
              <i class="<?= $r['rating']>=$s?'fas':'far' ?> fa-star"></i>
              <?php endfor; ?>
              <span class="rv-card-rating-num"><?= number_format($r['rating'],1) ?></span>
            </div>
          </div>
          <?php if ($r['comment']): ?>
          <p class="rv-card-comment"><?= nl2br(e($r['comment'])) ?></p>
          <?php else: ?>
          <p class="rv-card-comment rv-no-comment"><i class="fas fa-minus"></i> No written comment</p>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($total_reviews > 5): ?>
      <div class="rv-load-more" id="rv-load-more">
        <button class="rv-load-btn" id="rv-load-btn">
          <i class="fas fa-chevron-down"></i>
          Show all <?= $total_reviews ?> reviews
        </button>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="rv-empty">
        <div class="rv-empty-icon"><i class="fas fa-star-half-alt"></i></div>
        <h3>No reviews yet</h3>
        <p>Be the first to share your experience with this product!</p>
      </div>
      <?php endif; ?>

    </section>

    <!-- Related -->
    <?php if ($related): ?>
    <div style="margin-top:56px">
      <div class="section-header">
        <h2 class="section-title">Related <span>Products</span></h2>
      </div>
      <div class="products-grid">
        <?php foreach ($related as $p): ?>
        <?php include __DIR__ . '/../includes/product_card.php'; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
// ── Add to cart ───────────────────────────────────────────────
document.querySelectorAll('[data-add-cart]').forEach(btn => {
  btn.addEventListener('click', function() {
    const qty = document.getElementById('product-qty')?.value || 1;
    window.cartRequest && window.cartRequest('add', this.dataset.addCart, qty);
  });
});

// ── Review star picker ────────────────────────────────────────
(function () {
  const starsWrap  = document.getElementById('rv-stars-input');
  const ratingInp  = document.getElementById('rv-rating-input');
  const hintEl     = document.getElementById('rv-star-hint');
  const hints      = ['','Terrible','Poor','Average','Good','Excellent'];
  if (!starsWrap) return;

  const btns = Array.from(starsWrap.querySelectorAll('.rv-star-btn'));
  let current = parseInt(ratingInp.value) || 0;

  function paint(val, cls) {
    btns.forEach((b, i) => {
      b.classList.toggle('active',   cls === 'active'  && i < val);
      b.classList.toggle('hovered',  cls === 'hovered' && i < val);
    });
  }

  paint(current, 'active'); // init

  btns.forEach((btn, idx) => {
    btn.addEventListener('mouseenter', () => {
      paint(idx + 1, 'hovered');
      if (hintEl) hintEl.textContent = hints[idx + 1];
    });
    btn.addEventListener('mouseleave', () => {
      paint(0, 'hovered');
      if (hintEl) hintEl.textContent = current ? hints[current] : 'Click to rate';
    });
    btn.addEventListener('click', () => {
      current = idx + 1;
      ratingInp.value = current;
      paint(current, 'active');
      if (hintEl) { hintEl.textContent = hints[current]; hintEl.style.color = '#f59e0b'; }
    });
  });

  // Char counter
  const ta      = document.getElementById('rv-comment');
  const charNum = document.getElementById('rv-char-num');
  if (ta && charNum) ta.addEventListener('input', () => { charNum.textContent = ta.value.length; });

  // Validate before submit
  const form = document.getElementById('review-form');
  if (form) {
    form.addEventListener('submit', e => {
      if (!ratingInp.value) {
        e.preventDefault();
        starsWrap.classList.remove('rv-shake');
        void starsWrap.offsetWidth;
        starsWrap.classList.add('rv-shake');
        if (hintEl) { hintEl.textContent = 'Please select a rating!'; hintEl.style.color = '#dc2626'; }
      }
    });
  }

  // Load more reviews
  const loadBtn  = document.getElementById('rv-load-btn');
  const loadWrap = document.getElementById('rv-load-more');
  if (loadBtn) {
    loadBtn.addEventListener('click', () => {
      document.querySelectorAll('.rv-card--hidden').forEach(c => {
        c.classList.remove('rv-card--hidden');
        c.style.animation = 'rv-card-in .35s ease both';
      });
      loadWrap.style.display = 'none';
    });
  }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
