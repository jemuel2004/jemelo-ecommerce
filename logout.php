<?php
require_once __DIR__ . '/includes/auth.php';
$was_rider = ($_SESSION['user_role'] ?? '') === 'rider';
destroy_remember_token();
$_SESSION  = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
redirect($was_rider ? SITE_URL . '/rider/login.php' : SITE_URL . '/customer/index.php?modal=login');
