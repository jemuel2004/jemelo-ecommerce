<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── Auto-migration: adds missing columns automatically ────────
function ensure_user_columns(): void {
    // Once per session (schema doesn't change mid-session)
    if (!empty($_SESSION['_schema_ok'])) return;
    // Prevent duplicate runs within the same request
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $db   = getDB();
        $cols = $db->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);

        $add = [];
        if (!in_array('first_name',  $cols)) $add[] = "ADD COLUMN `first_name`  VARCHAR(60)  NOT NULL DEFAULT '' AFTER `name`";
        if (!in_array('middle_name', $cols)) $add[] = "ADD COLUMN `middle_name` VARCHAR(60)  NOT NULL DEFAULT '' AFTER `first_name`";
        if (!in_array('last_name',   $cols)) $add[] = "ADD COLUMN `last_name`   VARCHAR(60)  NOT NULL DEFAULT '' AFTER `middle_name`";
        if (!in_array('username',    $cols)) $add[] = "ADD COLUMN `username`    VARCHAR(80)  DEFAULT NULL AFTER `last_name`";
        if (!in_array('birthdate',   $cols)) $add[] = "ADD COLUMN `birthdate`   DATE         DEFAULT NULL AFTER `username`";

        if ($add) {
            $db->exec("ALTER TABLE `users` " . implode(', ', $add));
            try { $db->exec("ALTER TABLE `users` ADD UNIQUE INDEX `idx_username` (`username`)"); }
            catch (PDOException $e) { /* already exists */ }
            try { $db->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer'"); }
            catch (PDOException $e) { /* ignore */ }
        }

        $_SESSION['_schema_ok'] = true;
    } catch (PDOException $e) {
        // Non-fatal — caller surfaces the real error
    }
}

