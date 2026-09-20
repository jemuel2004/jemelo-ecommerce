<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_panel();

$page_title  = 'Categories';
$active_menu = 'categories';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['add_category'])) {
        $name   = trim($_POST['name']        ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        if (!$name) {
            flash('cat', 'Category name is required.', 'danger');
            redirect(SITE_URL . '/admin/categories.php');
        }

        $image = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $up = upload_image($_FILES['image'], 'categories');
            if ($up === false) {
                flash('cat', 'Image must be JPEG, PNG, or WebP and under 3 MB.', 'danger');
                redirect(SITE_URL . '/admin/categories.php');
            }
            $image = $up;
        }

        try {
            $db->prepare("INSERT INTO categories (name,description,image,status) VALUES (?,?,?,?)")
               ->execute([$name, $desc, $image, $status]);
            flash('cat', 'Category "' . $name . '" added!', 'success');
        } catch (PDOException $e) {
            flash('cat', 'A category with that name already exists.', 'danger');
        }
        redirect(SITE_URL . '/admin/categories.php');
    }

    if (isset($_POST['edit_category'])) {
        $id     = (int)($_POST['id']         ?? 0);
        $name   = trim($_POST['name']        ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        if (!$id) {
            flash('cat', 'Invalid category ID.', 'danger');
            redirect(SITE_URL . '/admin/categories.php');
        }
        if (!$name) {
            flash('cat', 'Category name is required.', 'danger');
            redirect(SITE_URL . '/admin/categories.php');
        }

        // Fetch current image for potential deletion
        $curSt = $db->prepare("SELECT image FROM categories WHERE id=?");
        $curSt->execute([$id]);
        $currentImage = $curSt->fetchColumn();

        if ($currentImage === false) {
            flash('cat', 'Category not found.', 'danger');
            redirect(SITE_URL . '/admin/categories.php');
        }

        $extra = ''; $imgParams = [];
        if (!empty($_FILES['image']['tmp_name'])) {
            $up = upload_image($_FILES['image'], 'categories');
            if ($up === false) {
                flash('cat', 'Image must be JPEG, PNG, or WebP and under 3 MB.', 'danger');
                redirect(SITE_URL . '/admin/categories.php');
            }
            $extra = ', image=?';
            $imgParams[] = $up;
            // Delete old image from disk
            if ($currentImage) {
                @unlink(UPLOAD_PATH . 'categories/' . $currentImage);
            }
        }

        $params = array_merge([$name, $desc, $status], $imgParams, [$id]);
        $db->prepare("UPDATE categories SET name=?,description=?,status=? $extra WHERE id=?")->execute($params);
        flash('cat', 'Category "' . $name . '" updated!', 'success');
        redirect(SITE_URL . '/admin/categories.php');
    }

    if (isset($_POST['delete_category'])) {
        $id = (int)($_POST['delete_id'] ?? 0);
        if ($id) {
            // Delete image file too
            $imgSt = $db->prepare("SELECT image FROM categories WHERE id=?");
            $imgSt->execute([$id]);
            $img = $imgSt->fetchColumn();
            if ($img) @unlink(UPLOAD_PATH . 'categories/' . $img);
            $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        }
        flash('cat', 'Category deleted.', 'success');
        redirect(SITE_URL . '/admin/categories.php');
    }
}

require_once __DIR__ . '/../includes/admin_header.php';

$categories = $db->query("SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON c.id=p.category_id GROUP BY c.id ORDER BY c.name")->fetchAll();
?>

<div class="admin-page-header">
  <div>
    <div class="admin-page-title">Categories</div>
    <div class="admin-page-subtitle"><?= count($categories) ?> categories</div>
  </div>
  <button class="btn btn-primary" onclick="openModal('add-cat-modal')">
    <i class="fas fa-plus"></i> Add Category
  </button>
</div>

<?php show_flash('cat'); ?>

<div class="admin-card">
  <div class="admin-card-body no-pad">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Image</th><th>Name</th><th>Description</th><th>Products</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
          <tr>
            <td style="color:var(--text-muted)"><?= $c['id'] ?></td>
            <td>
              <?php if ($c['image']): ?>
              <img src="<?= category_img($c['image']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:var(--radius);border:1px solid var(--border)">
              <?php else: ?>
              <div style="width:40px;height:40px;background:var(--bg-alt);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;color:var(--text-light)"><i class="fas fa-tag"></i></div>
              <?php endif; ?>
            </td>
            <td style="font-weight:600"><?= e($c['name']) ?></td>
            <td style="font-size:.82rem;color:var(--text-muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($c['description'] ?? '') ?></td>
            <td><span class="badge badge-primary"><?= $c['product_count'] ?></span></td>
            <td><?= status_badge($c['status']) ?></td>
            <td>
              <div style="display:flex;gap:6px">
                <button class="btn btn-sm btn-outline" onclick='editCat(<?= json_encode($c) ?>)'>
                  <i class="fas fa-edit"></i>
                </button>
                <form method="POST" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                  <button type="submit" name="delete_category" class="btn btn-sm btn-danger"
                          data-confirm="Delete '<?= e(addslashes($c['name'])) ?>'? Products in this category will be uncategorized.">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$categories): ?>
          <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-tags" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No categories yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Add Category Modal ─────────────────────────────────── -->
