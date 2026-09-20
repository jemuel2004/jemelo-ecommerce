<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

// Rider-only endpoint
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rider') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$rid    = (int)$_SESSION['user_id'];
$db     = getDB();
$action = $_REQUEST['action'] ?? '';

// ── Poll: count active deliveries ────────────────────────
if ($action === 'poll_new') {
    $cntSt = $db->prepare("SELECT COUNT(*) FROM orders WHERE rider_id=? AND status IN ('processing','picked_up','out_for_delivery')");
    $cntSt->execute([$rid]);
    $cnt = (int)$cntSt->fetchColumn();
    echo json_encode(['success' => true, 'count' => $cnt]);
    exit;
}

// ── All POST actions require CSRF ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method.']);
    exit;
}

verify_csrf();

// ── Update delivery status ───────────────────────────────
if ($action === 'update_status') {
    $orderId   = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');

    $allowed = ['picked_up', 'out_for_delivery', 'delivered', 'failed'];
    if (!$orderId || !in_array($newStatus, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    // Verify this order belongs to this rider
    $orderSt = $db->prepare("SELECT o.*, u.id AS customer_id FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=? AND o.rider_id=?");
    $orderSt->execute([$orderId, $rid]);
    $order = $orderSt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found or not assigned to you.']);
        exit;
    }

    // Valid status transitions
    $transitions = [
        'processing'       => ['picked_up'],
        'picked_up'        => ['out_for_delivery'],
        'out_for_delivery' => ['delivered', 'failed'],
    ];
    $currentStatus = $order['status'];
    if (!isset($transitions[$currentStatus]) || !in_array($newStatus, $transitions[$currentStatus])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status transition.']);
        exit;
    }

    // Update orders table
    $payStatus = $order['payment_status'];
    if ($newStatus === 'delivered' && in_array($order['payment_method'], ['cod', 'cash_on_delivery'])) {
        $payStatus = 'paid';
    }

    $db->prepare("UPDATE orders SET status=?, payment_status=?, delivery_notes=? WHERE id=?")
       ->execute([$newStatus, $payStatus, $notes ?: null, $orderId]);

    // Update deliveries table with timestamps
    $tsCol = match($newStatus) {
        'picked_up'        => 'picked_up_at',
        'out_for_delivery' => 'out_for_delivery_at',
        'delivered'        => 'delivered_at',
        default            => null,
    };

    if ($tsCol) {
        $db->prepare("UPDATE deliveries SET status=?, $tsCol=NOW(), notes=? WHERE order_id=? AND rider_id=?")
           ->execute([$newStatus, $notes ?: null, $orderId, $rid]);
    } else {
        $db->prepare("UPDATE deliveries SET status=?, notes=? WHERE order_id=? AND rider_id=?")
           ->execute([$newStatus, $notes ?: null, $orderId, $rid]);
    }

    // Notify customer
    $labels = [
        'picked_up'        => ['Picked Up',         'order_status',      'Your order has been picked up by the rider.'],
        'out_for_delivery' => ['Out for Delivery',   'order_status',      'Your order is on its way!'],
        'delivered'        => ['Delivered',          'delivery_delivered', 'Your order has been delivered. Thank you for shopping!'],
        'failed'           => ['Delivery Failed',    'delivery_failed',   'Unfortunately, delivery of your order failed. We will contact you shortly.'],
    ];
    [$labelStr, $notifType, $notifMsg] = $labels[$newStatus];

    notify(
        (int)$order['customer_id'],
        $notifType,
        'Order ' . $labelStr . ': #' . $order['order_number'],
        $notifMsg,
        SITE_URL . '/customer/order_detail.php?id=' . $orderId
    );

    // Notify admins of delivery status
    notify_admins(
        'delivery_update',
        'Delivery Update: #' . $order['order_number'],
        'Status changed to ' . ucfirst(str_replace('_', ' ', $newStatus)) . ' by rider.',
        SITE_URL . '/admin/order_detail.php?id=' . $orderId
    );

    echo json_encode([
        'success' => true,
        'message' => 'Delivery status updated to ' . ucfirst(str_replace('_', ' ', $newStatus)) . '.',
        'new_status' => $newStatus,
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
