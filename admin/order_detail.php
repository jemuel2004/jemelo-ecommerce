<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_panel();

$page_title  = 'Order Details';
$active_menu = 'orders';
$db = getDB();

// ── All logic with redirects BEFORE any HTML output ──────────
$id = (int)($_GET['id'] ?? 0);
$st = $db->prepare("
    SELECT o.*, u.name AS customer, u.email AS customer_email, u.phone AS customer_phone,
           r.name AS rider_name, r.phone AS rider_phone, r.avatar AS rider_avatar
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN users r ON o.rider_id = r.id
    WHERE o.id = ?
");
$st->execute([$id]);
$order = $st->fetch();
if (!$order) redirect(SITE_URL . '/admin/orders.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $status  = $_POST['status']         ?? $order['status'];
    $pay_st  = $_POST['payment_status'] ?? $order['payment_status'];
    $allowed = ['pending','processing','picked_up','out_for_delivery','shipped','delivered','failed','cancelled'];

    if (in_array($status, $allowed)) {
        // Auto-pay COD orders when admin marks as Delivered
        if ($status === 'delivered'
            && in_array($order['payment_method'], ['cod', 'cash_on_delivery'])
            && $pay_st !== 'paid') {
            $pay_st = 'paid';
        }
        $db->prepare("UPDATE orders SET status=?, payment_status=? WHERE id=?")->execute([$status, $pay_st, $id]);

        // Sync delivery record timestamps when admin manually updates
        if ($status === 'delivered') {
            $db->prepare("UPDATE deliveries SET status='delivered', delivered_at=COALESCE(delivered_at,NOW()) WHERE order_id=?")
               ->execute([$id]);
        } elseif ($status === 'failed') {
            $db->prepare("UPDATE deliveries SET status='failed' WHERE order_id=?")->execute([$id]);
        } elseif ($status === 'picked_up') {
            $db->prepare("UPDATE deliveries SET status='picked_up', picked_up_at=COALESCE(picked_up_at,NOW()) WHERE order_id=?")
               ->execute([$id]);
        } elseif ($status === 'out_for_delivery') {
            $db->prepare("UPDATE deliveries SET status='out_for_delivery', out_for_delivery_at=COALESCE(out_for_delivery_at,NOW()) WHERE order_id=?")
               ->execute([$id]);
        }

        flash('ord_det', 'Order updated successfully!', 'success');
        redirect(SITE_URL . '/admin/order_detail.php?id=' . $id);
    }
}

require_once __DIR__ . '/../includes/admin_header.php';

$items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
$items->execute([$id]);
$items = $items->fetchAll();

// Active riders for assignment dropdown
$riders_list = $db->query("SELECT id, name, phone FROM users WHERE role='rider' AND status='active' ORDER BY name")->fetchAll();

// Latest delivery record
$delSt = $db->prepare("SELECT * FROM deliveries WHERE order_id=? ORDER BY assigned_at DESC LIMIT 1");
$delSt->execute([$id]);
$delivery = $delSt->fetch();

// Statuses that allow rider assignment (include 'failed' for re-assignment)
$assignable_statuses = ['pending','processing','picked_up','out_for_delivery','failed'];
$can_assign = $_SESSION['user_role'] === 'admin' && in_array($order['status'], $assignable_statuses);
?>

<div class="admin-page-header">
  <div>
    <div class="admin-page-title">Order #<?= e($order['order_number']) ?></div>
    <div class="admin-page-subtitle"><?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></div>
  </div>
  <a href="<?= SITE_URL ?>/admin/orders.php" class="btn btn-outline-dark">
    <i class="fas fa-arrow-left"></i> Back
  </a>
</div>

<div id="assign-msg"></div>
<?php show_flash('ord_det'); ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">
  <!-- ── Left column ─────────────────────────────────────── -->
  <div>
    <!-- Items -->
    <div class="admin-card mb-20">
      <div class="admin-card-header">
        <span class="admin-card-title">Items Ordered (<?= count($items) ?>)</span>
      </div>
      <div class="admin-card-body no-pad">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
            <tbody>
              <?php foreach ($items as $item): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px">
                    <img src="<?= product_img($item['product_image'] ?? '') ?>"
                         style="width:44px;height:44px;object-fit:cover;border-radius:var(--radius);border:1px solid var(--border)">
                    <span style="font-weight:600;font-size:.88rem"><?= e($item['product_name']) ?></span>
                  </div>
                </td>
                <td><?= currency($item['price']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td style="font-weight:700"><?= currency($item['subtotal']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="padding:16px 22px;text-align:right;border-top:1px solid var(--border)">
          <span style="font-size:1.1rem;font-weight:800">Total: <?= currency($order['total_amount']) ?></span>
        </div>
      </div>
    </div>

    <!-- Shipping Address -->
    <div class="admin-card mb-20">
      <div class="admin-card-header"><span class="admin-card-title">Shipping Address</span></div>
      <div class="admin-card-body">
        <div style="font-weight:600;margin-bottom:4px"><?= e($order['shipping_name']) ?></div>
        <div><i class="fas fa-phone" style="color:var(--text-muted);margin-right:6px"></i><?= e($order['shipping_phone']) ?></div>
        <div style="margin-top:6px">
          <i class="fas fa-map-marker-alt" style="color:var(--text-muted);margin-right:6px"></i><?= e($order['shipping_address']) ?>
        </div>
        <?php if ($order['notes']): ?>
        <div style="margin-top:10px;padding:10px;background:var(--bg-alt);border-radius:var(--radius);font-size:.84rem;font-style:italic">
          <i class="fas fa-sticky-note" style="color:var(--warning);margin-right:6px"></i><?= e($order['notes']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Delivery Timeline -->
    <?php if ($delivery): ?>
    <div class="admin-card mb-20">
      <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-clock" style="color:var(--primary)"></i> Delivery Timeline</span>
        <span><?= delivery_status_badge($delivery['status']) ?></span>
      </div>
      <div class="admin-card-body" style="font-size:.86rem">
        <?php
        $timeline = [
            ['Assigned to Rider', $delivery['assigned_at']],
            ['Picked Up',         $delivery['picked_up_at']],
            ['Out for Delivery',  $delivery['out_for_delivery_at']],
            ['Delivered',         $delivery['delivered_at']],
        ];
        $shown = 0;
        foreach ($timeline as [$tl, $dt]):
            if (!$dt) continue;
            $shown++;
        ?>
        <div style="display:flex;gap:12px;margin-bottom:10px">
          <i class="fas fa-circle" style="color:var(--primary);font-size:.5rem;margin-top:5px;flex-shrink:0"></i>
          <div>
            <div style="font-weight:600"><?= $tl ?></div>
            <div style="color:var(--text-muted)"><?= date('M j, Y g:i A', strtotime($dt)) ?></div>
          </div>
        </div>
        <?php endforeach;
        if (!$shown): ?>
        <div style="color:var(--text-muted);font-size:.83rem">Rider assigned — awaiting pickup.</div>
        <?php endif; ?>
        <?php if ($delivery['notes']): ?>
        <div style="background:var(--bg-alt);padding:10px;border-radius:var(--radius);margin-top:8px;font-size:.83rem;font-style:italic">
          <i class="fas fa-sticky-note" style="color:var(--warning)"></i> <?= e($delivery['notes']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- COD payment note -->
    <?php if ($order['status'] === 'delivered' && $order['payment_status'] === 'paid' && in_array($order['payment_method'], ['cod','cash_on_delivery'])): ?>
    <div class="admin-card" style="border:2px solid var(--success)">
      <div class="admin-card-body" style="display:flex;align-items:center;gap:12px">
        <i class="fas fa-check-circle" style="color:var(--success);font-size:1.4rem"></i>
        <div>
          <div style="font-weight:700;color:var(--success)">COD Payment Collected</div>
          <div style="font-size:.82rem;color:var(--text-muted)">
            <?= currency($order['total_amount']) ?> collected by rider upon delivery.
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Right column ────────────────────────────────────── -->
  <div>
    <!-- Update Status Form -->
    <div class="admin-card mb-16">
      <div class="admin-card-header"><span class="admin-card-title">Update Order</span></div>
      <div class="admin-card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <div class="form-group">
            <label class="form-label">Order Status</label>
            <select name="status" class="form-control">
              <?php foreach (['pending','processing','picked_up','out_for_delivery','shipped','delivered','failed','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                <?= ucfirst(str_replace('_',' ',$s)) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Payment Status</label>
            <select name="payment_status" class="form-control">
              <?php foreach (['pending','unpaid','paid','failed'] as $ps): ?>
              <option value="<?= $ps ?>" <?= $order['payment_status'] === $ps ? 'selected' : '' ?>>
                <?= ucfirst($ps) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (in_array($order['payment_method'], ['cod','cash_on_delivery'])): ?>
          <div style="font-size:.76rem;color:var(--text-muted);padding:6px 10px;background:var(--bg-alt);border-radius:var(--radius);margin-bottom:10px">
            <i class="fas fa-info-circle" style="color:var(--warning)"></i>
            Setting status to <strong>Delivered</strong> will automatically mark payment as <strong>Paid</strong> for COD orders.
          </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-save"></i> Update Status
          </button>
        </form>
      </div>
    </div>

    <!-- Assign Rider (admin only, for assignable statuses) -->
    <?php if ($can_assign): ?>
    <div class="admin-card mb-16" id="assign-rider">
      <div class="admin-card-header">
        <span class="admin-card-title">
          <i class="fas fa-motorcycle" style="color:#f97316"></i>
          <?= $order['status'] === 'failed' ? 'Re-Assign Rider' : 'Assign Rider' ?>
        </span>
      </div>
      <div class="admin-card-body">
        <?php if ($order['rider_name']): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;padding:10px;background:#fff7ed;border-radius:var(--radius);border:1px solid #fed7aa">
          <img src="<?= avatar_url($order['rider_avatar']) ?>"
               style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #f97316">
          <div>
            <div style="font-weight:600;font-size:.85rem"><?= e($order['rider_name']) ?></div>
            <div style="font-size:.73rem;color:#92400e">
              <?= $order['status'] === 'failed' ? 'Previous rider (failed)' : 'Currently assigned' ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if (empty($riders_list)): ?>
        <div style="text-align:center;padding:16px;color:var(--text-muted);font-size:.83rem">
          <i class="fas fa-exclamation-triangle" style="color:var(--warning);margin-bottom:6px;display:block"></i>
          No active riders available.<br>
          <a href="<?= SITE_URL ?>/admin/riders.php" style="color:var(--primary)">Add a rider first</a>
        </div>
        <?php else: ?>
        <div class="form-group" style="margin-bottom:10px">
          <label class="form-label">Select Rider</label>
          <select id="assign-rider-select" class="form-control">
            <option value="">— Choose a rider —</option>
            <?php foreach ($riders_list as $r): ?>
            <option value="<?= $r['id'] ?>"
              <?= $order['rider_id'] == $r['id'] ? 'selected' : '' ?>
              data-name="<?= e($r['name']) ?>">
              <?= e($r['name']) ?><?= $r['phone'] ? ' (' . e($r['phone']) . ')' : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary btn-block" id="assign-rider-btn" onclick="assignRider()">
          <i class="fas fa-motorcycle"></i>
          <?= $order['rider_id'] ? 'Re-assign Rider' : 'Assign Rider' ?>
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Order Info -->
    <div class="admin-card mb-16">
      <div class="admin-card-header"><span class="admin-card-title">Order Info</span></div>
      <div class="admin-card-body" style="font-size:.86rem">
        <table style="width:100%">
          <tr>
            <td style="padding:6px 0;color:var(--text-muted)">Order #</td>
            <td style="font-weight:600;color:var(--primary)"><?= e($order['order_number']) ?></td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:var(--text-muted)">Status</td>
            <td><?= status_badge($order['status']) ?></td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:var(--text-muted)">Payment</td>
            <td><?= ucfirst(str_replace('_',' ',$order['payment_method'] ?? '')) ?></td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:var(--text-muted)">Pay Status</td>
            <td><?= status_badge($order['payment_status']) ?></td>
          </tr>
          <tr>
            <td style="padding:6px 0;color:var(--text-muted)">Total</td>
            <td style="font-weight:700"><?= currency($order['total_amount']) ?></td>
          </tr>
          <?php if ($order['rider_name']): ?>
          <tr>
            <td style="padding:6px 0;color:var(--text-muted)">Rider</td>
            <td style="font-weight:600">
              <i class="fas fa-motorcycle" style="color:#f97316;font-size:.8rem"></i>
              <?= e($order['rider_name']) ?>
            </td>
          </tr>
          <?php if ($order['rider_phone']): ?>
          <tr>
            <td style="padding:6px 0;color:var(--text-muted)">Rider Phone</td>
            <td><?= e($order['rider_phone']) ?></td>
          </tr>
          <?php endif; ?>
          <?php endif; ?>
        </table>
      </div>
    </div>

    <!-- Customer Info -->
    <div class="admin-card">
      <div class="admin-card-header"><span class="admin-card-title">Customer</span></div>
      <div class="admin-card-body" style="font-size:.86rem">
        <div style="font-weight:600"><?= e($order['customer']) ?></div>
        <div style="color:var(--text-muted)"><?= e($order['customer_email']) ?></div>
        <?php if ($order['customer_phone']): ?>
        <div style="color:var(--text-muted)"><?= e($order['customer_phone']) ?></div>
        <?php endif; ?>
        <a href="<?= SITE_URL ?>/admin/customers.php?q=<?= urlencode($order['customer_email']) ?>"
           class="btn btn-sm btn-outline mt-8">
          <i class="fas fa-user"></i> View Profile
        </a>
      </div>
    </div>
  </div>
</div>

<?php if ($can_assign && !empty($riders_list)): ?>
<script>
function assignRider() {
  const select  = document.getElementById('assign-rider-select');
  const btn     = document.getElementById('assign-rider-btn');
  const msgEl   = document.getElementById('assign-msg');
  const riderId = select?.value;

  if (!riderId) {
    msgEl.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Please select a rider.</div>';
    msgEl.scrollIntoView({ behavior: 'smooth' });
    return;
  }

  btn.disabled = true;
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning…';

  const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';

  fetch(window.SITE_URL + '/ajax/riders_admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action:     'assign',
      order_id:   '<?= $id ?>',
      rider_id:   riderId,
      csrf_token: csrf
    }).toString()
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      msgEl.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
      setTimeout(() => window.location.reload(), 1400);
    } else {
      msgEl.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
      btn.disabled = false;
      btn.innerHTML = orig;
    }
    msgEl.scrollIntoView({ behavior: 'smooth' });
  })
  .catch(() => {
    msgEl.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
    btn.disabled = false;
    btn.innerHTML = orig;
  });
}

// Auto-scroll to assign section if URL has #assign-rider
if (window.location.hash === '#assign-rider') {
  document.getElementById('assign-rider')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
