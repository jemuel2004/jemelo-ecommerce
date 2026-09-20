<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_panel();

$page_title  = 'Products';
$active_menu = 'products';
$db = getDB();

// ── Handle actions ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Add product
    if (isset($_POST['add_product'])) {
        $name        = trim($_POST['name']        ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price       = (float)($_POST['price']     ?? 0);
        $sale_price  = trim($_POST['sale_price']   ?? '') !== '' ? (float)$_POST['sale_price'] : null;
        $stock       = (int)($_POST['stock']       ?? 0);
        $description = trim($_POST['description']  ?? '');
        $status      = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';
        $featured    = isset($_POST['featured']) ? 1 : 0;

        $image = 'no-image.png';
        if (!empty($_FILES['image']['tmp_name'])) {
            $uploaded = upload_image($_FILES['image']);
            if ($uploaded) $image = $uploaded;
        }

        if ($name && $price >= 0) {
            $ins = $db->prepare("INSERT INTO products (category_id,name,description,price,sale_price,stock,image,status,featured) VALUES (?,?,?,?,?,?,?,?,?)");
            $ins->execute([$category_id ?: null, $name, $description, $price, $sale_price, $stock, $image, $status, $featured]);
            $newProdId = $db->lastInsertId();

            // Notify customers of new product (only if active)
            if ($status === 'active') {
                $displayPrice = $sale_price ? currency($sale_price) : currency($price);
                notify_all_customers(
                    'new_product',
                    'New Product: ' . $name,
                    'Now available at ' . $displayPrice . ($sale_price ? ' (on sale!)' : ''),
                    SITE_URL . '/customer/product.php?id=' . $newProdId
                );
            }
            // Warn admin if stock is already low
            if ($stock > 0 && $stock <= 5) {
                notify_admins('low_stock', 'Low Stock Alert', '"' . $name . '" has only ' . $stock . ' units left.', SITE_URL . '/admin/products.php');
            }
            flash('prod', 'Product added successfully!', 'success');
        } else {
            flash('prod', 'Name and valid price are required.', 'danger');
        }
        redirect(SITE_URL . '/admin/products.php');
    }

    // Edit product
    if (isset($_POST['edit_product'])) {
        $id          = (int)($_POST['id']          ?? 0);
        $name        = trim($_POST['name']         ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price       = (float)($_POST['price']     ?? 0);
        $sale_price  = trim($_POST['sale_price']   ?? '') !== '' ? (float)$_POST['sale_price'] : null;
        $stock       = (int)($_POST['stock']       ?? 0);
        $description = trim($_POST['description']  ?? '');
        $status      = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';
        $featured    = isset($_POST['featured']) ? 1 : 0;

        if (!$id) {
            flash('prod', 'Invalid product ID.', 'danger');
            redirect(SITE_URL . '/admin/products.php');
        }
        if (!$name) {
            flash('prod', 'Product name is required.', 'danger');
            redirect(SITE_URL . '/admin/products.php');
        }
        if ($price < 0) {
            flash('prod', 'Price cannot be negative.', 'danger');
            redirect(SITE_URL . '/admin/products.php');
        }

        // Fetch current image so we can delete it if a new one is uploaded
        $curSt = $db->prepare("SELECT image FROM products WHERE id=?");
        $curSt->execute([$id]);
        $currentImage = $curSt->fetchColumn();

        if ($currentImage === false) {
            flash('prod', 'Product not found.', 'danger');
            redirect(SITE_URL . '/admin/products.php');
        }

        $image_sql = '';
        $imgParams = [];

        if (!empty($_FILES['image']['tmp_name'])) {
            $uploaded = upload_image($_FILES['image']);
            if ($uploaded === false) {
                flash('prod', 'Image must be JPEG, PNG, or WebP and under 3 MB.', 'danger');
                redirect(SITE_URL . '/admin/products.php');
            }
            $image_sql = ', image=?';
            $imgParams[] = $uploaded;
            // Delete old image file from disk
            if ($currentImage && $currentImage !== 'no-image.png') {
                @unlink(UPLOAD_PATH . 'products/' . $currentImage);
            }
        }

        $params = array_merge([$category_id ?: null, $name, $description, $price, $sale_price, $stock, $status, $featured], $imgParams, [$id]);
        $upd = $db->prepare("UPDATE products SET category_id=?,name=?,description=?,price=?,sale_price=?,stock=?,status=?,featured=? $image_sql WHERE id=?");
        $upd->execute($params);

        // Low stock alert on update
        if ($stock > 0 && $stock <= 5) {
            notify_admins('low_stock', 'Low Stock Alert', '"' . $name . '" has only ' . $stock . ' units left.', SITE_URL . '/admin/products.php');
        } elseif ($stock === 0) {
            notify_admins('low_stock', 'Out of Stock', '"' . $name . '" is now out of stock.', SITE_URL . '/admin/products.php');
        }
        // Sale price added → notify customers as promo
        if ($sale_price && $status === 'active') {
            notify_all_customers(
                'promo',
                'New Promo: ' . $name,
                'Now on sale at ' . currency($sale_price) . '!',
                SITE_URL . '/customer/product.php?id=' . $id
            );
        }
        flash('prod', 'Product "' . $name . '" updated successfully!', 'success');
        redirect(SITE_URL . '/admin/products.php');
    }

    // Delete product
    if (isset($_POST['delete_product'])) {
        $id = (int)($_POST['delete_id'] ?? 0);
        $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        flash('prod', 'Product deleted.', 'success');
        redirect(SITE_URL . '/admin/products.php');
    }
}

require_once __DIR__ . '/../includes/admin_header.php';

// ── Fetch products ────────────────────────────────────────────
$q       = trim($_GET['q'] ?? '');
$cat_f   = (int)($_GET['cat'] ?? 0);
$status_f = $_GET['status'] ?? '';
$stock_f  = $_GET['stock'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 15;

$where  = ['1=1'];
$params = [];
if ($q)        { $where[] = 'p.name LIKE ?'; $params[] = '%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%'; }
if ($cat_f)    { $where[] = 'p.category_id = ?';    $params[] = $cat_f; }
if ($status_f) { $where[] = "p.status = ?";          $params[] = $status_f; }
if ($stock_f === '0') { $where[] = 'p.stock = 0'; }
elseif ($stock_f === 'low') { $where[] = 'p.stock > 0 AND p.stock <= 5'; }

$where_sql = 'WHERE ' . implode(' AND ', $where);
$count_st = $db->prepare("SELECT COUNT(*) FROM products p $where_sql");
$count_st->execute($params);
$total = (int)$count_st->fetchColumn();
$pag   = paginate($total, $limit, $page);

$st = $db->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id $where_sql ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
$st->execute(array_merge($params, [$pag['per_page'], $pag['offset']]));
$products = $st->fetchAll();

$categories = $db->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
?>

<div class="admin-page-header">
  <div>
    <div class="admin-page-title">Products</div>
    <div class="admin-page-subtitle"><?= $total ?> products total</div>
  </div>
  <button class="btn btn-primary" onclick="openModal('add-product-modal')">
    <i class="fas fa-plus"></i> Add Product
  </button>
</div>

<?php show_flash('prod'); ?>

<div class="admin-card">
  <div class="admin-card-header">
    <form method="GET" class="filter-bar" style="width:100%">
      <div class="search-input-wrap">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Search products…" value="<?= e($q) ?>">
      </div>
      <select name="cat" class="filter-select" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $cat_f == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="filter-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="active"   <?= $status_f === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $status_f === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
      <select name="stock" class="filter-select" onchange="this.form.submit()">
        <option value="">All Stock</option>
        <option value="0"   <?= $stock_f === '0'   ? 'selected' : '' ?>>Out of Stock</option>
        <option value="low" <?= $stock_f === 'low' ? 'selected' : '' ?>>Low Stock (≤5)</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="<?= SITE_URL ?>/admin/products.php" class="btn btn-outline-dark btn-sm">Reset</a>
    </form>
  </div>
  <div class="admin-card-body no-pad">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><input type="checkbox" id="select-all"></th>
            <th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Featured</th><th>Actions</th>
          </tr>
        </thead>
        <tbody id="table-body">
          <?php foreach ($products as $p): ?>
          <tr>
            <td><input type="checkbox" class="row-check"></td>
            <td>
              <div style="display:flex;align-items:center;gap:12px">
                <img src="<?= product_img($p['image']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:var(--radius);border:1px solid var(--border)">
                <div>
                  <div style="font-weight:600;font-size:.88rem"><?= e($p['name']) ?></div>
                  <div style="font-size:.75rem;color:var(--text-muted)">ID #<?= $p['id'] ?></div>
                </div>
              </div>
            </td>
            <td><span style="font-size:.82rem"><?= e($p['cat_name'] ?? '—') ?></span></td>
            <td>
              <?= currency($p['price']) ?>
              <?php if ($p['sale_price']): ?>
              <br><small style="color:var(--danger)"><?= currency($p['sale_price']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($p['stock'] == 0): ?>
              <span style="color:var(--danger);font-weight:700">Out</span>
              <?php elseif ($p['stock'] <= 5): ?>
              <span style="color:var(--warning);font-weight:700"><?= $p['stock'] ?> ⚠</span>
              <?php else: ?>
              <span style="font-weight:600"><?= $p['stock'] ?></span>
              <?php endif; ?>
            </td>
            <td><?= status_badge($p['status']) ?></td>
            <td><?php if ($p['featured']): ?><i class="fas fa-star" style="color:var(--warning)"></i><?php else: ?><i class="far fa-star" style="color:var(--text-light)"></i><?php endif; ?></td>
            <td>
              <div style="display:flex;gap:6px">
                <button class="btn btn-sm btn-outline" onclick='editProduct(<?= json_encode($p) ?>)' title="Edit">
                  <i class="fas fa-edit"></i>
                </button>
                <form method="POST" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                  <button type="submit" name="delete_product" class="btn btn-sm btn-danger" data-confirm="Delete this product?">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$products): ?>
          <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">No products found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($pag['pages'] > 1): ?>
  <div class="card-footer">
    <div class="pagination" style="padding:8px 0 0">
      <?php $base = "?q=$q&cat=$cat_f&status=$status_f&stock=$stock_f"; ?>
      <?php if ($pag['current'] > 1): ?><a href="<?= $base ?>&page=<?= $pag['current']-1 ?>"><i class="fas fa-chevron-left"></i></a><?php else: ?><span class="disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
      <?php for ($i = max(1,$pag['current']-2); $i <= min($pag['pages'],$pag['current']+2); $i++): ?>
      <?php if ($i === $pag['current']): ?><span class="active"><?= $i ?></span><?php else: ?><a href="<?= $base ?>&page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($pag['current'] < $pag['pages']): ?><a href="<?= $base ?>&page=<?= $pag['current']+1 ?>"><i class="fas fa-chevron-right"></i></a><?php else: ?><span class="disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ── Add Product Modal ──────────────────────────────────── -->
<div class="modal-overlay" id="add-product-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Add New Product</span>
      <button class="modal-close" type="button"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="modal-body">
        <?php $modal_prefix = 'add'; include __DIR__ . '/../includes/product_form.php'; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" onclick="closeModal('add-product-modal')">Cancel</button>
        <button type="submit" name="add_product" class="btn btn-primary"><i class="fas fa-plus"></i> Save Product</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Product Modal ─────────────────────────────────── -->
<div class="modal-overlay" id="edit-product-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-edit" style="color:var(--primary)"></i> Edit Product</span>
      <button class="modal-close" type="button"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" id="edit-product-form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="edit-id">
      <div class="modal-body">
        <?php $modal_prefix = 'edit'; include __DIR__ . '/../includes/product_form.php'; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" onclick="closeModal('edit-product-modal')">Cancel</button>
        <button type="submit" name="edit_product" class="btn btn-primary"><i class="fas fa-check"></i> Update Product</button>
      </div>
    </form>
  </div>
</div>

<script>
function editProduct(p) {
  const f = document.getElementById('edit-product-form');

  // Populate fields
  f.querySelector('[name=id]').value          = p.id;
  f.querySelector('[name=name]').value        = p.name;
  f.querySelector('[name=category_id]').value = p.category_id || '';
  f.querySelector('[name=price]').value       = p.price;
  f.querySelector('[name=sale_price]').value  = p.sale_price || '';
  f.querySelector('[name=stock]').value       = p.stock;
  f.querySelector('[name=description]').value = p.description || '';
  f.querySelector('[name=status]').value      = p.status;
  f.querySelector('[name=featured]').checked  = p.featured == '1';

  // Reset file input so no stale file from a previous edit gets submitted
  const fileInput = document.getElementById('edit-img-file');
  if (fileInput) fileInput.value = '';

  // Restore current image preview
  const preview     = document.getElementById('edit-img-preview');
  const placeholder = document.getElementById('edit-upload-placeholder');
  if (preview && placeholder) {
    if (p.image && p.image !== 'no-image.png') {
      preview.src           = window.SITE_URL + '/assets/uploads/products/' + encodeURIComponent(p.image);
      preview.style.display = 'block';
      placeholder.style.display = 'none';
    } else {
      preview.src           = '';
      preview.style.display = 'none';
      placeholder.style.display = 'flex';
    }
  }

  openModal('edit-product-modal');
}

// Reset edit modal state when it is closed
document.addEventListener('click', function(e) {
  if ((e.target.matches('.modal-overlay') && e.target.id === 'edit-product-modal') ||
      (e.target.closest('.modal-close') && e.target.closest('#edit-product-modal'))) {
    const fileInput = document.getElementById('edit-img-file');
    if (fileInput) fileInput.value = '';
  }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
