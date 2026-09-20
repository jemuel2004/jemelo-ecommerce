<?php
/**
 * Jemelo — Full Setup & Reset Script
 * Visit: http://localhost/Ecommerce/setup.php
 * DELETE this file after setup is complete!
 */
$allowed = ['localhost', '127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed)) {
    http_response_code(403); die('Access denied.');
}

define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'ecommerce_db');
define('DB_CHARSET', 'utf8mb4');
define('SITE_URL',   'http://localhost/Ecommerce');
define('UPLOAD_PATH', __DIR__ . '/assets/uploads/');

$steps = [];

function ok($msg)  { global $steps; $steps[] = ['ok',   $msg]; }
function err($msg) { global $steps; $steps[] = ['err',  $msg]; }
function warn($msg){ global $steps; $steps[] = ['warn', $msg]; }

try {
    // ── 1. Connect & create database ─────────────────────────
    $pdo = new PDO("mysql:host=".DB_HOST.";charset=".DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `".DB_NAME."`");
    ok('Database ready: ' . DB_NAME);

    // ── 2. Create tables ─────────────────────────────────────
    $tables = [
    "CREATE TABLE IF NOT EXISTS `users` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name`        VARCHAR(100)  NOT NULL,
        `first_name`  VARCHAR(60)   NOT NULL DEFAULT '',
        `middle_name` VARCHAR(60)   NOT NULL DEFAULT '',
        `last_name`   VARCHAR(60)   NOT NULL DEFAULT '',
        `username`    VARCHAR(80)   DEFAULT NULL,
        `birthdate`   DATE          DEFAULT NULL,
        `email`       VARCHAR(150)  NOT NULL UNIQUE,
        `password`    VARCHAR(255)  NOT NULL,
        `role`        ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer',
        `phone`       VARCHAR(25),
        `address`     TEXT,
        `avatar`      VARCHAR(255) DEFAULT 'default.png',
        `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `categories` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name`       VARCHAR(100) NOT NULL UNIQUE,
        `description` TEXT,
        `image`      VARCHAR(255),
        `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `products` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `category_id` INT UNSIGNED,
        `name`        VARCHAR(200) NOT NULL,
        `description` TEXT,
        `price`       DECIMAL(10,2) NOT NULL,
        `sale_price`  DECIMAL(10,2) DEFAULT NULL,
        `stock`       INT NOT NULL DEFAULT 0,
        `image`       VARCHAR(255) DEFAULT 'no-image.png',
        `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `featured`    TINYINT(1) NOT NULL DEFAULT 0,
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `cart` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL,
        `product_id` INT UNSIGNED NOT NULL,
        `quantity`   INT NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cart (`user_id`,`product_id`),
        FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `orders` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`          INT UNSIGNED NOT NULL,
        `order_number`     VARCHAR(25) NOT NULL UNIQUE,
        `total_amount`     DECIMAL(10,2) NOT NULL,
        `status`           ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
        `shipping_name`    VARCHAR(100),
        `shipping_phone`   VARCHAR(25),
        `shipping_address` TEXT,
        `payment_method`   ENUM('cod','gcash','bank_transfer') NOT NULL DEFAULT 'cod',
        `payment_status`   ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
        `notes`            TEXT,
        `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `order_items` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `order_id`      INT UNSIGNED NOT NULL,
        `product_id`    INT UNSIGNED DEFAULT NULL,
        `product_name`  VARCHAR(200) NOT NULL,
        `product_image` VARCHAR(255),
        `price`         DECIMAL(10,2) NOT NULL,
        `quantity`      INT NOT NULL,
        `subtotal`      DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)   ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `reviews` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL,
        `product_id` INT UNSIGNED NOT NULL,
        `order_id`   INT UNSIGNED DEFAULT NULL,
        `rating`     TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
        `comment`    TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_review (`user_id`,`product_id`),
        FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `chat_conversations` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `customer_id` INT UNSIGNED NOT NULL,
        `subject`     VARCHAR(200) NOT NULL,
        `status`      ENUM('open','closed') NOT NULL DEFAULT 'open',
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `chat_messages` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `conversation_id` INT UNSIGNED NOT NULL,
        `sender_id`       INT UNSIGNED NOT NULL,
        `sender_role`     ENUM('admin','customer') NOT NULL,
        `message`         TEXT NOT NULL,
        `is_read`         TINYINT(1) NOT NULL DEFAULT 0,
        `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`sender_id`)       REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `notifications` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL,
        `type`       VARCHAR(50)  NOT NULL,
        `title`      VARCHAR(200) NOT NULL,
        `message`    TEXT,
        `link`       VARCHAR(255),
        `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_read (`user_id`, `is_read`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    ok('All tables created/verified (users, categories, products, cart, orders, order_items, reviews, chat_conversations, chat_messages, notifications)');

    // ── 2b. Migrate existing users table (add new columns if absent) ──
    $existingCols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
    $alterClauses = [];
    if (!in_array('first_name',  $existingCols)) $alterClauses[] = "ADD COLUMN `first_name`  VARCHAR(60) NOT NULL DEFAULT '' AFTER `name`";
    if (!in_array('middle_name', $existingCols)) $alterClauses[] = "ADD COLUMN `middle_name` VARCHAR(60) NOT NULL DEFAULT '' AFTER `first_name`";
    if (!in_array('last_name',   $existingCols)) $alterClauses[] = "ADD COLUMN `last_name`   VARCHAR(60) NOT NULL DEFAULT '' AFTER `middle_name`";
    if (!in_array('username',    $existingCols)) $alterClauses[] = "ADD COLUMN `username`    VARCHAR(80) DEFAULT NULL AFTER `last_name`";
    if (!in_array('birthdate',   $existingCols)) $alterClauses[] = "ADD COLUMN `birthdate`   DATE DEFAULT NULL AFTER `username`";
    if ($alterClauses) {
        $pdo->exec("ALTER TABLE `users` " . implode(', ', $alterClauses));
        ok('Added new profile columns to users table (first_name, middle_name, last_name, username, birthdate)');
    } else {
        ok('User profile columns already present — skipped');
    }
    // Extend role enum to include staff
    try {
        $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer'");
        ok('Role enum updated to include staff');
    } catch (Exception $re) { warn('Role enum: ' . $re->getMessage()); }
    // Unique index on username (ignore if already exists)
    try {
        $pdo->exec("ALTER TABLE `users` ADD UNIQUE INDEX `idx_username` (`username`)");
        ok('Unique index on username added');
    } catch (Exception $ie) { /* already exists — fine */ }

    // ── 3. Admin account ─────────────────────────────────────
    $hash = password_hash('Admin@123', PASSWORD_DEFAULT);
    $exists = $pdo->query("SELECT COUNT(*) FROM users WHERE email='admin@shop.com'")->fetchColumn();
    if ($exists) {
        $pdo->prepare("UPDATE users SET password=?, status='active', role='admin', name='Administrator' WHERE email='admin@shop.com'")
            ->execute([$hash]);
        ok('Admin password reset: admin@shop.com / Admin@123');
    } else {
        $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES ('Administrator','admin@shop.com',?,'admin')")
            ->execute([$hash]);
        ok('Admin account created: admin@shop.com / Admin@123');
    }

    // Verify hash works
    $stored = $pdo->query("SELECT password FROM users WHERE email='admin@shop.com'")->fetchColumn();
    if (password_verify('Admin@123', $stored)) {
        ok('Password hash verified ✓');
    } else {
        err('Password hash verification FAILED — contact support');
    }

    // ── 4. Sample categories ─────────────────────────────────
    $cats = [
        ['Electronics',     'Gadgets, phones, laptops and more'],
        ['Clothing',        'Fashion apparel for men and women'],
        ['Books',           'Bestselling books across all genres'],
        ['Sports & Fitness','Equipment for an active lifestyle'],
        ['Home & Garden',   'Decor, tools, and living essentials'],
    ];
    $catStmt = $pdo->prepare("INSERT IGNORE INTO categories (name,description) VALUES (?,?)");
    foreach ($cats as $c) $catStmt->execute($c);
    ok('Sample categories seeded');

    // ── 5. Sample products ───────────────────────────────────
    $products = [
        [1,'Wireless Headphones Pro',  'Premium over-ear headphones with active noise cancellation and 30-hour battery.',2999.00,2499.00,50,1],
        [1,'Smart Watch Series 5',     '1.4-inch AMOLED display, heart rate monitor, sleep tracking, 7-day battery.',4599.00,null,30,1],
        [1,'Mechanical Keyboard RGB',  'Tactile switches, per-key RGB lighting, aluminium frame, USB-C.',1799.00,1499.00,75,0],
        [1,'Portable Bluetooth Speaker','360° surround sound, waterproof IPX7, 20-hour playtime.',1299.00,null,60,1],
        [2,'Classic Oxford Shirt',     '100% cotton button-down shirt, slim fit, machine washable.',599.00,499.00,100,1],
        [2,'Slim Fit Chino Pants',     'Stretch fabric, tapered leg, five pockets, modern fit.',799.00,null,80,0],
        [2,'Running Jacket Lite',      'Lightweight windbreaker, reflective strips, zip pockets.',1199.00,999.00,45,0],
        [3,'The Art of Clean Code',    'A guide to writing readable, maintainable software — 400 pages.',450.00,null,200,0],
        [3,'Deep Work',                'Rules for focused success in a distracted world by Cal Newport.',380.00,320.00,150,1],
        [4,'Adjustable Dumbbell Set',  '5–52.5 lbs per dumbbell, 15 weight settings, compact design.',3499.00,null,25,1],
        [4,'Resistance Band Kit',      'Set of 5 bands (10–50 lbs), door anchor, handles & ankle straps.',699.00,549.00,90,0],
        [5,'Ceramic Plant Pot Set',    'Set of 3 minimalist matte pots with drainage holes & saucers.',549.00,null,70,0],
    ];
    $prodStmt = $pdo->prepare("INSERT IGNORE INTO products (category_id,name,description,price,sale_price,stock,featured) VALUES (?,?,?,?,?,?,?)");
    foreach ($products as $p) $prodStmt->execute($p);
    ok('Sample products seeded');

    // ── 6. Upload directories ────────────────────────────────
    $dirs = ['products', 'categories', 'avatars'];
    foreach ($dirs as $d) {
        $path = UPLOAD_PATH . $d;
        if (!is_dir($path)) mkdir($path, 0755, true);
    }
    ok('Upload directories ready: assets/uploads/{products,categories,avatars}');

    // ── 7. Summary ───────────────────────────────────────────
    $userCount    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $catCount     = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    ok("Database summary — Users: $userCount | Categories: $catCount | Products: $productCount");

} catch (PDOException $e) {
    err('Database error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Jemelo Setup</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#F1F5F9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border-radius:14px;padding:36px;max-width:620px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,.1)}
  h1{font-size:1.4rem;color:#1E293B;margin-bottom:24px;display:flex;align-items:center;gap:10px}
  .step{display:flex;gap:10px;align-items:flex-start;padding:9px 14px;border-radius:8px;margin-bottom:6px;font-size:.875rem}
  .ok  {background:#D1FAE5;color:#065F46}
  .err {background:#FEE2E2;color:#991B1B}
  .warn{background:#FEF3C7;color:#92400E}
  .icon{font-size:1rem;flex-shrink:0;margin-top:1px}
  .creds{background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:20px;margin-top:20px}
  .creds h3{font-size:.95rem;color:#1E40AF;margin-bottom:10px}
  .creds p{font-size:.875rem;color:#1E293B;margin:4px 0}
  code{background:#DBEAFE;padding:2px 7px;border-radius:4px;font-family:monospace}
  .actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
  .btn{display:inline-flex;align-items:center;gap:6px;padding:11px 22px;border-radius:8px;font-weight:600;font-size:.875rem;text-decoration:none;cursor:pointer;border:none}
  .btn-primary{background:#2563EB;color:#fff}
  .btn-primary:hover{background:#1D4ED8}
  .btn-danger{background:#EF4444;color:#fff}
  .warning-box{background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;padding:14px;margin-top:16px;font-size:.82rem;color:#92400E}
  .has-errors .creds{background:#FEF2F2;border-color:#FECACA}
</style>
</head>
<body>
<div class="card">
  <h1>🛒 Jemelo — Setup</h1>

  <?php foreach ($steps as [$type, $msg]): ?>
  <div class="step <?= $type ?>">
    <span class="icon"><?= $type==='ok' ? '✓' : ($type==='err' ? '✗' : '⚠') ?></span>
    <span><?= htmlspecialchars($msg) ?></span>
  </div>
  <?php endforeach; ?>

  <?php $hasErrors = in_array('err', array_column($steps, 0)); ?>

  <?php if (!$hasErrors): ?>
  <div class="creds">
    <h3>🎉 Setup Complete!</h3>
    <p><strong>Admin Login</strong></p>
    <p>Email: <code>admin@shop.com</code></p>
    <p>Password: <code>Admin@123</code></p>
  </div>

  <div class="actions">
    <a href="<?= SITE_URL ?>/login.php" class="btn btn-primary">→ Go to Login</a>
    <a href="<?= SITE_URL ?>/customer/index.php" class="btn btn-primary" style="background:#10B981">🛍 View Store</a>
  </div>

  <div class="warning-box">
    ⚠ <strong>Important:</strong> Delete <code>setup.php</code> immediately after logging in successfully.
  </div>
  <?php else: ?>
  <div class="warning-box" style="background:#FEE2E2;border-color:#FECACA;color:#991B1B;margin-top:16px">
    ✗ Setup encountered errors. Check MySQL is running in XAMPP Control Panel and try again.
  </div>
  <?php endif; ?>
</div>
</body>
</html>
