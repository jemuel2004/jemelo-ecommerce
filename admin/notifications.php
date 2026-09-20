<?php
$page_title  = 'Notifications';
$active_menu = '';
require_once __DIR__ . '/../includes/admin_header.php';
$db = getDB();

// Mark all read on open
$db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$total   = (int)$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?")->execute([$_SESSION['user_id']]) ? $db->query("SELECT COUNT(*) FROM notifications WHERE user_id=" . (int)$_SESSION['user_id'])->fetchColumn() : 0;
$pages   = max(1, (int)ceil($total / $perPage));
$offset  = ($page - 1) * $perPage;

$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$notifs->execute([$_SESSION['user_id'], $perPage, $offset]);
$notifications = $notifs->fetchAll();
?>

<div class="admin-page-header">
  <div class="admin-page-title">Notifications</div>
</div>

<?php if (empty($notifications)): ?>
<div class="admin-card" style="text-align:center;padding:60px 20px">
  <i class="fas fa-bell-slash" style="font-size:3rem;color:var(--text-light);margin-bottom:16px;display:block"></i>
  <h3 style="margin-bottom:8px">No notifications yet</h3>
  <p style="color:var(--text-muted);font-size:.9rem">Activity from orders, customers, chat, and products will appear here.</p>
</div>
<?php else: ?>
<div class="admin-card" style="overflow:hidden">
  <div class="admin-card-header" style="display:flex;justify-content:space-between;align-items:center">
    <span class="admin-card-title">All Notifications (<?= $total ?>)</span>
  </div>
  <?php foreach ($notifications as $n):
    $meta = notif_meta($n['type']);
    $link = $n['link'] ?: '#';
  ?>
  <a href="<?= e($link) ?>" style="display:flex;align-items:flex-start;gap:14px;padding:14px 20px;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text);transition:background .15s" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
    <div style="width:40px;height:40px;border-radius:50%;background:<?= $meta['color'] ?>20;color:<?= $meta['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;margin-top:1px">
      <i class="fas <?= $meta['icon'] ?>"></i>
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-weight:600;font-size:.88rem;margin-bottom:2px"><?= e($n['title']) ?></div>
      <?php if ($n['message']): ?>
      <div style="font-size:.8rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($n['message']) ?></div>
      <?php endif; ?>
      <div style="font-size:.72rem;color:var(--text-light);margin-top:3px">
        <i class="fas fa-clock"></i> <?= time_ago($n['created_at']) ?> &mdash; <?= date('M j, Y g:i A', strtotime($n['created_at'])) ?>
      </div>
    </div>
    <span class="badge <?= $n['is_read'] ? 'badge-secondary' : 'badge-primary' ?>" style="font-size:.65rem;flex-shrink:0;align-self:center">
      <?= $n['is_read'] ? 'Read' : 'New' ?>
    </span>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($pages > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:20px">
  <?php for ($p = 1; $p <= $pages; $p++): ?>
  <a href="?page=<?= $p ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
