<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin_panel();

$page_title  = 'Chat Support';
$active_menu = 'chat';
$db = getDB();

$conv_id = (int)($_GET['id'] ?? 0);

// ── POST handling BEFORE any HTML output ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    verify_csrf();
    $newStatus = $_POST['toggle_status'];
    if (in_array($newStatus, ['open', 'closed'])) {
        $db->prepare("UPDATE chat_conversations SET status=? WHERE id=?")->execute([$newStatus, $conv_id]);
        redirect(SITE_URL . '/admin/chat.php?id=' . $conv_id);
    }
}

require_once __DIR__ . '/../includes/admin_header.php';

// ── Active conversation ───────────────────────────────────────
$conversation = null;
if ($conv_id) {
    $st = $db->prepare("
        SELECT cc.*, u.name AS customer_name, u.email AS customer_email, u.avatar AS customer_avatar
        FROM chat_conversations cc
        JOIN users u ON u.id = cc.customer_id
        WHERE cc.id = ?
    ");
    $st->execute([$conv_id]);
    $conversation = $st->fetch();
    if ($conversation) {
        $db->prepare("UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender_role='customer'")
           ->execute([$conv_id]);
    }
}

// All conversations — whitelist filter value to prevent SQL injection
$filter = in_array($_GET['filter'] ?? '', ['open', 'closed', 'all']) ? $_GET['filter'] : 'open';
if ($filter === 'all') {
    $convs = $db->query("
        SELECT cc.*, u.name AS customer_name, u.avatar AS customer_avatar,
               (SELECT COUNT(*) FROM chat_messages WHERE conversation_id=cc.id AND sender_role='customer' AND is_read=0) AS unread,
               (SELECT message FROM chat_messages WHERE conversation_id=cc.id ORDER BY created_at DESC LIMIT 1) AS last_msg,
               (SELECT created_at FROM chat_messages WHERE conversation_id=cc.id ORDER BY created_at DESC LIMIT 1) AS last_at
        FROM chat_conversations cc
        JOIN users u ON u.id = cc.customer_id
        ORDER BY COALESCE(last_at, cc.created_at) DESC
    ")->fetchAll();
} else {
    $convStmt = $db->prepare("
        SELECT cc.*, u.name AS customer_name, u.avatar AS customer_avatar,
               (SELECT COUNT(*) FROM chat_messages WHERE conversation_id=cc.id AND sender_role='customer' AND is_read=0) AS unread,
               (SELECT message FROM chat_messages WHERE conversation_id=cc.id ORDER BY created_at DESC LIMIT 1) AS last_msg,
               (SELECT created_at FROM chat_messages WHERE conversation_id=cc.id ORDER BY created_at DESC LIMIT 1) AS last_at
        FROM chat_conversations cc
        JOIN users u ON u.id = cc.customer_id
        WHERE cc.status = ?
        ORDER BY COALESCE(last_at, cc.created_at) DESC
    ");
    $convStmt->execute([$filter]);
    $convs = $convStmt->fetchAll();
}

// Messages for active conversation
$messages = [];
if ($conversation) {
    $msg_st = $db->prepare("
        SELECT cm.*, u.name, u.avatar, u.role
        FROM chat_messages cm
        JOIN users u ON u.id = cm.sender_id
        WHERE cm.conversation_id = ?
        ORDER BY cm.created_at ASC
    ");
    $msg_st->execute([$conv_id]);
    $messages = $msg_st->fetchAll();
}

$admin_info = $db->prepare("SELECT avatar FROM users WHERE id=?");
$admin_info->execute([$_SESSION['user_id']]);
$my_avatar = $admin_info->fetchColumn();
?>

<div class="admin-page-header" style="margin-bottom:0">
  <div class="admin-page-title">Chat Support</div>
</div>

<div class="chat-layout chat-admin-layout">

  <!-- Sidebar -->
  <div class="chat-sidebar">
    <div class="chat-sidebar-header" style="flex-direction:column;align-items:flex-start;gap:10px">
      <span>Conversations</span>
      <div style="display:flex;gap:6px;width:100%">
        <a href="?filter=open<?= $conv_id ? "&id=$conv_id" : '' ?>"
           class="btn btn-sm <?= $filter==='open' ? 'btn-primary' : 'btn-outline' ?>" style="flex:1;justify-content:center">Open</a>
        <a href="?filter=closed<?= $conv_id ? "&id=$conv_id" : '' ?>"
           class="btn btn-sm <?= $filter==='closed' ? 'btn-primary' : 'btn-outline' ?>" style="flex:1;justify-content:center">Closed</a>
        <a href="?filter=all<?= $conv_id ? "&id=$conv_id" : '' ?>"
           class="btn btn-sm <?= $filter==='all' ? 'btn-primary' : 'btn-outline' ?>" style="flex:1;justify-content:center">All</a>
      </div>
    </div>

    <div class="chat-conv-list">
      <?php if (empty($convs)): ?>
      <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:.85rem">No conversations found.</div>
      <?php else: foreach ($convs as $c): ?>
      <a href="<?= SITE_URL ?>/admin/chat.php?id=<?= $c['id'] ?>&filter=<?= $filter ?>"
         class="chat-conv-item <?= $conv_id == $c['id'] ? 'active' : '' ?>">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
          <img src="<?= avatar_url($c['customer_avatar']) ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover" alt="">
          <span class="chat-conv-subject" style="flex:1"><?= e($c['customer_name']) ?></span>
          <?php if ($c['unread'] > 0): ?>
          <span class="badge badge-danger" style="font-size:.6rem"><?= $c['unread'] ?></span>
          <?php endif; ?>
        </div>
        <div class="chat-conv-subject" style="font-size:.78rem;color:var(--text-muted)"><?= e($c['subject']) ?></div>
        <div class="chat-conv-preview"><?= e(mb_substr($c['last_msg'] ?? '', 0, 50)) ?>…</div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:2px">
          <span class="chat-conv-time"><?= $c['last_at'] ? time_ago($c['last_at']) : time_ago($c['created_at']) ?></span>
          <span class="badge <?= $c['status']==='open' ? 'badge-success' : 'badge-secondary' ?>" style="font-size:.6rem"><?= ucfirst($c['status']) ?></span>
        </div>
      </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Main chat area -->
  <div class="chat-main">
    <?php if (!$conversation): ?>
    <div class="chat-empty">
      <i class="fas fa-headset"></i>
      <h3>Select a conversation</h3>
      <p>Choose a customer conversation to view and reply.</p>
    </div>
    <?php else: ?>

    <!-- Chat header -->
    <div class="chat-header">
      <div style="display:flex;align-items:center;gap:12px">
        <img src="<?= avatar_url($conversation['customer_avatar']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover" alt="">
        <div>
          <div class="chat-header-subject"><?= e($conversation['customer_name']) ?></div>
          <div class="chat-header-meta"><?= e($conversation['customer_email']) ?> &mdash; <?= e($conversation['subject']) ?></div>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <span class="badge <?= $conversation['status']==='open' ? 'badge-success' : 'badge-secondary' ?>"><?= ucfirst($conversation['status']) ?></span>
        <form method="POST" style="margin:0">
          <?= csrf_field() ?>
          <?php if ($conversation['status'] === 'open'): ?>
          <button type="submit" name="toggle_status" value="closed" class="btn btn-sm btn-outline">
            <i class="fas fa-lock"></i> Close
          </button>
          <?php else: ?>
          <button type="submit" name="toggle_status" value="open" class="btn btn-sm btn-outline">
            <i class="fas fa-lock-open"></i> Reopen
          </button>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chat-messages">
      <?php foreach ($messages as $msg): ?>
      <div class="chat-msg <?= $msg['sender_role'] === 'admin' ? 'chat-msg-me' : 'chat-msg-other' ?>">
        <?php if ($msg['sender_role'] === 'customer'): ?>
        <img src="<?= avatar_url($msg['avatar']) ?>" class="chat-msg-avatar" alt="">
        <?php endif; ?>
        <div class="chat-msg-bubble">
          <div class="chat-msg-name"><?= $msg['sender_role'] === 'admin' ? 'You' : e($msg['name']) ?></div>
          <div class="chat-msg-text"><?= nl2br(e($msg['message'])) ?></div>
          <div class="chat-msg-time"><?= date('M j, g:i A', strtotime($msg['created_at'])) ?></div>
        </div>
        <?php if ($msg['sender_role'] === 'admin'): ?>
        <img src="<?= avatar_url($my_avatar) ?>" class="chat-msg-avatar" alt="">
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Reply box -->
    <?php if ($conversation['status'] === 'open'): ?>
    <div class="chat-reply-box">
      <form id="chat-reply-form" data-conv="<?= $conv_id ?>">
        <?= csrf_field() ?>
        <textarea id="chat-reply-input" class="chat-reply-input" placeholder="Type your reply…" rows="1" required></textarea>
        <button type="submit" class="chat-send-btn"><i class="fas fa-paper-plane"></i></button>
      </form>
    </div>
    <?php else: ?>
    <div style="padding:16px;text-align:center;color:var(--text-muted);font-size:.85rem;border-top:1px solid var(--border)">
      Conversation closed. <a href="?id=<?= $conv_id ?>&filter=<?= $filter ?>" onclick="this.closest('form') || document.querySelector('[name=toggle_status][value=open]')?.click()">Reopen</a> to reply.
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
const siteUrl = '<?= SITE_URL ?>';

function scrollBottom() {
  const el = document.getElementById('chat-messages');
  if (el) el.scrollTop = el.scrollHeight;
}
scrollBottom();

const replyForm = document.getElementById('chat-reply-form');
if (replyForm) {
  replyForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const input = document.getElementById('chat-reply-input');
    const msg = input.value.trim();
    if (!msg) return;
    const convId = this.dataset.conv;
    const csrf = this.querySelector('[name=csrf_token]').value;
    fetch(siteUrl + '/ajax/chat.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `action=send&conversation_id=${convId}&message=${encodeURIComponent(msg)}&csrf_token=${csrf}`
    }).then(r => r.json()).then(data => {
      if (data.success) {
        input.value = '';
        input.style.height = 'auto';
        appendMessage(data.message, 'me');
      }
    });
  });

  document.getElementById('chat-reply-input').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });

  // Poll for customer messages every 4 seconds
  let lastId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
  setInterval(() => {
    const convId = replyForm.dataset.conv;
    fetch(siteUrl + `/ajax/chat.php?action=poll&conversation_id=${convId}&last_id=${lastId}`)
      .then(r => r.json()).then(data => {
        if (data.messages && data.messages.length) {
          data.messages.forEach(m => {
            if (m.sender_role !== 'admin') {
              appendMessage(m, 'other');
              lastId = m.id;
            }
          });
        }
      });
  }, 4000);
}

function escHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(String(str)));
  return d.innerHTML;
}
function appendMessage(msg, side) {
  const box = document.getElementById('chat-messages');
  const div = document.createElement('div');
  div.className = 'chat-msg ' + (side === 'me' ? 'chat-msg-me' : 'chat-msg-other');
  const text = typeof msg === 'object' ? msg.message : msg;
  const time = typeof msg === 'object' && msg.created_at
    ? new Date(msg.created_at).toLocaleString('en-PH', {month:'short',day:'numeric',hour:'numeric',minute:'2-digit'})
    : new Date().toLocaleString('en-PH', {month:'short',day:'numeric',hour:'numeric',minute:'2-digit'});
  div.innerHTML = `<div class="chat-msg-bubble">
    <div class="chat-msg-name">${side === 'me' ? 'You' : 'Customer'}</div>
    <div class="chat-msg-text">${escHtml(text).replace(/\n/g,'<br>')}</div>
    <div class="chat-msg-time">${escHtml(time)}</div>
  </div>`;
  box.appendChild(div);
  scrollBottom();
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