<div class="modal-overlay" id="add-cat-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Add Category</span>
      <button class="modal-close" type="button"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Name <span style="color:#ef4444">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="Category name" required>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Optional description…"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Image</label>
          <label class="img-upload-area" id="add-cat-upload-area" style="height:110px">
            <input type="file" name="image" id="add-cat-img-file"
                   accept="image/jpeg,image/png,image/webp"
                   data-preview="add-cat-img-preview"
                   data-placeholder="add-cat-upload-placeholder"
                   style="display:none">
            <div class="img-upload-placeholder" id="add-cat-upload-placeholder">
              <i class="fas fa-cloud-upload-alt"></i>
              <span>Click to upload</span>
              <small>JPEG · PNG · WebP · max 3 MB</small>
            </div>
            <img id="add-cat-img-preview" class="img-upload-preview" src="" alt="preview" style="display:none">
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" onclick="closeModal('add-cat-modal')">Cancel</button>
        <button type="submit" name="add_category" class="btn btn-primary"><i class="fas fa-plus"></i> Save Category</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Category Modal ────────────────────────────────── -->
<div class="modal-overlay" id="edit-cat-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-edit" style="color:var(--primary)"></i> Edit Category</span>
      <button class="modal-close" type="button"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" id="edit-cat-form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="edit-cat-id">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Name <span style="color:#ef4444">*</span></label>
            <input type="text" name="name" id="edit-cat-name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="edit-cat-status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit-cat-desc" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Image</label>
          <label class="img-upload-area" id="edit-cat-upload-area" style="height:110px">
            <input type="file" name="image" id="edit-cat-img-file"
                   accept="image/jpeg,image/png,image/webp"
                   data-preview="edit-cat-img-preview"
                   data-placeholder="edit-cat-upload-placeholder"
                   style="display:none">
            <div class="img-upload-placeholder" id="edit-cat-upload-placeholder">
              <i class="fas fa-cloud-upload-alt"></i>
              <span>Click to replace image</span>
              <small>JPEG · PNG · WebP · max 3 MB</small>
            </div>
            <img id="edit-cat-img-preview" class="img-upload-preview" src="" alt="preview" style="display:none">
          </label>
          <div class="form-hint" style="margin-top:5px"><i class="fas fa-info-circle"></i> Leave empty to keep current image.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" onclick="closeModal('edit-cat-modal')">Cancel</button>
        <button type="submit" name="edit_category" class="btn btn-primary"><i class="fas fa-check"></i> Update Category</button>
      </div>
    </form>
  </div>
</div>

<script>
function editCat(c) {
  document.getElementById('edit-cat-id').value     = c.id;
  document.getElementById('edit-cat-name').value   = c.name;
  document.getElementById('edit-cat-desc').value   = c.description || '';
  document.getElementById('edit-cat-status').value = c.status;

  // Reset file input to avoid stale file from previous edit
  const fileInput = document.getElementById('edit-cat-img-file');
  if (fileInput) fileInput.value = '';

  // Restore current image preview
  const preview     = document.getElementById('edit-cat-img-preview');
  const placeholder = document.getElementById('edit-cat-upload-placeholder');
  if (preview && placeholder) {
    if (c.image) {
      preview.src           = window.SITE_URL + '/assets/uploads/categories/' + encodeURIComponent(c.image);
      preview.style.display = 'block';
      placeholder.style.display = 'none';
    } else {
      preview.src           = '';
      preview.style.display = 'none';
      placeholder.style.display = 'flex';
    }
  }

  openModal('edit-cat-modal');
}

// Reset state when edit modal is closed
document.addEventListener('click', function(e) {
  if ((e.target.matches('.modal-overlay') && e.target.id === 'edit-cat-modal') ||
      (e.target.closest('.modal-close') && e.target.closest('#edit-cat-modal'))) {
    const fi = document.getElementById('edit-cat-img-file');
    if (fi) fi.value = '';
  }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
