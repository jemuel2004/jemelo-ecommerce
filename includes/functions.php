<?php
require_once __DIR__ . '/../config/database.php';

// ── Output sanitization ──────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── CSRF ─────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

// ── Flash messages ───────────────────────────────────────────
function flash(string $key, string $msg = '', string $type = 'success'): ?array {
    if ($msg !== '') {
        $_SESSION['flash'][$key] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    if (isset($_SESSION['flash'][$key])) {
        $f = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $f;
    }
    return null;
}

function show_flash(string $key): void {
    $f = flash($key);
    if ($f) {
        echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
}

// ── Currency ─────────────────────────────────────────────────
function currency(float $amount): string {
    return '₱' . number_format($amount, 2);
}

// ── Avatar URL ───────────────────────────────────────────────
function avatar_url(?string $avatar): string {
    if ($avatar && $avatar !== 'default.png') {
        $path = UPLOAD_PATH . 'avatars/' . $avatar;
        if (file_exists($path)) {
            return UPLOAD_URL . 'avatars/' . rawurlencode($avatar);
        }
    }
    return SITE_URL . '/assets/images/default-avatar.svg';
}

// ── Unread chat messages count ───────────────────────────────
function unread_chat_count(int $userId, string $role): int {
    try {
        $db = getDB();
        if ($role === 'admin') {
            $st = $db->query("SELECT COUNT(*) FROM chat_messages WHERE sender_role='customer' AND is_read=0");
        } else {
            $st = $db->prepare("
                SELECT COUNT(*) FROM chat_messages cm
                JOIN chat_conversations cc ON cc.id = cm.conversation_id
                WHERE cc.customer_id = ? AND cm.sender_role = 'admin' AND cm.is_read = 0
            ");
            $st->execute([$userId]);
        }
        return (int)$st->fetchColumn();
    } catch (PDOException $e) {
        return 0; // chat tables not yet created
    }
}

// ── Product image URL ────────────────────────────────────────
function product_img(?string $img): string {
    if ($img && $img !== 'no-image.png') {
        $path = UPLOAD_PATH . 'products/' . $img;
        if (file_exists($path)) {
            return UPLOAD_URL . 'products/' . rawurlencode($img);
        }
    }
    return SITE_URL . '/assets/images/no-image.svg';
}

function category_img(?string $img): string {
    if ($img) {
        $path = UPLOAD_PATH . 'categories/' . $img;
        if (file_exists($path)) {
            return UPLOAD_URL . 'categories/' . rawurlencode($img);
        }
    }
    return SITE_URL . '/assets/images/no-image.svg';
}

// ── Image upload helper ───────────────────────────────────────
function upload_image(array $file, string $dir = 'products'): string|false {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
    if (!is_uploaded_file($file['tmp_name'])) return false;
    if ($file['size'] > 3 * 1024 * 1024) return false;

    // Validate real MIME type from file content (not spoofable browser-reported type)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);
    $mimeMap  = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($mimeMap[$realMime])) return false;

    $ext      = $mimeMap[$realMime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;   // crypto-random name
    $dest     = UPLOAD_PATH . $dir . '/' . $filename;

    if (!is_dir(UPLOAD_PATH . $dir)) mkdir(UPLOAD_PATH . $dir, 0755, true);
    return move_uploaded_file($file['tmp_name'], $dest) ? $filename : false;
}

// ── Order number generator ───────────────────────────────────
function generate_order_number(): string {
    return 'ORD-' . strtoupper(substr(uniqid(), -6)) . '-' . date('ymd');
}

// ── Pagination helper ────────────────────────────────────────
function paginate(int $total, int $perPage, int $current): array {
    $pages = (int) ceil($total / $perPage);
    return [
        'total'    => $total,
        'per_page' => $perPage,
        'current'  => max(1, min($current, $pages ?: 1)),
        'pages'    => $pages,
        'offset'   => ($current - 1) * $perPage,
    ];
}

// ── Cart count (for header badge) ───────────────────────────
function cart_count(): int {
    if (empty($_SESSION['user_id'])) return 0;
    $db  = getDB();
    $st  = $db->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id = ?');
    $st->execute([$_SESSION['user_id']]);
    return (int) $st->fetchColumn();
}

// ── Star rating HTML ─────────────────────────────────────────
function star_rating(float $avg, int $count = 0): string {
    $html = '<span class="stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($avg >= $i)             $html .= '<i class="fas fa-star"></i>';
        elseif ($avg >= $i - 0.5)  $html .= '<i class="fas fa-star-half-alt"></i>';
        else                        $html .= '<i class="far fa-star"></i>';
    }
    $html .= '</span>';
    if ($count > 0) $html .= ' <small class="text-muted">(' . $count . ')</small>';
    return $html;
}

// ── Status badge ────────────────────────────────────────────
function status_badge(?string $status): string {
    $status = $status ?? '';
    $map = [
        'pending'           => 'badge-warning',
        'processing'        => 'badge-info',
        'picked_up'         => 'badge-info',
        'out_for_delivery'  => 'badge-primary',
        'shipped'           => 'badge-primary',
        'delivered'         => 'badge-success',
        'failed'            => 'badge-danger',
        'cancelled'         => 'badge-danger',
        'paid'              => 'badge-success',
        'unpaid'            => 'badge-warning',
        'active'            => 'badge-success',
        'inactive'          => 'badge-secondary',
    ];
    $labels = [
        'picked_up'        => 'Picked Up',
        'out_for_delivery' => 'Out for Delivery',
        'unpaid'           => 'Unpaid',
    ];
    $cls   = $map[$status]    ?? 'badge-secondary';
    $label = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

// ── Delivery status badge (rider-side) ───────────────────────
function delivery_status_badge(string $status): string {
    $map = [
        'pending'           => ['badge-warning',  'Pending',           'fa-clock'],
        'picked_up'         => ['badge-info',     'Picked Up',         'fa-box'],
        'out_for_delivery'  => ['badge-primary',  'Out for Delivery',  'fa-motorcycle'],
        'delivered'         => ['badge-success',  'Delivered',         'fa-check-circle'],
        'failed'            => ['badge-danger',   'Failed',            'fa-times-circle'],
    ];
    [$cls, $label, $icon] = $map[$status] ?? ['badge-secondary', ucfirst($status), 'fa-circle'];
    return '<span class="badge ' . $cls . '"><i class="fas ' . $icon . '"></i> ' . $label . '</span>';
}

// ── Notify a specific rider ──────────────────────────────────
function notify_rider(int $riderId, string $type, string $title, string $message = '', string $link = ''): void {
    notify($riderId, $type, $title, $message, $link);
}

// ── Notify all active riders ─────────────────────────────────
function notify_all_riders(string $type, string $title, string $message = '', string $link = ''): void {
    try {
        $db    = getDB();
        $riders = $db->query("SELECT id FROM users WHERE role='rider' AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
        $st    = $db->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?,?,?,?,?)");
        foreach ($riders as $id) $st->execute([$id, $type, $title, $message, $link]);
    } catch (PDOException $e) {
        error_log('notify_all_riders() failed: ' . $e->getMessage());
    }
}

// ── Notifications ─────────────────────────────────────────────

/**
 * Create a notification for a single user.
 */
function notify(int $userId, string $type, string $title, string $message = '', string $link = ''): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)")
           ->execute([$userId, $type, $title, $message, $link]);
    } catch (PDOException $e) {
        error_log('notify() failed: ' . $e->getMessage());
    }
}

