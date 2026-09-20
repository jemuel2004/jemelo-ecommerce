<?php
require_once __DIR__ . '/auth.php';
require_rider();
require_once __DIR__ . '/functions.php';

$page_title  = $page_title  ?? 'Rider Portal';
$active_menu = $active_menu ?? '';

$db_rider    = getDB();
$rider_info  = $db_rider->prepare("SELECT avatar, name FROM users WHERE id=?");
$rider_info->execute([$_SESSION['user_id']]);
$rider_row   = $rider_info->fetch();
$rider_avatar= $rider_row['avatar'] ?? null;

$pdSt = $db_rider->prepare("SELECT COUNT(*) FROM orders WHERE rider_id=? AND status IN ('processing','picked_up','out_for_delivery')");
$pdSt->execute([$_SESSION['user_id']]);
$pending_del = (int)$pdSt->fetchColumn();

$notif_count = unread_notif_count($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title><?= e($page_title) ?> — <?= SITE_NAME ?> Rider</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/rider.css">
<script>window.SITE_URL = '<?= SITE_URL ?>';</script>
</head>
<body>

<div id="rider-sidebar-overlay" class="rider-sidebar-overlay"></div>

<div class="rider-layout">

<!-- ── Sidebar ─────────────────────────────────────────── -->
<aside class="rider-sidebar" id="rider-sidebar">
  <div class="rider-brand">
    <div class="logo-text">Jeme<span>lo</span></div>
    <span class="rider-tag">Rider</span>
  </div>

  <nav class="rider-nav">
    <p class="rider-section-label">Main</p>
    <a href="<?= SITE_URL ?>/rider/index.php"
       class="rider-nav-link <?= $active_menu === 'dashboard' ? 'active' : '' ?>">
      <i class="fas fa-tachometer-alt"></i> Dashboard
      <?php if ($pending_del > 0): ?>
      <span class="badge badge-warning" id="new-delivery-badge" data-count="<?= $pending_del ?>"><?= $pending_del ?></span>
      <?php else: ?>
      <span class="badge badge-warning" id="new-delivery-badge" data-count="0" style="display:none">0</span>
      <?php endif; ?>
    </a>

    <p class="rider-section-label">Deliveries</p>
    <a href="<?= SITE_URL ?>/rider/deliveries.php"
       class="rider-nav-link <?= $active_menu === 'deliveries' ? 'active' : '' ?>">
      <i class="fas fa-motorcycle"></i> My Deliveries
    </a>
    <a href="<?= SITE_URL ?>/rider/deliveries.php?filter=delivered"
       class="rider-nav-link <?= $active_menu === 'history' ? 'active' : '' ?>">
      <i class="fas fa-history"></i> Delivery History
    </a>

    <p class="rider-section-label">Account</p>
    <a href="<?= SITE_URL ?>/rider/profile.php"
       class="rider-nav-link <?= $active_menu === 'profile' ? 'active' : '' ?>">
      <i class="fas fa-user-cog"></i> My Profile
    </a>
    <a href="<?= SITE_URL ?>/customer/index.php" class="rider-nav-link">
      <i class="fas fa-store"></i> View Store
    </a>
  </nav>

  <div class="rider-sidebar-footer">
    <div class="rider-sidebar-user">
      <div class="rider-sidebar-avatar">
        <img src="<?= avatar_url($rider_avatar) ?>" alt="avatar">
      </div>
      <div class="rider-sidebar-info">
        <div class="rider-sidebar-name"><?= e($_SESSION['user_name']) ?></div>
        <div class="rider-sidebar-role">Rider</div>
      </div>
      <a href="<?= SITE_URL ?>/logout.php" class="rider-sidebar-logout" title="Sign out">
        <i class="fas fa-sign-out-alt"></i>
      </a>
    </div>
  </div>
</aside>

<!-- ── Main ──────────────────────────────────────────────── -->
<main class="rider-main">
  <!-- Topbar -->
  <div class="rider-topbar">
    <div class="rider-topbar-left">
      <button class="rider-topbar-menu" id="rider-menu-btn"><i class="fas fa-bars"></i></button>
      <span class="rider-topbar-title"><?= e($page_title) ?></span>
    </div>
    <div class="rider-topbar-right">
      <div class="rider-status-dot">Online</div>
      <span style="font-size:.78rem;color:var(--text-muted)" id="rider-clock"></span>

      <!-- Notifications -->
      <div class="notif-bell-wrap">
        <button class="notif-bell-btn" id="rider-notif-bell" aria-label="Notifications">
          <i class="fas fa-bell"></i>
          <?php if ($notif_count > 0): ?>
          <span class="notif-badge" id="rider-notif-badge"><?= $notif_count > 99 ? '99+' : $notif_count ?></span>
          <?php else: ?>
          <span class="notif-badge" id="rider-notif-badge" style="display:none">0</span>
          <?php endif; ?>
        </button>
        <div class="notif-dropdown" id="rider-notif-drop">
          <div class="notif-dropdown-header">
            <span class="notif-dropdown-title"><i class="fas fa-bell"></i> Notifications</span>
            <button class="notif-mark-all" id="rider-mark-all">Mark all read</button>
          </div>
          <div class="notif-list" id="rider-notif-list">
            <div class="notif-empty" style="padding:20px">Loading…</div>
          </div>
          <div class="notif-dropdown-footer">
            <a href="<?= SITE_URL ?>/rider/index.php">Back to Dashboard</a>
          </div>
        </div>
      </div>

      <!-- User dropdown -->
      <div class="dropdown">
        <button style="background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:8px"
                data-dropdown="rider-user-menu">
          <img src="<?= avatar_url($rider_avatar) ?>" alt="avatar"
               style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid var(--rider-primary)">
        </button>
        <div class="dropdown-menu" id="rider-user-menu">
          <a href="<?= SITE_URL ?>/rider/profile.php"><i class="fas fa-user-cog"></i> Profile</a>
          <a href="<?= SITE_URL ?>/customer/index.php"><i class="fas fa-store"></i> View Store</a>
          <div class="dropdown-divider"></div>
          <a href="<?= SITE_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
        </div>
      </div>
    </div>
  </div>

  <div class="rider-content">
