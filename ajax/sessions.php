<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'list') {
    try {
        $db = getDB();
        $st = $db->prepare(
            "SELECT id, device_info, ip_address, last_used, expires_at, created_at
             FROM `user_sessions`
             WHERE `user_id` = ? AND `expires_at` > NOW()
             ORDER BY `last_used` DESC"
        );
        $st->execute([$_SESSION['user_id']]);
        $rows = $st->fetchAll();

        // Identify current session's token hash (if remember cookie is active)
        $currentHash = '';
        $cookie = $_COOKIE['se_remember'] ?? '';
        if ($cookie) {
            $parts = explode(':', $cookie, 2);
            if (count($parts) === 2) {
                $currentHash = hash('sha256', $parts[1]);
            }
        }

        $sessions = [];
        foreach ($rows as $r) {
            $sessions[] = [
                'id'          => $r['id'],
                'device_info' => $r['device_info'] ?: 'Unknown device',
                'ip_address'  => $r['ip_address']  ?: 'Unknown',
                'last_used'   => $r['last_used'],
                'expires_at'  => $r['expires_at'],
                'created_at'  => $r['created_at'],
            ];
        }
        echo json_encode(['success' => true, 'sessions' => $sessions, 'current_hash' => $currentHash]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }

} elseif ($action === 'revoke' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if ($sessionId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid session ID']);
        exit;
    }
    try {
        getDB()->prepare("DELETE FROM `user_sessions` WHERE `id` = ? AND `user_id` = ?")
               ->execute([$sessionId, $_SESSION['user_id']]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }

} elseif ($action === 'revoke_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Keep the current cookie's token so this device stays logged in
    $currentHash = '';
    $cookie = $_COOKIE['se_remember'] ?? '';
    if ($cookie) {
        $parts = explode(':', $cookie, 2);
        if (count($parts) === 2) {
            $currentHash = hash('sha256', $parts[1]);
        }
    }
    try {
        $db = getDB();
        if ($currentHash) {
            $db->prepare("DELETE FROM `user_sessions` WHERE `user_id` = ? AND `token_hash` != ?")
               ->execute([$_SESSION['user_id'], $currentHash]);
        } else {
            $db->prepare("DELETE FROM `user_sessions` WHERE `user_id` = ?")
               ->execute([$_SESSION['user_id']]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