// ── Delivery / Rider schema migration ────────────────────────
function ensure_delivery_columns(): void {
    if (!empty($_SESSION['_delivery_schema_ok'])) return;
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $db = getDB();

        // Add 'rider' to users.role ENUM
        try {
            $db->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','staff','customer','rider') NOT NULL DEFAULT 'customer'");
        } catch (PDOException $e) {}

        // Add rider_id and delivery_notes to orders
        $ocols = $db->query("SHOW COLUMNS FROM `orders`")->fetchAll(PDO::FETCH_COLUMN);
        $oAdd  = [];
        if (!in_array('rider_id', $ocols))       $oAdd[] = "ADD COLUMN `rider_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`";
        if (!in_array('delivery_notes', $ocols))  $oAdd[] = "ADD COLUMN `delivery_notes` TEXT DEFAULT NULL";
        if ($oAdd) $db->exec("ALTER TABLE `orders` " . implode(', ', $oAdd));

        // Expand order status ENUM to include delivery stages
        try {
            $db->exec("ALTER TABLE `orders` MODIFY COLUMN `status`
                ENUM('pending','processing','picked_up','out_for_delivery','shipped','delivered','failed','cancelled')
                NOT NULL DEFAULT 'pending'");
        } catch (PDOException $e) {}

        // Expand payment_status ENUM to include 'unpaid'
        try {
            $db->exec("ALTER TABLE `orders` MODIFY COLUMN `payment_status`
                ENUM('pending','unpaid','paid','failed') NOT NULL DEFAULT 'pending'");
        } catch (PDOException $e) {}

        // Create deliveries table (rider assignment tracking)
        $db->exec("CREATE TABLE IF NOT EXISTS `deliveries` (
            `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `order_id`            INT UNSIGNED NOT NULL,
            `rider_id`            INT UNSIGNED NOT NULL,
            `status`              ENUM('pending','picked_up','out_for_delivery','delivered','failed') NOT NULL DEFAULT 'pending',
            `notes`               TEXT DEFAULT NULL,
            `assigned_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `picked_up_at`        DATETIME DEFAULT NULL,
            `out_for_delivery_at` DATETIME DEFAULT NULL,
            `delivered_at`        DATETIME DEFAULT NULL,
            `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_order`  (`order_id`),
            INDEX `idx_rider`  (`rider_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $_SESSION['_delivery_schema_ok'] = true;
    } catch (PDOException $e) {
        error_log('ensure_delivery_columns failed: ' . $e->getMessage());
    }
}

// ── Session / Remember-Me Tables ─────────────────────────────
function ensure_session_tables(): void {
    if (!empty($_SESSION['_session_tables_ok'])) return;
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $db = getDB();

        $db->exec("CREATE TABLE IF NOT EXISTS `user_sessions` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id`     INT UNSIGNED NOT NULL,
            `token_hash`  VARCHAR(64) NOT NULL,
            `device_info` VARCHAR(255) DEFAULT NULL,
            `ip_address`  VARCHAR(45)  DEFAULT NULL,
            `last_used`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `expires_at`  DATETIME NOT NULL,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `idx_token`   (`token_hash`),
            KEY        `idx_user`    (`user_id`),
            KEY        `idx_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `identifier`   VARCHAR(255) NOT NULL,
            `ip_address`   VARCHAR(45)  NOT NULL,
            `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_identifier`   (`identifier`),
            KEY `idx_ip`           (`ip_address`),
            KEY `idx_attempted_at` (`attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $_SESSION['_session_tables_ok'] = true;
    } catch (PDOException $e) {
        error_log('ensure_session_tables failed: ' . $e->getMessage());
    }
}

// ── Remember-Me: create token ─────────────────────────────
function create_remember_token(int $userId, int $days = 30): void {
    $token   = bin2hex(random_bytes(32));
    $hash    = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', strtotime("+{$days} days"));
    $device  = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    try {
        $db = getDB();
        // Remove expired tokens for this user first
        $db->prepare("DELETE FROM `user_sessions` WHERE `user_id` = ? AND `expires_at` < NOW()")
           ->execute([$userId]);
        $db->prepare(
            "INSERT INTO `user_sessions` (`user_id`, `token_hash`, `device_info`, `ip_address`, `expires_at`)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$userId, $hash, $device, $ip, $expires]);
    } catch (PDOException $e) {
        error_log('create_remember_token failed: ' . $e->getMessage());
        return;
    }

    setcookie('se_remember', $userId . ':' . $token, [
        'expires'  => strtotime("+{$days} days"),
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

// ── Remember-Me: destroy token ────────────────────────────
function destroy_remember_token(): void {
    $cookie = $_COOKIE['se_remember'] ?? '';
    if ($cookie) {
        $parts = explode(':', $cookie, 2);
        if (count($parts) === 2) {
            $hash = hash('sha256', $parts[1]);
            try {
                getDB()->prepare("DELETE FROM `user_sessions` WHERE `token_hash` = ?")
                       ->execute([$hash]);
            } catch (PDOException $e) {}
        }
    }
    setcookie('se_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

// ── Remember-Me: auto-login from cookie ──────────────────
function restore_session_from_cookie(): void {
    if (!empty($_SESSION['user_id'])) return;
    $cookie = $_COOKIE['se_remember'] ?? '';
    if (!$cookie) return;

    $parts = explode(':', $cookie, 2);
    if (count($parts) !== 2) { destroy_remember_token(); return; }

    [$userId, $token] = $parts;
    $userId = (int)$userId;
    if ($userId <= 0) { destroy_remember_token(); return; }

    $hash = hash('sha256', $token);

    try {
        $db = getDB();
        $st = $db->prepare(
            "SELECT s.id, u.name, u.email, u.role, u.avatar, u.status
             FROM `user_sessions` s
             JOIN `users` u ON u.id = s.user_id
             WHERE s.token_hash = ? AND s.user_id = ? AND s.expires_at > NOW()
             LIMIT 1"
        );
        $st->execute([$hash, $userId]);
        $row = $st->fetch();

        if (!$row || $row['status'] === 'inactive') {
            destroy_remember_token();
            return;
        }

        // Rotate the token — delete old, issue new
        $db->prepare("DELETE FROM `user_sessions` WHERE `token_hash` = ?")->execute([$hash]);

        session_regenerate_id(true);
        $_SESSION['user_id']       = $userId;
        $_SESSION['user_name']     = $row['name'];
        $_SESSION['user_email']    = $row['email'];
        $_SESSION['user_role']     = $row['role'];
        $_SESSION['user_avatar']   = $row['avatar'];
        $_SESSION['last_activity'] = time();

        create_remember_token($userId, 30);
    } catch (PDOException $e) {
        error_log('restore_session_from_cookie failed: ' . $e->getMessage());
        destroy_remember_token();
    }
}

// ── Rate Limiting ─────────────────────────────────────────
function check_rate_limit(string $identifier, string $ip): bool {
    try {
        $db     = getDB();
        $cutoff = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        $st     = $db->prepare(
            "SELECT COUNT(*) FROM `login_attempts`
             WHERE (`identifier` = ? OR `ip_address` = ?) AND `attempted_at` > ?"
        );
        $st->execute([$identifier, $ip, $cutoff]);
        return (int)$st->fetchColumn() < 5;
    } catch (PDOException $e) {
        return true; // fail open — don't block login if DB is unavailable
    }
}

function record_login_attempt(string $identifier, string $ip): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO `login_attempts` (`identifier`, `ip_address`) VALUES (?, ?)")
           ->execute([$identifier, $ip]);
        // Prune records older than 24 hours
        $db->exec("DELETE FROM `login_attempts` WHERE `attempted_at` < NOW() - INTERVAL 24 HOUR");
    } catch (PDOException $e) {}
}

// ── Session Timeout ───────────────────────────────────────
function check_session_timeout(int $timeout = 1800): void {
    if (empty($_SESSION['user_id'])) return;
    $last = $_SESSION['last_activity'] ?? 0;
    if ($last && (time() - $last) > $timeout) {
        $was_rider = ($_SESSION['user_role'] ?? '') === 'rider';
        destroy_remember_token();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        session_start();
        $loc = $was_rider
            ? SITE_URL . '/rider/login.php?reason=timeout'
            : SITE_URL . '/customer/index.php?modal=login&reason=timeout';
        header('Location: ' . $loc);
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// Run automatically on every page load — no manual migration needed
ensure_user_columns();
ensure_delivery_columns();
ensure_session_tables();
restore_session_from_cookie();

// ── Guard functions ───────────────────────────────────────────

function require_login(): void {
    check_session_timeout();
    if (empty($_SESSION['user_id'])) {
        flash('error', 'Please log in to continue.', 'danger');
        $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $currentUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
        redirect(SITE_URL . '/customer/index.php?modal=login&return=' . urlencode($currentUrl));
    }
}

/** Admin-only pages (never accessible by staff) */
function require_admin(): void {
    require_login();
    if ($_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        redirect(SITE_URL . '/customer/index.php');
    }
}

/** Rider-only pages */
function require_rider(): void {
    if (empty($_SESSION['user_id'])) {
        redirect(SITE_URL . '/rider/login.php');
    }
    if ($_SESSION['user_role'] !== 'rider') {
        redirect(SITE_URL . '/customer/index.php');
    }
}

/** Admin-panel pages — admin AND staff allowed */
function require_admin_panel(): void {
    require_login();
    if (!in_array($_SESSION['user_role'], ['admin', 'staff'])) {
        http_response_code(403);
        redirect(SITE_URL . '/customer/index.php');
    }
}

function require_customer(): void {
    require_login();
    if ($_SESSION['user_role'] === 'rider') {
        // Riders may browse the store but cannot access buying pages
        redirect(SITE_URL . '/customer/index.php');
    }
    if ($_SESSION['user_role'] !== 'customer') {
        redirect(SITE_URL . '/admin/index.php');
    }
}

function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function is_admin():    bool  { return is_logged_in() && $_SESSION['user_role'] === 'admin'; }
function is_staff():    bool  { return is_logged_in() && in_array($_SESSION['user_role'], ['admin', 'staff']); }

// ── Login ─────────────────────────────────────────────────────
function attempt_login(string $identifier, string $password): array {
    $db         = getDB();
    $identifier = trim($identifier);
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Rate limit check — 5 attempts per 15 min per identifier or IP
    if (!check_rate_limit($identifier, $ip)) {
        return ['success' => false, 'message' => 'Too many login attempts. Please wait 15 minutes and try again.'];
    }

    // Auto-detect: contains @ → treat as email, otherwise → username
    if (str_contains($identifier, '@')) {
        $st = $db->prepare('SELECT id, name, email, password, role, status, avatar FROM users WHERE email = ? LIMIT 1');
        $st->execute([strtolower($identifier)]);
    } else {
        $st = $db->prepare('SELECT id, name, email, password, role, status, avatar FROM users WHERE username = ? LIMIT 1');
        $st->execute([$identifier]);
    }

    $user = $st->fetch();

    if (!$user) {
        record_login_attempt($identifier, $ip);
        return ['success' => false, 'message' => 'No account found with that email or username.'];
    }
    if (!password_verify($password, $user['password'])) {
        record_login_attempt($identifier, $ip);
        return ['success' => false, 'message' => 'Incorrect password. Please try again.'];
    }
    if ($user['status'] === 'inactive') {
        return ['success' => false, 'message' => 'Your account has been deactivated. Please contact support.'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_name']     = $user['name'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['user_avatar']   = $user['avatar'];
    $_SESSION['last_activity'] = time();

    return ['success' => true, 'role' => $user['role'], 'avatar' => $user['avatar']];
}

// ── Register ──────────────────────────────────────────────────
function register_user(array $data, array $files = []): array {
    // Self-heal DB schema before anything else
    ensure_user_columns();

    $errors = [];

    $first_name  = trim($data['first_name']  ?? '');
    $middle_name = trim($data['middle_name'] ?? '');
    $last_name   = trim($data['last_name']   ?? '');
    $username    = trim($data['username']    ?? '');
    $email       = strtolower(trim($data['email']     ?? ''));
    $password    = $data['password'] ?? '';
    $confirm     = $data['confirm']  ?? '';
    $phone       = trim($data['phone']     ?? '');
    $address     = trim($data['address']   ?? '');
    $birthdate   = trim($data['birthdate'] ?? '');

    $name = implode(' ', array_filter([$first_name, $middle_name, $last_name]));

    // ── Validation ────────────────────────────────────────────
    if (!$first_name) $errors[] = 'First name is required.';
    if (!$last_name)  $errors[] = 'Last name is required.';

    if (!$username) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
        $errors[] = 'Username may only contain letters, numbers, dots and underscores.';
    }

    if (!$email) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!$birthdate) {
        $errors[] = 'Birthdate is required.';
    } else {
        $dob = \DateTime::createFromFormat('Y-m-d', $birthdate);
        $age = $dob ? (int)(new \DateTime())->diff($dob)->y : 0;
        if (!$dob || $age < 13) $errors[] = 'You must be at least 13 years old to register.';
    }

    if (!$phone)   $errors[] = 'Mobile number is required.';
    if (!$address) $errors[] = 'Delivery address is required.';

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if ($errors) return ['success' => false, 'errors' => $errors, 'message' => $errors[0]];

    // ── Uniqueness checks ─────────────────────────────────────
    $db = getDB();

    $st = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    if ($st->fetch()) {
        return ['success' => false, 'errors' => ['That email address is already registered.'], 'message' => 'Email is already registered.'];
    }

    $st = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    if ($st->fetch()) {
        return ['success' => false, 'errors' => ['That username is already taken. Please choose another.'], 'message' => 'Username is already taken.'];
    }

    // ── Avatar upload ─────────────────────────────────────────
    $avatarFile = 'default.png';
    if (!empty($files['avatar']['tmp_name']) && $files['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploaded = upload_image($files['avatar'], 'avatars');
        if ($uploaded) $avatarFile = $uploaded;
    }

    // ── Insert ────────────────────────────────────────────────
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins  = $db->prepare(
        'INSERT INTO users
            (name, first_name, middle_name, last_name, username, email, password, phone, address, birthdate, avatar)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $name, $first_name, $middle_name, $last_name, $username,
        $email, $hash, $phone, $address,
        $birthdate ?: null,
        $avatarFile,
    ]);

    return ['success' => true, 'avatar' => $avatarFile, 'name' => $name];
}
