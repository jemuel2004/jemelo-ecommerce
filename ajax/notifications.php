<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json');

$db     = getDB();
$action = $_REQUEST['action'] ?? '';
$userId = (int)$_SESSION['user_id'];

switch ($action) {

    // ── Poll: unread count only ───────────────────────────────
    case 'poll':
        echo json_encode(['unread' => unread_notif_count($userId)]);
        break;

    // ── Fetch: full notification list as HTML ─────────────────
    case 'fetch':
        $notifs = get_notifications($userId, 25);
        if (empty($notifs)) {
            $html = '<div class="notif-empty"><i class="fas fa-bell-slash"></i><p>No notifications yet</p></div>';
        } else {
            $html = '';
            foreach ($notifs as $n) {
                $meta    = notif_meta($n['type']);
                $timeAgo = time_ago($n['created_at']);
                $unread  = $n['is_read'] ? '' : 'notif-item--unread';
                $link    = e($n['link'] ?: '#');
                $title   = e($n['title']);
                $msgHtml = $n['message'] ? '<div class="notif-msg">' . e($n['message']) . '</div>' : '';
                $dot     = !$n['is_read'] ? '<span class="notif-dot"></span>' : '';
                $html   .= '<a class="notif-item ' . $unread . '" href="' . $link . '" data-id="' . $n['id'] . '">'
                         . '<div class="notif-icon" style="background:' . $meta['color'] . '20;color:' . $meta['color'] . '">'
                         . '<i class="fas ' . $meta['icon'] . '"></i></div>'
                         . '<div class="notif-body">'
                         . '<div class="notif-title">' . $title . '</div>'
                         . $msgHtml
                         . '<div class="notif-time"><i class="fas fa-clock"></i> ' . $timeAgo . '</div>'
                         . '</div>' . $dot . '</a>';
            }
        }
        echo json_encode(['html' => $html, 'unread' => unread_notif_count($userId)]);
        break;

    // ── Mark one as read ──────────────────────────────────────
    case 'mark_read':
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")
               ->execute([$id, $userId]);
        }
        echo json_encode(['success' => true, 'unread' => unread_notif_count($userId)]);
        break;

    // ── Mark all as read ──────────────────────────────────────
    case 'mark_all_read':
        $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")
           ->execute([$userId]);
        echo json_encode(['success' => true, 'unread' => 0]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
