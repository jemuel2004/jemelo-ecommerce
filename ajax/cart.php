<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to manage your cart.', 'cart_count' => 0]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// CSRF check
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Security error. Please refresh.']);
    exit;
}

$db         = getDB();
$action     = $_POST['action']     ?? '';
$product_id = (int)($_POST['product_id'] ?? 0);
$quantity   = max(1, (int)($_POST['quantity'] ?? 1));
$user_id    = (int)$_SESSION['user_id'];

function cart_total_count(PDO $db, int $uid): int {
    $st = $db->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id=?');
    $st->execute([$uid]);
    return (int)$st->fetchColumn();
}

if (!$product_id && $action !== 'clear') {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

switch ($action) {
    case 'add':
        // Validate product exists, is active, has stock
        $prod = $db->prepare("SELECT id, name, stock, COALESCE(sale_price,price) AS price FROM products WHERE id=? AND status='active'");
        $prod->execute([$product_id]);
        $product = $prod->fetch();

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not available.']);
            exit;
        }

        // Check current qty in cart
        $in_cart = $db->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=?");
        $in_cart->execute([$user_id, $product_id]);
        $current_qty = (int)($in_cart->fetchColumn() ?: 0);

        $new_qty = $current_qty + $quantity;
        if ($new_qty > $product['stock']) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock. Available: ' . $product['stock']]);
            exit;
        }

        $upsert = $db->prepare("INSERT INTO cart (user_id,product_id,quantity) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        $upsert->execute([$user_id, $product_id, $quantity, $quantity]);

        echo json_encode([
            'success'    => true,
            'message'    => '"' . $product['name'] . '" added to cart!',
            'cart_count' => cart_total_count($db, $user_id),
        ]);
        break;

    case 'update':
        $prod = $db->prepare("SELECT stock FROM products WHERE id=? AND status='active'");
        $prod->execute([$product_id]);
        $product = $prod->fetch();

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit;
        }
        if ($quantity > $product['stock']) {
            echo json_encode(['success' => false, 'message' => 'Requested quantity exceeds stock.']);
            exit;
        }

        $upd = $db->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=?");
        $upd->execute([$quantity, $user_id, $product_id]);

        // Get item subtotal for frontend update
        $price_st = $db->prepare("SELECT COALESCE(sale_price,price) FROM products WHERE id=?");
        $price_st->execute([$product_id]);
        $unit_price = (float)$price_st->fetchColumn();

        echo json_encode([
            'success'        => true,
            'message'        => 'Cart updated.',
            'cart_count'     => cart_total_count($db, $user_id),
            'item_subtotal'  => currency($unit_price * $quantity),
        ]);
        break;

    case 'remove':
        $db->prepare("DELETE FROM cart WHERE user_id=? AND product_id=?")->execute([$user_id, $product_id]);
        echo json_encode([
            'success'    => true,
            'message'    => 'Item removed from cart.',
            'cart_count' => cart_total_count($db, $user_id),
        ]);
        break;

    case 'clear':
        $db->prepare("DELETE FROM cart WHERE user_id=?")->execute([$user_id]);
        echo json_encode(['success' => true, 'message' => 'Cart cleared.', 'cart_count' => 0]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
