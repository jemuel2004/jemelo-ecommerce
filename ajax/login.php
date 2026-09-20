<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

verify_csrf();

$result = attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '');

if ($result['success']) {
    $role = $result['role'];

    // Remember Me — only for customers/riders (not admin/staff for security)
    if (!empty($_POST['remember']) && in_array($role, ['customer', 'rider'])) {
        create_remember_token((int)$_SESSION['user_id'], 30);
    }

    if (in_array($role, ['admin', 'staff'])) {
        $redirect = SITE_URL . '/admin/index.php';
    } elseif ($role === 'rider') {
        $redirect = SITE_URL . '/rider/index.php';
    } else {
        $redirect = SITE_URL . '/customer/index.php';
        $return   = $_POST['return'] ?? '';
        if ($return) {
            $ret    = parse_url($return);
            $site   = parse_url(SITE_URL);
            // Only follow same-host return URLs to prevent open-redirect
            if (isset($ret['host']) && $ret['host'] === ($site['host'] ?? '')) {
                $redirect = $return;
            }
        }
    }

    echo json_encode([
        'success'  => true,
        'redirect' => $redirect,
        'role'     => $role,
        'name'     => $_SESSION['user_name'],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message']]);
}
