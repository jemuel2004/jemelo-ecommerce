<?php
/**
 * Jemelo — Safe Database Migration
 * Run once to add missing columns. Delete this file afterwards.
 */
$allowed = ['localhost', '127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed)) {
    http_response_code(403); die('Access denied.');
}

require_once __DIR__ . '/config/database.php';
$pdo = getDB();

$results = [];

try {
    $existingCols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);

    $toAdd = [];
    if (!in_array('first_name',  $existingCols)) $toAdd[] = "ADD COLUMN `first_name`  VARCHAR(60)  NOT NULL DEFAULT '' AFTER `name`";
    if (!in_array('middle_name', $existingCols)) $toAdd[] = "ADD COLUMN `middle_name` VARCHAR(60)  NOT NULL DEFAULT '' AFTER `first_name`";
    if (!in_array('last_name',   $existingCols)) $toAdd[] = "ADD COLUMN `last_name`   VARCHAR(60)  NOT NULL DEFAULT '' AFTER `middle_name`";
    if (!in_array('username',    $existingCols)) $toAdd[] = "ADD COLUMN `username`    VARCHAR(80)  DEFAULT NULL AFTER `last_name`";
    if (!in_array('birthdate',   $existingCols)) $toAdd[] = "ADD COLUMN `birthdate`   DATE         DEFAULT NULL AFTER `username`";

    if ($toAdd) {
        $pdo->exec("ALTER TABLE `users` " . implode(', ', $toAdd));
        $added = implode(', ', array_map(fn($c) => '`' . explode('`', $c)[1] . '`', $toAdd));
        $results[] = ['ok', "Added missing columns: $added"];
    } else {
        $results[] = ['ok', 'All required columns already present — nothing to add.'];
    }

    // Ensure role enum includes 'staff'
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer'");
    $results[] = ['ok', 'Role enum verified (admin, staff, customer).'];

    // Add unique index on username if missing
    try {
        $pdo->exec("ALTER TABLE `users` ADD UNIQUE INDEX `idx_username` (`username`)");
        $results[] = ['ok', 'Unique index on `username` added.'];
    } catch (Exception $e) {
        $results[] = ['info', 'Unique index on `username` already exists — skipped.'];
    }

    // Summary
    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
    $results[] = ['ok', 'Migration complete. users table columns: ' . implode(', ', $cols)];

} catch (PDOException $e) {
    $results[] = ['err', 'Error: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Jemelo — Migration</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#F1F5F9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border-radius:14px;padding:36px;max-width:640px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,.1)}
  h1{font-size:1.3rem;color:#1E293B;margin-bottom:20px}
  .step{display:flex;gap:10px;align-items:flex-start;padding:9px 14px;border-radius:8px;margin-bottom:6px;font-size:.875rem;word-break:break-all}
  .ok  {background:#D1FAE5;color:#065F46}
  .err {background:#FEE2E2;color:#991B1B}
  .info{background:#DBEAFE;color:#1E40AF}
  .actions{display:flex;gap:10px;margin-top:24px;flex-wrap:wrap}
  .btn{display:inline-flex;align-items:center;gap:6px;padding:11px 22px;border-radius:8px;font-weight:600;font-size:.875rem;text-decoration:none;color:#fff;background:#2563EB}
  .btn:hover{background:#1D4ED8}
  .warn-box{background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;padding:12px 14px;margin-top:16px;font-size:.82rem;color:#92400E}
</style>
</head>
<body>
<div class="card">
  <h1>🔧 Jemelo — Database Migration</h1>
  <?php foreach ($results as [$type, $msg]): ?>
  <div class="step <?= $type ?>">
    <span><?= $type === 'ok' ? '✓' : ($type === 'err' ? '✗' : 'ℹ') ?></span>
    <span><?= htmlspecialchars($msg) ?></span>
  </div>
  <?php endforeach; ?>
  <div class="actions">
    <a href="<?= defined('SITE_URL') ? SITE_URL : 'http://localhost/Ecommerce' ?>/register.php" class="btn">→ Try Registering Again</a>
  </div>
  <div class="warn-box">⚠ Delete <code>migrate.php</code> after this runs successfully.</div>
</div>
</body>
</html>
