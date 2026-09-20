<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
header('Content-Type: application/json');

$db     = getDB();
$action = $_REQUEST['action'] ?? '';
$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['user_role'];

// ── Send message ──────────────────────────────────────────────
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $conv_id = (int)($_POST['conversation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if (!$message || !$conv_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    // Verify access using a whitelist approach (no dynamic SQL)
    if ($role === 'customer') {
        $st = $db->prepare("SELECT cc.id, cc.status, cc.customer_id, cc.subject FROM chat_conversations cc WHERE cc.id = ? AND cc.customer_id = ?");
        $st->execute([$conv_id, $userId]);
    } else {
        $st = $db->prepare("SELECT cc.id, cc.status, cc.customer_id, cc.subject FROM chat_conversations cc WHERE cc.id = ?");
        $st->execute([$conv_id]);
    }
    $conv = $st->fetch();

    if (!$conv || $conv['status'] === 'closed') {
        echo json_encode(['success' => false, 'message' => 'Conversation not found or closed.']);
        exit;
    }

    $db->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)")
       ->execute([$conv_id, $userId, $role, $message]);

    $msgId  = $db->lastInsertId();
    $newMsg = $db->prepare("SELECT cm.*, u.name, u.avatar FROM chat_messages cm JOIN users u ON u.id = cm.sender_id WHERE cm.id = ?");
    $newMsg->execute([$msgId]);
    $msgRow = $newMsg->fetch();

    // ── Notify other party ────────────────────────────────────
    $preview = mb_substr($message, 0, 60) . (mb_strlen($message) > 60 ? '…' : '');

    if ($role === 'customer') {
        // Customer sent → notify all admins
        notify_admins(
            'new_message',
            'New Support Message',
            $_SESSION['user_name'] . ': ' . $preview,
            SITE_URL . '/admin/chat.php?id=' . $conv_id
        );
    } else {
        // Admin replied → notify customer
        notify(
            (int)$conv['customer_id'],
            'chat_reply',
            'Support Team Replied',
            $preview,
            SITE_URL . '/customer/chat.php?id=' . $conv_id
        );
    }

    echo json_encode(['success' => true, 'message' => $msgRow]);
    exit;
}

// ── Poll for new messages ─────────────────────────────────────
if ($action === 'poll') {
    $conv_id = (int)($_GET['conversation_id'] ?? 0);
    $last_id = (int)($_GET['last_id'] ?? 0);

    if (!$conv_id) {
        echo json_encode(['messages' => []]);
        exit;
    }

    if ($role === 'customer') {
        $st = $db->prepare("SELECT id FROM chat_conversations WHERE id = ? AND customer_id = ?");
        $st->execute([$conv_id, $userId]);
    } else {
        $st = $db->prepare("SELECT id FROM chat_conversations WHERE id = ?");
        $st->execute([$conv_id]);
    }

    if (!$st->fetch()) {
        echo json_encode(['messages' => []]);
        exit;
    }

    $msgs = $db->prepare("
        SELECT cm.*, u.name, u.avatar
        FROM chat_messages cm
        JOIN users u ON u.id = cm.sender_id
        WHERE cm.conversation_id = ? AND cm.id > ?
        ORDER BY cm.created_at ASC
    ");
    $msgs->execute([$conv_id, $last_id]);
    $newMsgs = $msgs->fetchAll();

    // Mark incoming messages as read
    if ($role === 'customer') {
        $db->prepare("UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender_role='admin' AND id>?")
           ->execute([$conv_id, $last_id]);
    } else {
        $db->prepare("UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender_role='customer' AND id>?")
           ->execute([$conv_id, $last_id]);
    }

    echo json_encode(['messages' => $newMsgs]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
