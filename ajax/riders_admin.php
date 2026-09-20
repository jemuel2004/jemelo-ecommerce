<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Admin-only
if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin'])) {
    flash('rider_mgmt', 'Access denied.', 'danger');
    redirect(SITE_URL . '/admin/riders.php');
}

$db     = getDB();
$action = $_POST['action'] ?? '';

// The 'assign' action is called via AJAX — return JSON on auth/CSRF failure
if ($action === 'assign') {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh and try again.']);
        exit;
    }
} else {
    verify_csrf();
}

function rider_back(string $msg, string $type = 'success'): never {
    $_SESSION['flash']['rider_mgmt'] = ['msg' => $msg, 'type' => $type];
    redirect(SITE_URL . '/admin/riders.php');
}

// ── Add rider ─────────────────────────────────────────────
if ($action === 'add') {
    $first   = trim($_POST['first_name'] ?? '');
    $last    = trim($_POST['last_name']  ?? '');
    $email   = strtolower(trim($_POST['email'] ?? ''));
    $username= trim($_POST['username']   ?? '') ?: null;
    $phone   = trim($_POST['phone']      ?? '');
    $password= $_POST['password']        ?? '';
    $confirm = $_POST['confirm_pw']      ?? '';
    $status  = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    if (!$first || !$last)   rider_back('First and last name are required.', 'danger');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) rider_back('Valid email is required.', 'danger');
    if (strlen($password) < 8)    rider_back('Password must be at least 8 characters.', 'danger');
    if ($password !== $confirm)   rider_back('Passwords do not match.', 'danger');

    // Duplicate checks
    $dup = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $dup->execute([$email]);
    if ($dup->fetch()) rider_back('That email is already registered.', 'danger');

    if ($username) {
        if (strlen($username) < 3 || !preg_match('/^[\w.-]+$/', $username))
            rider_back('Username must be 3+ chars (letters, numbers, dots, underscores only).', 'danger');
        $udup = $db->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
        $udup->execute([$username]);
        if ($udup->fetch()) rider_back('Username is already taken.', 'danger');
    }

    $name   = trim("$first $last");
    $hash   = password_hash($password, PASSWORD_DEFAULT);
    $avatar = 'default.png';
    if (!empty($_FILES['avatar']['name'])) {
        $up = upload_image($_FILES['avatar'], 'avatars');
        if ($up) $avatar = $up;
    }

    $ins = $db->prepare(
        "INSERT INTO users (name, first_name, last_name, email, username, password, phone, avatar, role, status)
         VALUES (?,?,?,?,?,?,?,?,'rider',?)"
    );
    $ins->execute([$name, $first, $last, $email, $username, $hash, $phone, $avatar, $status]);

    notify_admins('new_customer', 'New Rider Registered', "$name has been added as a rider.", SITE_URL . '/admin/riders.php');

    rider_back("Rider $name added successfully!");
}

// ── Edit rider ─────────────────────────────────────────────
if ($action === 'edit') {
    $id      = (int)($_POST['id'] ?? 0);
    $first   = trim($_POST['first_name'] ?? '');
    $last    = trim($_POST['last_name']  ?? '');
    $email   = strtolower(trim($_POST['email'] ?? ''));
    $username= trim($_POST['username']   ?? '') ?: null;
    $phone   = trim($_POST['phone']      ?? '');
    $password= $_POST['password']        ?? '';
    $confirm = $_POST['confirm_pw']      ?? '';
    $status  = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    if (!$id) rider_back('Invalid rider.', 'danger');

    $cur = $db->prepare("SELECT * FROM users WHERE id=? AND role='rider'");
    $cur->execute([$id]);
    $cur = $cur->fetch();
    if (!$cur) rider_back('Rider not found.', 'danger');

    if (!$first || !$last) rider_back('First and last name are required.', 'danger');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) rider_back('Valid email is required.', 'danger');

    // Duplicate email check (exclude self)
    $dup = $db->prepare("SELECT id FROM users WHERE email=? AND id!=? LIMIT 1");
    $dup->execute([$email, $id]);
    if ($dup->fetch()) rider_back('Email is already in use.', 'danger');

    if ($username) {
        $udup = $db->prepare("SELECT id FROM users WHERE username=? AND id!=? LIMIT 1");
        $udup->execute([$username, $id]);
        if ($udup->fetch()) rider_back('Username is already taken.', 'danger');
    }

    $name   = trim("$first $last");
    $avatar = $cur['avatar'];

    if (!empty($_FILES['avatar']['name'])) {
        $up = upload_image($_FILES['avatar'], 'avatars');
        if ($up) {
            if ($avatar && $avatar !== 'default.png') @unlink(UPLOAD_PATH . 'avatars/' . $avatar);
            $avatar = $up;
        }
    }

    if ($password !== '') {
        if (strlen($password) < 8) rider_back('Password must be at least 8 characters.', 'danger');
        if ($password !== $confirm) rider_back('Passwords do not match.', 'danger');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET name=?,first_name=?,last_name=?,email=?,username=?,phone=?,avatar=?,password=?,status=? WHERE id=?")
           ->execute([$name,$first,$last,$email,$username,$phone,$avatar,$hash,$status,$id]);
    } else {
        $db->prepare("UPDATE users SET name=?,first_name=?,last_name=?,email=?,username=?,phone=?,avatar=?,status=? WHERE id=?")
           ->execute([$name,$first,$last,$email,$username,$phone,$avatar,$status,$id]);
    }

    rider_back("Rider $name updated successfully!");
}