/**
 * Create a notification for every admin account.
 */
function notify_admins(string $type, string $title, string $message = '', string $link = ''): void {
    try {
        $db = getDB();
        $admins = $db->query("SELECT id FROM users WHERE role='admin' AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
        $st = $db->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        foreach ($admins as $id) {
            $st->execute([$id, $type, $title, $message, $link]);
        }
    } catch (PDOException $e) {
        error_log('notify_admins() failed: ' . $e->getMessage());
    }
}

/**
 * Create a notification for every active customer account.
 */
function notify_all_customers(string $type, string $title, string $message = '', string $link = ''): void {
    try {
        $db = getDB();
        $customers = $db->query("SELECT id FROM users WHERE role='customer' AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
        $st = $db->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        foreach ($customers as $id) {
            $st->execute([$id, $type, $title, $message, $link]);
        }
    } catch (PDOException $e) {
        error_log('notify_all_customers() failed: ' . $e->getMessage());
    }
}

/**
 * Fetch recent notifications for a user.
 */
function get_notifications(int $userId, int $limit = 20): array {
    try {
        $db = getDB();
        $st = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $st->execute([$userId, $limit]);
        return $st->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Count unread notifications for a user.
 */
function unread_notif_count(int $userId): int {
    try {
        $db = getDB();
        $st = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $st->execute([$userId]);
        return (int)$st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Notification type → icon + color map.
 */
function notif_meta(string $type): array {
    return match($type) {
        'new_order'      => ['icon' => 'fa-shopping-bag',    'color' => '#2563EB'],
        'order_status'   => ['icon' => 'fa-truck',           'color' => '#10B981'],
        'order_cancelled'=> ['icon' => 'fa-times-circle',    'color' => '#EF4444'],
        'new_message'    => ['icon' => 'fa-comments',        'color' => '#8B5CF6'],
        'chat_reply'     => ['icon' => 'fa-reply',           'color' => '#8B5CF6'],
        'new_customer'   => ['icon' => 'fa-user-plus',       'color' => '#0EA5E9'],
        'new_product'    => ['icon' => 'fa-box-open',        'color' => '#F59E0B'],
        'promo'          => ['icon' => 'fa-tag',             'color' => '#EF4444'],
        'low_stock'          => ['icon' => 'fa-exclamation-triangle', 'color' => '#F59E0B'],
        'announcement'       => ['icon' => 'fa-bullhorn',            'color' => '#2563EB'],
        'rider_assigned'     => ['icon' => 'fa-motorcycle',          'color' => '#F97316'],
        'delivery_update'    => ['icon' => 'fa-truck',               'color' => '#10B981'],
        'delivery_failed'    => ['icon' => 'fa-times-circle',        'color' => '#EF4444'],
        'delivery_delivered' => ['icon' => 'fa-check-circle',        'color' => '#10B981'],
        default              => ['icon' => 'fa-bell',                'color' => '#64748B'],
    };
}

// ── Redirect helper ──────────────────────────────────────────
function redirect(string $url): never {
    // Discard any buffered output so the Location header is not blocked
    while (ob_get_level() > 0) ob_end_clean();
    header('Location: ' . $url);
    exit;
}

// ── Time ago ─────────────────────────────────────────────────
function time_ago(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y) return $diff->y . 'y ago';
    if ($diff->m) return $diff->m . 'mo ago';
    if ($diff->d) return $diff->d . 'd ago';
    if ($diff->h) return $diff->h . 'h ago';
    if ($diff->i) return $diff->i . 'min ago';
    return 'just now';
}
