<?php
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Shop';
$db = getDB();

// ── Filters ──────────────────────────────────────────────────
$q          = trim($_GET['q'] ?? '');
$category   = (int)($_GET['category'] ?? 0);
$sort       = $_GET['sort'] ?? 'newest';
$min_price  = (float)($_GET['min_price'] ?? 0);
$max_price  = (float)($_GET['max_price'] ?? 99999);
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 12;

// ── Count query ───────────────────────────────────────────────
$where = ["p.status='active'"];
$params = [];

if ($q) {
    $qSafe    = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where[]  = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = $qSafe;
    $params[] = $qSafe;
}
if ($category) {
    $where[]  = "p.category_id = ?";
    $params[] = $category;
}
if ($min_price > 0) { $where[] = "COALESCE(p.sale_price, p.price) >= ?"; $params[] = $min_price; }
if ($max_price < 99999) { $where[] = "COALESCE(p.sale_price, p.price) <= ?"; $params[] = $max_price; }

$where_sql = 'WHERE ' . implode(' AND ', $where);

$count_sql = "SELECT COUNT(*) FROM products p $where_sql";
$count_st  = $db->prepare($count_sql);
$count_st->execute($params);
$total = (int)$count_st->fetchColumn();

$pag = paginate($total, $per_page, $page);

$order_map = [
    'newest'     => 'p.created_at DESC',
    'oldest'     => 'p.created_at ASC',
    'price_asc'  => 'COALESCE(p.sale_price,p.price) ASC',
    'price_desc' => 'COALESCE(p.sale_price,p.price) DESC',
    'name_asc'   => 'p.name ASC',
];
$order_by = $order_map[$sort] ?? 'p.created_at DESC';

$sql = "SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $where_sql
        ORDER BY p.featured DESC, $order_by
        LIMIT ? OFFSET ?";
$st = $db->prepare($sql);
$st->execute(array_merge($params, [$pag['per_page'], $pag['offset']]));
$products = $st->fetchAll();

// Featured products (for hero only on first page, no search)
$featured = [];
if (!$q && !$category && $page === 1) {
    $fs = $db->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.status='active' AND p.featured=1 LIMIT 4");
    $featured = $fs->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$q && !$category && $page === 1): ?>