// ── Toggle status ──────────────────────────────────────────
if ($action === 'toggle_status') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=? AND role='rider'")->execute([$id]);
    rider_back('Rider status updated.');
}

// ── Delete rider ───────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$_SESSION['user_id']) rider_back('Cannot delete your own account.', 'danger');
    $r = $db->prepare("SELECT avatar FROM users WHERE id=? AND role='rider'");
    $r->execute([$id]);
    $r = $r->fetch();
    if ($r) {
        if ($r['avatar'] && $r['avatar'] !== 'default.png') @unlink(UPLOAD_PATH . 'avatars/' . $r['avatar']);
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    }
    rider_back('Rider deleted successfully.');
}

// ── Assign rider to order ──────────────────────────────────
if ($action === 'assign') {
    $orderId  = (int)($_POST['order_id']  ?? 0);
    $riderId  = (int)($_POST['rider_id']  ?? 0);
    if (!$orderId || !$riderId) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    // Verify rider exists and is active
    $rSt = $db->prepare("SELECT id, name FROM users WHERE id=? AND role='rider' AND status='active'");
    $rSt->execute([$riderId]);
    $riderRow = $rSt->fetch();
    if (!$riderRow) {
        echo json_encode(['success' => false, 'message' => 'Rider not found or inactive.']);
        exit;
    }

    // Get order
    $oSt = $db->prepare("SELECT o.*, u.id AS customer_id FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?");
    $oSt->execute([$orderId]);
    $order = $oSt->fetch();
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    // Remove old delivery record if re-assigning
    $db->prepare("DELETE FROM deliveries WHERE order_id=?")->execute([$orderId]);

    // Assign rider
    $db->prepare("UPDATE orders SET rider_id=?, status='processing', payment_status=CASE WHEN payment_method='cod' THEN 'unpaid' ELSE payment_status END WHERE id=?")
       ->execute([$riderId, $orderId]);

    // Create delivery record
    $db->prepare("INSERT INTO deliveries (order_id, rider_id) VALUES (?,?)")->execute([$orderId, $riderId]);

    // Notify rider
    notify_rider(
        $riderId,
        'rider_assigned',
        'New Delivery Assigned: #' . $order['order_number'],
        'You have been assigned to deliver order #' . $order['order_number'] . '.',
        SITE_URL . '/rider/deliveries.php?id=' . $orderId
    );

    // Notify customer
    notify(
        (int)$order['customer_id'],
        'order_status',
        'Rider Assigned: #' . $order['order_number'],
        'A rider has been assigned to deliver your order. They will pick it up soon.',
        SITE_URL . '/customer/order_detail.php?id=' . $orderId
    );

    echo json_encode([
        'success'     => true,
        'message'     => 'Rider ' . $riderRow['name'] . ' assigned successfully!',
        'rider_name'  => $riderRow['name'],
    ]);
    exit;
}

rider_back('Unknown action.', 'danger');
