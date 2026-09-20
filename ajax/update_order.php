<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

// Admin and staff can update order status
if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Security error.']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$status   = $_POST['status'] ?? '';
$allowed  = ['pending', 'processing', 'picked_up', 'out_for_delivery', 'shipped', 'delivered', 'failed', 'cancelled'];

if (!$order_id || !in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

$db = getDB();

$orderSt = $db->prepare("SELECT user_id, order_number, total_amount, payment_method, payment_status FROM orders WHERE id = ?");
$orderSt->execute([$order_id]);
$orderRow = $orderSt->fetch();

if (!$orderRow) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

// Auto-pay COD when admin marks as delivered
$newPayStatus = $orderRow['payment_status'];
if ($status === 'delivered' && in_array($orderRow['payment_method'], ['cod', 'cash_on_delivery']) && $orderRow['payment_status'] !== 'paid') {
    $newPayStatus = 'paid';
}

$db->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE id = ?")
   ->execute([$status, $newPayStatus, $order_id]);

// Sync delivery record if it exists
if ($status === 'delivered') {
    $db->prepare("UPDATE deliveries SET status='delivered', delivered_at=COALESCE(delivered_at, NOW()) WHERE order_id=?")->execute([$order_id]);
} elseif ($status === 'failed') {
    $db->prepare("UPDATE deliveries SET status='failed' WHERE order_id=?")->execute([$order_id]);
}

// Customer notification
$statusLabels = [
    'pending'          => 'Order Received',
    'processing'       => 'Order Being Processed',
    'picked_up'        => 'Order Picked Up by Rider',
    'out_for_delivery' => 'Order Out for Delivery',
    'shipped'          => 'Order Shipped',
    'delivered'        => 'Order Delivered',
    'failed'           => 'Delivery Failed',
    'cancelled'        => 'Order Cancelled',
];
$label     = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
$notifType = in_array($status, ['cancelled', 'failed']) ? 'order_cancelled' : 'order_status';
$notifMsg  = 'Your order #' . $orderRow['order_number'] . ' — ' . currency((float)$orderRow['total_amount']);

notify((int)$orderRow['user_id'], $notifType, $label, $notifMsg, SITE_URL . '/customer/order_detail.php?id=' . $order_id);

if (in_array($status, ['cancelled', 'failed'])) {
    notify_admins($notifType, 'Order ' . ucfirst($status) . ': #' . $orderRow['order_number'], $notifMsg, SITE_URL . '/admin/order_detail.php?id=' . $order_id);
}

echo json_encode(['success' => true, 'message' => 'Status updated to ' . ucfirst(str_replace('_', ' ', $status)) . '.']);