<!-- ── Hero Carousel ──────────────────────────────────────── -->
<section class="hero-carousel" id="heroCarousel" aria-label="Advertisement Banner">

  <div class="hc-track">

    <!-- Slide 1 — Shop Smarter -->
    <div class="hc-slide" style="background:linear-gradient(135deg,#0d1f42 0%,#1a3a6b 60%,#1e4080 100%)">
      <div class="hc-deco hc-deco--1"></div>
      <div class="hc-deco hc-deco--2"></div>
      <div class="container hc-inner">
        <div class="hc-content">
          <span class="hc-badge" style="background:rgba(37,99,235,.25);color:#93c5fd"><i class="fas fa-fire-alt"></i> Best Deals</span>
          <h1 class="hc-title">Shop Smarter,<br>Live <em>Better</em></h1>
          <p class="hc-desc">Discover thousands of quality products at unbeatable prices. Fast delivery, easy returns guaranteed.</p>
          <div class="hc-btns">
            <a href="#products" class="btn btn-primary btn-lg"><i class="fas fa-shopping-bag"></i> Shop Now</a>
            <a href="#featured" class="hc-ghost-btn"><i class="fas fa-star"></i> Featured</a>
          </div>
        </div>
        <div class="hc-visual">
          <div class="hc-visual-ring" style="border-color:rgba(37,99,235,.4)">
            <div class="hc-visual-icon" style="background:linear-gradient(135deg,#1d4ed8,#3b82f6)">
              <i class="fas fa-shopping-bag"></i>
            </div>
          </div>
          <div class="hc-visual-stat hc-stat--tl">1000+<span>Products</span></div>
          <div class="hc-visual-stat hc-stat--br">Free<span>Returns</span></div>
        </div>
      </div>
    </div>

    <!-- Slide 2 — Summer Sale -->
    <div class="hc-slide" style="background:linear-gradient(135deg,#7c1a1a 0%,#b91c1c 50%,#ea580c 100%)">
      <div class="hc-deco hc-deco--1" style="background:rgba(251,146,60,.15)"></div>
      <div class="hc-deco hc-deco--2" style="background:rgba(239,68,68,.12)"></div>
      <div class="container hc-inner">
        <div class="hc-content">
          <span class="hc-badge" style="background:rgba(251,146,60,.3);color:#fed7aa"><i class="fas fa-tags"></i> Limited Time</span>
          <h1 class="hc-title">Summer Sale<br><em>Up to 50% Off</em></h1>
          <p class="hc-desc">Massive discounts on electronics, fashion, home essentials and more. Don't miss out — sale ends soon!</p>
          <div class="hc-btns">
            <a href="?sort=price_asc" class="btn btn-lg" style="background:#ea580c;color:#fff;border-color:#ea580c"><i class="fas fa-bolt"></i> Grab Deals</a>
            <a href="#products" class="hc-ghost-btn"><i class="fas fa-eye"></i> Browse All</a>
          </div>
        </div>
        <div class="hc-visual">
          <div class="hc-visual-ring" style="border-color:rgba(251,146,60,.4)">
            <div class="hc-visual-icon" style="background:linear-gradient(135deg,#c2410c,#f97316)">
              <i class="fas fa-percentage"></i>
            </div>
          </div>
          <div class="hc-visual-stat hc-stat--tl" style="background:rgba(234,88,12,.35)">50%<span>Off</span></div>
          <div class="hc-visual-stat hc-stat--br" style="background:rgba(234,88,12,.35)">Today<span>Only</span></div>
        </div>
      </div>
    </div>

    <!-- Slide 3 — Free Delivery -->
    <div class="hc-slide" style="background:linear-gradient(135deg,#064e3b 0%,#065f46 55%,#0f766e 100%)">
      <div class="hc-deco hc-deco--1" style="background:rgba(52,211,153,.12)"></div>
      <div class="hc-deco hc-deco--2" style="background:rgba(16,185,129,.1)"></div>
      <div class="container hc-inner">
        <div class="hc-content">
          <span class="hc-badge" style="background:rgba(52,211,153,.25);color:#a7f3d0"><i class="fas fa-truck"></i> Free Shipping</span>
          <h1 class="hc-title">Free Delivery<br>on <em>₱500+</em> Orders</h1>
          <p class="hc-desc">Order ₱500 or more and enjoy free shipping nationwide. Add more items to your cart and save more!</p>
          <div class="hc-btns">
            <a href="#products" class="btn btn-lg" style="background:#059669;color:#fff;border-color:#059669"><i class="fas fa-truck"></i> Shop & Save</a>
            <?php if (empty($is_rider)): ?>
            <a href="<?= SITE_URL ?>/customer/cart.php" class="hc-ghost-btn"><i class="fas fa-shopping-cart"></i> My Cart</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="hc-visual">
          <div class="hc-visual-ring" style="border-color:rgba(52,211,153,.4)">
            <div class="hc-visual-icon" style="background:linear-gradient(135deg,#047857,#10b981)">
              <i class="fas fa-truck"></i>
            </div>
          </div>
          <div class="hc-visual-stat hc-stat--tl" style="background:rgba(5,150,105,.35)">Fast<span>Shipping</span></div>
          <div class="hc-visual-stat hc-stat--br" style="background:rgba(5,150,105,.35)">₱500+<span>Free</span></div>
        </div>
      </div>
    </div>

    <!-- Slide 4 — New Arrivals -->
    <div class="hc-slide" style="background:linear-gradient(135deg,#2e1065 0%,#4c1d95 55%,#6d28d9 100%)">
      <div class="hc-deco hc-deco--1" style="background:rgba(167,139,250,.12)"></div>
      <div class="hc-deco hc-deco--2" style="background:rgba(139,92,246,.1)"></div>
      <div class="container hc-inner">
        <div class="hc-content">
          <span class="hc-badge" style="background:rgba(167,139,250,.25);color:#ddd6fe"><i class="fas fa-sparkles"></i> Just Arrived</span>
          <h1 class="hc-title">New Arrivals<br><em>This Week</em></h1>
          <p class="hc-desc">Fresh products added weekly — from trending gadgets to everyday essentials. Be the first to grab them!</p>
          <div class="hc-btns">
            <a href="?sort=newest" class="btn btn-lg" style="background:#7c3aed;color:#fff;border-color:#7c3aed"><i class="fas fa-magic"></i> See New Items</a>
            <a href="#featured" class="hc-ghost-btn"><i class="fas fa-heart"></i> Favourites</a>
          </div>
        </div>
        <div class="hc-visual">
          <div class="hc-visual-ring" style="border-color:rgba(167,139,250,.4)">
            <div class="hc-visual-icon" style="background:linear-gradient(135deg,#5b21b6,#8b5cf6)">
              <i class="fas fa-box-open"></i>
            </div>
          </div>
          <div class="hc-visual-stat hc-stat--tl" style="background:rgba(109,40,217,.35)">New<span>Weekly</span></div>
          <div class="hc-visual-stat hc-stat--br" style="background:rgba(109,40,217,.35)">Top<span>Picks</span></div>
        </div>
      </div>
    </div>

  </div><!-- /.hc-track -->

  <!-- Prev / Next arrows -->
  <button class="hc-nav hc-nav--prev" aria-label="Previous slide"><i class="fas fa-chevron-left"></i></button>
  <button class="hc-nav hc-nav--next" aria-label="Next slide"><i class="fas fa-chevron-right"></i></button>

  <!-- Dot indicators -->
  <div class="hc-dots" role="tablist">
    <button class="hc-dot active" data-idx="0" aria-label="Slide 1"></button>
    <button class="hc-dot" data-idx="1" aria-label="Slide 2"></button>
    <button class="hc-dot" data-idx="2" aria-label="Slide 3"></button>
    <button class="hc-dot" data-idx="3" aria-label="Slide 4"></button>
  </div>

