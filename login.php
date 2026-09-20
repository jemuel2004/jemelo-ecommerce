<?php
require_once __DIR__ . '/includes/auth.php';

// Already logged in — send to dashboard
if (is_logged_in()) {
    redirect(in_array($_SESSION['user_role'], ['admin','staff'])
        ? SITE_URL . '/admin/index.php'
        : SITE_URL . '/customer/index.php');
}

// Everything goes through the popup modal on the homepage
redirect(SITE_URL . '/customer/index.php?modal=login');
