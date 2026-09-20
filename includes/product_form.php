<?php
// Shared product form fields (used in add & edit modals)
// Requires $modal_prefix ('add' or 'edit') to be set by the caller.
$modal_prefix = $modal_prefix ?? 'add';
$categories   = $categories ?? getDB()->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
?>

<!-- Row 1: Name + Category -->
<div class="form-row-2">
  <div class="form-group">
    <label class="form-label">Product Name <span style="color:#ef4444">*</span></label>
    <input type="text" name="name" class="form-control" placeholder="e.g. Wireless Headphones" required>
  </div>
  <div class="form-group">
    <label class="form-label">Category</label>
    <select name="category_id" class="form-control">
      <option value="">— No Category —</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<!-- Row 2: Price + Sale Price + Stock -->
<div class="form-row-3">
  <div class="form-group">
    <label class="form-label">Price (₱) <span style="color:#ef4444">*</span></label>
    <input type="number" name="price" class="form-control" step="0.01" min="0" placeholder="0.00" required>
  </div>
  <div class="form-group">
    <label class="form-label">Sale Price (₱)</label>
    <input type="number" name="sale_price" class="form-control" step="0.01" min="0" placeholder="Optional">
  </div>
  <div class="form-group">
    <label class="form-label">Stock <span style="color:#ef4444">*</span></label>
    <input type="number" name="stock" class="form-control" min="0" placeholder="0" required>
  </div>
</div>

<!-- Row 3: Description -->
<div class="form-group">
  <label class="form-label">Description</label>
  <textarea name="description" class="form-control" rows="3" placeholder="Short product description…"></textarea>
</div>

<div class="form-divider"></div>

<!-- Row 4: Status + Image -->
<div class="form-row-2">
  <div class="form-group">
    <label class="form-label">Status</label>
    <select name="status" class="form-control">
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>
    <div style="margin-top:14px;display:flex;align-items:center;gap:10px">
      <label class="toggle" title="Mark as Featured">
        <input type="checkbox" name="featured">
        <span class="toggle-slider"></span>
      </label>
      <span style="font-size:.82rem;color:#374151;font-weight:500">Featured Product</span>
    </div>
  </div>
  <div class="form-group">
    <label class="form-label">Product Image</label>
    <label class="img-upload-area" id="<?= $modal_prefix ?>-upload-area" style="height:110px">
      <input type="file" name="image" id="<?= $modal_prefix ?>-img-file"
             accept="image/jpeg,image/png,image/webp"
             data-preview="<?= $modal_prefix ?>-img-preview"
             data-placeholder="<?= $modal_prefix ?>-upload-placeholder"
             style="display:none">
      <div class="img-upload-placeholder" id="<?= $modal_prefix ?>-upload-placeholder">
        <i class="fas fa-cloud-upload-alt"></i>
        <span>Click to upload</span>
        <small>JPEG · PNG · WebP · max 3 MB</small>
      </div>
      <img id="<?= $modal_prefix ?>-img-preview" class="img-upload-preview" src="" alt="preview" style="display:none">
    </label>
    <?php if ($modal_prefix === 'edit'): ?>
    <div class="form-hint" style="margin-top:5px"><i class="fas fa-info-circle"></i> Leave empty to keep current image.</div>
    <?php endif; ?>
  </div>
</div>