</section>

<script>
(function(){
  const carousel = document.getElementById('heroCarousel');
  if (!carousel) return;
  const track  = carousel.querySelector('.hc-track');
  const slides = carousel.querySelectorAll('.hc-slide');
  const dots   = carousel.querySelectorAll('.hc-dot');
  const total  = slides.length;
  let current  = 0;
  let timer    = null;

  function goTo(idx) {
    slides[current].classList.remove('hc-slide--active');
    dots[current].classList.remove('active');
    current = (idx + total) % total;
    slides[current].classList.add('hc-slide--active');
    dots[current].classList.add('active');
    track.style.transform = 'translateX(-' + (current * 100) + '%)';
  }

  function startAuto() {
    stopAuto();
    timer = setInterval(function(){ goTo(current + 1); }, 4500);
  }
  function stopAuto() { clearInterval(timer); }

  // Init first slide
  slides[0].classList.add('hc-slide--active');

  carousel.querySelector('.hc-nav--prev').addEventListener('click', function(){
    goTo(current - 1); startAuto();
  });
  carousel.querySelector('.hc-nav--next').addEventListener('click', function(){
    goTo(current + 1); startAuto();
  });

  dots.forEach(function(dot){
    dot.addEventListener('click', function(){
      goTo(parseInt(this.dataset.idx)); startAuto();
    });
  });

  // Touch/swipe support
  let touchX = null;
  carousel.addEventListener('touchstart', function(e){ touchX = e.touches[0].clientX; }, {passive:true});
  carousel.addEventListener('touchend', function(e){
    if (touchX === null) return;
    const diff = touchX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) { goTo(diff > 0 ? current + 1 : current - 1); startAuto(); }
    touchX = null;
  }, {passive:true});

  // Pause on hover
  carousel.addEventListener('mouseenter', stopAuto);
  carousel.addEventListener('mouseleave', startAuto);

  startAuto();
})();
</script>

<?php if ($featured): ?>
<!-- ── Featured ───────────────────────────────────────────── -->
<section class="section" id="featured" style="padding-bottom:0">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><span>Featured</span> Products</h2>
      <a href="?sort=newest" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="products-grid">
      <?php foreach ($featured as $p): ?>
      <?php include __DIR__ . '/../includes/product_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php endif; ?>

