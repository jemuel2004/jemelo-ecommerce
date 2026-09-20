<?php
require_once __DIR__ . '/auth.php';   // require_once is idempotent — safe to call multiple times
require_admin_panel();
require_once __DIR__ . '/functions.php';
$page_title  = $page_title  ?? 'Dashboard';
$active_menu = $active_menu ?? '';

$db_admin      = getDB();
$pending_count = $db_admin->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$chat_unread   = unread_chat_count($_SESSION['user_id'], 'admin');
$notif_count_a = unread_notif_count($_SESSION['user_id']);

// Fetch avatar once — used in both sidebar and topbar
$admin_av = $db_admin->prepare("SELECT avatar FROM users WHERE id=?");
$admin_av->execute([$_SESSION['user_id']]);
$admin_avatar = $admin_av->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title><?= e($page_title) ?> — <?= SITE_NAME ?> Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>window.SITE_URL = '<?= SITE_URL ?>';</script>
</head>
<body>

<div id="sidebar-overlay" class="sidebar-overlay"></div>

<div class="admin-layout">

<!-- ── Sidebar ─────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div><div class="logo-text">Jeme<span>lo</span></div></div>
    <span class="admin-tag"><?= $_SESSION['user_role'] === 'admin' ? 'Admin' : 'Staff' ?></span>
  </div>

  <nav class="sidebar-nav">
    <p class="sidebar-section-label">Main</p>
    <a href="<?= SITE_URL ?>/admin/index.php"
       class="sidebar-link <?= $active_menu === 'dashboard' ? 'active' : '' ?>">
      <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>

    <p class="sidebar-section-label">Catalog</p>
    <a href="<?= SITE_URL ?>/admin/products.php"
       class="sidebar-link <?= $active_menu === 'products' ? 'active' : '' ?>">
      <i class="fas fa-box-open"></i> Products
    </a>
    <a href="<?= SITE_URL ?>/admin/categories.php"
       class="sidebar-link <?= $active_menu === 'categories' ? 'active' : '' ?>">
      <i class="fas fa-tags"></i> Categories
    </a>

    <p class="sidebar-section-label">Sales</p>
    <a href="<?= SITE_URL ?>/admin/orders.php"
       class="sidebar-link <?= $active_menu === 'orders' ? 'active' : '' ?>">
      <i class="fas fa-shopping-bag"></i> Orders
      <?php if ($pending_count > 0): ?>
      <span class="badge badge-warning"><?= $pending_count ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= SITE_URL ?>/admin/customers.php"
       class="sidebar-link <?= $active_menu === 'customers' ? 'active' : '' ?>">
      <i class="fas fa-users"></i> Customers
    </a>
    <?php if ($_SESSION['user_role'] === 'admin'): ?>
    <a href="<?= SITE_URL ?>/admin/staff.php"
       class="sidebar-link <?= $active_menu === 'staff' ? 'active' : '' ?>">
      <i class="fas fa-user-tie"></i> Staff
    </a>
    <a href="<?= SITE_URL ?>/admin/riders.php"
       class="sidebar-link <?= $active_menu === 'riders' ? 'active' : '' ?>">
      <i class="fas fa-motorcycle"></i> Riders
    </a>
    <a href="<?= SITE_URL ?>/admin/reports.php"
       class="sidebar-link <?= $active_menu === 'reports' ? 'active' : '' ?>">
      <i class="fas fa-chart-bar"></i> Reports
    </a>
    <?php endif; ?>
    <a href="<?= SITE_URL ?>/admin/chat.php"
       class="sidebar-link <?= $active_menu === 'chat' ? 'active' : '' ?>">
      <i class="fas fa-comments"></i> Chat Support
      <?php if ($chat_unread > 0): ?>
      <span class="badge badge-danger"><?= $chat_unread ?></span>
      <?php endif; ?>
    </a>

    <p class="sidebar-section-label">Settings</p>
    <a href="<?= SITE_URL ?>/admin/profile.php"
       class="sidebar-link <?= $active_menu === 'profile' ? 'active' : '' ?>">
      <i class="fas fa-user-cog"></i> My Profile
    </a>
    <a href="<?= SITE_URL ?>/customer/index.php" class="sidebar-link">
      <i class="fas fa-store"></i> View Store
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-avatar">
        <img src="<?= avatar_url($admin_avatar) ?>" alt="avatar" class="avatar-img">
      </div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= e($_SESSION['user_name']) ?></div>
        <div class="sidebar-user-role"><?= e($_SESSION['user_role']) ?></div>
      </div>
      <a href="<?= SITE_URL ?>/logout.php" class="sidebar-logout" title="Sign out">
        <i class="fas fa-sign-out-alt"></i>
      </a>
    </div>
  </div>
</aside>

<!-- ── Main ──────────────────────────────────────────────── -->
<main class="admin-main">
  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-left">
      <button class="topbar-menu-btn" id="topbar-menu-btn">
        <i class="fas fa-bars"></i>
      </button>
      <span class="topbar-title"><?= e($page_title) ?></span>
    </div>
    <div class="topbar-right">
      <span class="topbar-time" id="topbar-clock"></span>

      <!-- Notification Bell -->
      <div class="notif-bell-wrap" id="notif-wrap-admin">
        <button class="notif-bell-btn" id="notif-bell-admin" title="Notifications" aria-label="Notifications">
          <i class="fas fa-bell"></i>
          <?php if ($notif_count_a > 0): ?>
          <span class="notif-badge" id="notif-badge-admin"><?= $notif_count_a > 99 ? '99+' : $notif_count_a ?></span>
          <?php else: ?>
          <span class="notif-badge" id="notif-badge-admin" style="display:none">0</span>
          <?php endif; ?>
        </button>
        <div class="notif-dropdown" id="notif-dropdown-admin">
          <div class="notif-dropdown-header">
            <span class="notif-dropdown-title"><i class="fas fa-bell"></i> Notifications</span>
            <button class="notif-mark-all" id="notif-mark-all-admin">Mark all read</button>
          </div>
          <div class="notif-list" id="notif-list-admin">
            <div class="notif-skeleton"><div class="notif-skeleton-icon"></div><div class="notif-skeleton-lines"><span></span><span></span></div></div>
            <div class="notif-skeleton"><div class="notif-skeleton-icon"></div><div class="notif-skeleton-lines"><span></span><span></span></div></div>
          </div>
          <div class="notif-dropdown-footer">
            <a href="<?= SITE_URL ?>/admin/notifications.php">View all notifications</a>
          </div>
        </div>
      </div>

      <div class="dropdown">
        <div class="topbar-avatar" data-dropdown-toggle="admin-user-menu">
          <img src="<?= avatar_url($admin_avatar) ?>" alt="avatar" class="avatar-img">
        </div>
        <div class="dropdown-menu" id="admin-user-menu">
          <a href="<?= SITE_URL ?>/admin/profile.php"><i class="fas fa-user-cog"></i> Profile</a>
          <a href="<?= SITE_URL ?>/customer/index.php"><i class="fas fa-store"></i> View Store</a>
          <div class="dropdown-divider"></div>
          <a href="<?= SITE_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
        </div>
      </div>
    </div>
  </div>

  <div class="admin-content">
