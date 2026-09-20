<?php
require_once __DIR__ . '/includes/auth.php';
if (is_admin()) {
    redirect(SITE_URL . '/admin/index.php');
}
redirect(SITE_URL . '/customer/index.php');