<!-- ── Products Section ──────────────────────────────────── -->
<section class="section" id="products">
  <div class="container">
    <div class="shop-layout">

      <!-- Filters sidebar -->
      <aside class="filters-panel">
        <form method="GET" class="auto-submit">
          <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>

          <div class="card">
            <div class="card-header" style="font-size:.85rem">
              <span><i class="fas fa-filter"></i> Filters</span>
              <a href="<?= SITE_URL ?>/customer/index.php" style="font-size:.75rem;font-weight:500;color:var(--text-muted)">Clear</a>
            </div>
            <div class="card-body" style="padding:18px">

              <div class="filter-section">
                <div class="filter-title">Categories</div>
                <?php
                $cats = $db->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll();
                foreach ($cats as $c):
                ?>
                <label class="filter-option">
                  <input type="checkbox" name="category" value="<?= $c['id'] ?>"
                         <?= $category == $c['id'] ? 'checked' : '' ?>
                         onchange="this.form.submit()">
                  <?= e($c['name']) ?>
                </label>
                <?php endforeach; ?>
              </div>

              <div class="filter-section">
                <div class="filter-title">Price Range</div>
                <div style="display:flex;gap:8px;align-items:center">
                  <input type="number" name="min_price" class="form-control" placeholder="Min"
                         value="<?= $min_price ?: '' ?>" style="font-size:.8rem;padding:7px 10px" min="0">
                  <span style="color:var(--text-muted)">—</span>
                  <input type="number" name="max_price" class="form-control" placeholder="Max"
                         value="<?= ($max_price < 99999) ? $max_price : '' ?>" style="font-size:.8rem;padding:7px 10px" min="0">
                </div>
                <button type="submit" class="btn btn-outline btn-sm btn-block mt-8">Apply</button>
              </div>

              <div class="filter-section">
                <div class="filter-title">Sort By</div>
                <?php
                $sorts = ['newest'=>'Newest','price_asc'=>'Price: Low–High','price_desc'=>'Price: High–Low','name_asc'=>'Name A–Z'];
                foreach ($sorts as $val => $label):
                ?>
                <label class="filter-option">
                  <input type="radio" name="sort" value="<?= $val ?>"
                         <?= $sort === $val ? 'checked' : '' ?>
                         onchange="this.form.submit()">
                  <?= $label ?>
                </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </form>
      </aside>

      <!-- Product grid -->
      <div>
        <!-- Header row -->
        <div class="d-flex align-center justify-between mb-16" style="flex-wrap:wrap;gap:10px">
          <div>
            <?php if ($q): ?>
            <h2 style="font-size:1.1rem;margin-bottom:4px">Results for "<?= e($q) ?>"</h2>
            <?php elseif ($category): ?>
            <h2 style="font-size:1.1rem;margin-bottom:4px"><?= e($cats[array_search($category, array_column($cats,'id'))]['name'] ?? 'Category') ?></h2>
            <?php else: ?>
            <h2 style="font-size:1.1rem;margin-bottom:4px">All Products</h2>
            <?php endif; ?>
            <p style="font-size:.82rem;margin:0"><?= $total ?> product<?= $total !== 1 ? 's' : '' ?> found</p>
          </div>
          <form method="GET" style="display:flex;align-items:center;gap:8px">
            <?php if ($q):     ?><input type="hidden" name="q"        value="<?= e($q) ?>"><?php endif; ?>
            <?php if ($category): ?><input type="hidden" name="category" value="<?= $category ?>"><?php endif; ?>
            <label style="font-size:.82rem;color:var(--text-muted)">Sort:</label>
            <select name="sort" class="form-control" style="width:auto;font-size:.82rem;padding:7px 28px 7px 10px" onchange="this.form.submit()">
              <?php foreach ($sorts as $v => $l): ?>
              <option value="<?= $v ?>" <?= $sort === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>

        <?php if ($products): ?>
        <div class="products-grid">
          <?php foreach ($products as $p): ?>
          <?php include __DIR__ . '/../includes/product_card.php'; ?>
          <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pag['pages'] > 1): ?>
        <div class="pagination">
          <?php
          $base = '?q=' . urlencode($q) . '&category=' . $category . '&sort=' . $sort;
          ?>
          <?php if ($pag['current'] > 1): ?>
          <a href="<?= $base ?>&page=<?= $pag['current'] - 1 ?>"><i class="fas fa-chevron-left"></i></a>
          <?php else: ?>
          <span class="disabled"><i class="fas fa-chevron-left"></i></span>
          <?php endif; ?>

          <?php for ($i = max(1, $pag['current']-2); $i <= min($pag['pages'], $pag['current']+2); $i++): ?>
          <?php if ($i === $pag['current']): ?>
          <span class="active"><?= $i ?></span>
          <?php else: ?>
          <a href="<?= $base ?>&page=<?= $i ?>"><?= $i ?></a>
          <?php endif; ?>
          <?php endfor; ?>

          <?php if ($pag['current'] < $pag['pages']): ?>
          <a href="<?= $base ?>&page=<?= $pag['current'] + 1 ?>"><i class="fas fa-chevron-right"></i></a>
          <?php else: ?>
          <span class="disabled"><i class="fas fa-chevron-right"></i></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state card">
          <div class="card-body">
            <div class="empty-icon"><i class="fas fa-search"></i></div>
            <h3>No products found</h3>
            <p>Try adjusting your filters or search terms.</p>
            <a href="<?= SITE_URL ?>/customer/index.php" class="btn btn-primary mt-16">Browse All Products</a>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
