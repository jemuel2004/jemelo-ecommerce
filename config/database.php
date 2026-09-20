<?php
// Buffer all output so header() calls always work, even if something
// accidentally echoes before a redirect (warnings, BOM bytes, etc.)
if (ob_get_level() === 0) ob_start();

// ── Database configuration ────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecommerce_db');
define('DB_CHARSET', 'utf8mb4');

// ── Site configuration ───────────────────────────────────────
define('SITE_NAME',  'Jemelo');
define('SITE_URL',   'http://localhost/Ecommerce');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL',  SITE_URL . '/assets/uploads/');

// ── PDO connection (singleton) ───────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log this; never expose details
            error_log('DB Connection failed: ' . $e->getMessage());
            die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
        }
    }
    return $pdo;
}
