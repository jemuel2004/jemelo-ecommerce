<?php
require_once __DIR__ . '/../includes/auth.php';
require_customer();
$page_title = 'Chat Support';
$db = getDB();

// Start a new conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_conversation'])) {
    verify_csrf();
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($subject && $message) {
        $db->prepare("INSERT INTO chat_conversations (customer_id, subject) VALUES (?, ?)")
           ->execute([$_SESSION['user_id'], $subject]);
        $conv_id = $db->lastInsertId();
        $db->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?, ?, 'customer', ?)")
           ->execute([$conv_id, $_SESSION['user_id'], $message]);
        header("Location: " . SITE_URL . "/customer/chat.php?id=$conv_id");
        exit;
    }
}

// Active conversation
$conv_id = (int)($_GET['id'] ?? 0);
$conversation = null;
if ($conv_id) {
    $st = $db->prepare("SELECT * FROM chat_conversations WHERE id=? AND customer_id=?");
    $st->execute([$conv_id, $_SESSION['user_id']]);
    $conversation = $st->fetch();
    if ($conversation) {
        // Mark admin messages as read
        $db->prepare("UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender_role='admin'")
           ->execute([$conv_id]);
    }
}

// All conversations for this customer
$convs = $db->prepare("
    SELECT cc.*,
           (SELECT COUNT(*) FROM chat_messages WHERE conversation_id=cc.id AND sender_role='admin' AND is_read=0) AS unread,
           (SELECT message FROM chat_messages WHERE conversation_id=cc.id ORDER BY created_at DESC LIMIT 1) AS last_msg,
           (SELECT created_at FROM chat_messages WHERE conversation_id=cc.id ORDER BY created_at DESC LIMIT 1) AS last_at
    FROM chat_conversations cc
    WHERE cc.customer_id = ?
    ORDER BY COALESCE(last_at, cc.created_at) DESC
");
$convs->execute([$_SESSION['user_id']]);
$conversations = $convs->fetchAll();

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

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1><i class="fas fa-comments" style="color:var(--primary)"></i> Chat Support</h1>
    <p>Get help from our team for your concerns</p>
  </div>
</div>

<div class="container" style="padding-bottom:60px">
  <div class="chat-layout">

    <!-- Sidebar: conversations list -->
    <div class="chat-sidebar">
      <div class="chat-sidebar-header">
        <span>My Conversations</span>
        <button class="btn btn-primary btn-sm" id="new-conv-btn"><i class="fas fa-plus"></i> New</button>
      </div>

      <!-- New conversation form -->
      <div id="new-conv-form" style="display:none;padding:14px;border-bottom:1px solid var(--border)">
        <form method="POST">
          <?= csrf_field() ?>
          <div class="form-group" style="margin-bottom:8px">
            <select name="subject" class="form-control form-control-sm" required>
              <option value="">Select topic...</option>
              <option>Order Issue</option>
              <option>Payment Problem</option>
              <option>Delivery Concern</option>
              <option>Product Question</option>
              <option>Account Help</option>
              <option>Other</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:8px">
            <textarea name="message" class="form-control form-control-sm" rows="3" placeholder="Describe your concern..." required></textarea>
          </div>
          <div style="display:flex;gap:8px">
            <button type="submit" name="new_conversation" class="btn btn-primary btn-sm">Send</button>
            <button type="button" class="btn btn-outline btn-sm" id="cancel-conv-btn">Cancel</button>
          </div>
        </form>
      </div>

      <div class="chat-conv-list">
        <?php if (empty($conversations)): ?>
        <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:.85rem">
          No conversations yet.<br>Click <strong>+ New</strong> to start.
        </div>
        <?php else: foreach ($conversations as $c): ?>
        <a href="<?= SITE_URL ?>/customer/chat.php?id=<?= $c['id'] ?>"
           class="chat-conv-item <?= $conv_id == $c['id'] ? 'active' : '' ?>">
          <div class="chat-conv-subject">
            <?= e($c['subject']) ?>
            <?php if ($c['unread'] > 0): ?>
            <span class="badge badge-danger" style="font-size:.65rem"><?= $c['unread'] ?></span>
            <?php endif; ?>
          </div>
          <div class="chat-conv-preview"><?= e(mb_substr($c['last_msg'] ?? '', 0, 50)) ?>…</div>
          <div class="chat-conv-time"><?= $c['last_at'] ? time_ago($c['last_at']) : time_ago($c['created_at']) ?></div>
          <span class="badge <?= $c['status'] === 'open' ? 'badge-success' : 'badge-secondary' ?>" style="font-size:.62rem"><?= ucfirst($c['status']) ?></span>
        </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Main chat area -->
    <div class="chat-main">
      <?php if (!$conversation): ?>
      <div class="chat-empty">
        <i class="fas fa-comments"></i>
        <h3>Select a conversation</h3>
        <p>Choose a conversation from the list or start a new one.</p>
        <button class="btn btn-primary" id="new-conv-btn2"><i class="fas fa-plus"></i> New Conversation</button>
      </div>
      <?php else: ?>
      <!-- Chat header -->
      <div class="chat-header">
        <div>
          <div class="chat-header-subject"><?= e($conversation['subject']) ?></div>
          <div class="chat-header-meta">
            <span class="badge <?= $conversation['status'] === 'open' ? 'badge-success' : 'badge-secondary' ?>"><?= ucfirst($conversation['status']) ?></span>
            &nbsp;Started <?= time_ago($conversation['created_at']) ?>
          </div>
        </div>
      </div>

      <!-- Messages -->
      <div class="chat-messages" id="chat-messages">
        <?php foreach ($messages as $msg): ?>
        <div class="chat-msg <?= $msg['sender_role'] === 'customer' ? 'chat-msg-me' : 'chat-msg-other' ?>">
          <?php if ($msg['sender_role'] === 'admin'): ?>
          <img src="<?= avatar_url($msg['avatar']) ?>" class="chat-msg-avatar" alt="admin">
          <?php endif; ?>
          <div class="chat-msg-bubble">
            <?php if ($msg['sender_role'] === 'admin'): ?>
            <div class="chat-msg-name">Support Team</div>
            <?php endif; ?>
            <div class="chat-msg-text"><?= nl2br(e($msg['message'])) ?></div>
            <div class="chat-msg-time"><?= date('M j, g:i A', strtotime($msg['created_at'])) ?></div>
          </div>
          <?php if ($msg['sender_role'] === 'customer'): ?>
          <img src="<?= avatar_url($msg['avatar']) ?>" class="chat-msg-avatar" alt="me">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Reply box -->
      <?php if ($conversation['status'] === 'open'): ?>
      <div class="chat-reply-box">
        <form id="chat-reply-form" data-conv="<?= $conv_id ?>">
          <?= csrf_field() ?>
          <textarea id="chat-reply-input" class="chat-reply-input" placeholder="Type your message…" rows="1" required></textarea>
          <button type="submit" class="chat-send-btn"><i class="fas fa-paper-plane"></i></button>
        </form>
      </div>
      <?php else: ?>
      <div style="padding:16px;text-align:center;color:var(--text-muted);font-size:.85rem;border-top:1px solid var(--border)">
        This conversation is closed.
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const siteUrl = '<?= SITE_URL ?>';

// Toggle new conversation form
document.getElementById('new-conv-btn').addEventListener('click', function() {
  const f = document.getElementById('new-conv-form');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
});
document.getElementById('cancel-conv-btn')?.addEventListener('click', function() {
  document.getElementById('new-conv-form').style.display = 'none';
});
document.getElementById('new-conv-btn2')?.addEventListener('click', function() {
  document.getElementById('new-conv-form').style.display = 'block';
  document.getElementById('new-conv-btn').scrollIntoView();
});

// Scroll chat to bottom
function scrollBottom() {
  const el = document.getElementById('chat-messages');
  if (el) el.scrollTop = el.scrollHeight;
}
scrollBottom();

// Send message via AJAX
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
        appendMessage(data.message, 'me');
      }
    });
  });

  // Auto-grow textarea
  document.getElementById('chat-reply-input').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });

  // Poll for new messages every 4 seconds
  let lastId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
  setInterval(() => {
    const convId = replyForm.dataset.conv;
    fetch(siteUrl + `/ajax/chat.php?action=poll&conversation_id=${convId}&last_id=${lastId}`)
      .then(r => r.json()).then(data => {
        if (data.messages && data.messages.length) {
          data.messages.forEach(m => {
            if (m.sender_role !== 'customer') {
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
  const text = typeof msg === 'string' ? msg : msg.message;
  const time = typeof msg === 'object' && msg.created_at
    ? new Date(msg.created_at).toLocaleString('en-PH', {month:'short',day:'numeric',hour:'numeric',minute:'2-digit'})
    : new Date().toLocaleString('en-PH', {month:'short',day:'numeric',hour:'numeric',minute:'2-digit'});
  div.innerHTML = `<div class="chat-msg-bubble">
    <div class="chat-msg-text">${escHtml(text).replace(/\n/g,'<br>')}</div>
    <div class="chat-msg-time">${escHtml(time)}</div>
  </div>`;
  box.appendChild(div);
  scrollBottom();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
