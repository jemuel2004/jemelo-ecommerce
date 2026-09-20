<?php
require_once __DIR__ . '/../includes/auth.php';
require_customer();
$page_title = 'Notifications';
$db = getDB();

// Mark all as read when page opens
$db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);

// Paginate
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$total   = (int)$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?")->execute([$_SESSION['user_id']]) ? $db->query("SELECT COUNT(*) FROM notifications WHERE user_id=" . (int)$_SESSION['user_id'])->fetchColumn() : 0;
$pages   = max(1, (int)ceil($total / $perPage));
$offset  = ($page - 1) * $perPage;

$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$notifs->execute([$_SESSION['user_id'], $perPage, $offset]);
$notifications = $notifs->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1><i class="fas fa-bell" style="color:var(--primary)"></i> Notifications</h1>
    <p>All your recent alerts and updates</p>
  </div>
</div>

<div class="container" style="padding-bottom:60px;max-width:720px">
  <?php if (empty($notifications)): ?>
  <div class="card" style="text-align:center;padding:60px 20px">
    <i class="fas fa-bell-slash" style="font-size:3rem;color:var(--text-light);margin-bottom:16px;display:block"></i>
    <h3 style="margin-bottom:8px">No notifications yet</h3>
    <p style="color:var(--text-muted);font-size:.9rem">You'll be notified about orders, promos, and more.</p>
    <a href="<?= SITE_URL ?>/customer/index.php" class="btn btn-primary" style="margin-top:16px">Browse Products</a>
  </div>
  <?php else: ?>

  <div class="card" style="overflow:hidden">
    <?php foreach ($notifications as $n):
      $meta = notif_meta($n['type']);
      $link = $n['link'] ?: '#';
    ?>
    <a href="<?= e($link) ?>" class="notif-page-item">
      <div class="notif-icon" style="background:<?= $meta['color'] ?>20;color:<?= $meta['color'] ?>;width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">
        <i class="fas <?= $meta['icon'] ?>"></i>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:.88rem;color:var(--text);margin-bottom:2px"><?= e($n['title']) ?></div>
        <?php if ($n['message']): ?>
        <div style="font-size:.8rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($n['message']) ?></div>
        <?php endif; ?>
        <div style="font-size:.72rem;color:var(--text-light);margin-top:3px"><i class="fas fa-clock"></i> <?= time_ago($n['created_at']) ?> &mdash; <?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></div>
      </div>
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
</div>

<style>
.notif-page-item {
  display:flex;align-items:flex-start;gap:14px;padding:16px 20px;
  border-bottom:1px solid var(--border);text-decoration:none;color:var(--text);
  transition:background .15s;
}
.notif-page-item:last-child{border-bottom:none}
.notif-page-item:hover{background:var(--bg)}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
