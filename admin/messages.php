<?php
require_once '../includes/admin_auth.php';

// ── AJAX ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Admin reply
    if ($_POST['action'] === 'reply') {
        $user_id = (int)$_POST['user_id'];
        $message = trim($_POST['message'] ?? '');
        if (!$user_id || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Missing data.']); exit();
        }
        $pdo->prepare("INSERT INTO messages (user_id, sender, message) VALUES (?, 'admin', ?)")
            ->execute([$user_id, $message]);

        // Also push a notification to the user
        $pdo->prepare("
            INSERT INTO notifications (user_id, res_id, type, title, message)
            SELECT ?, COALESCE((SELECT id FROM reservations WHERE user_id = ? ORDER BY created_at DESC LIMIT 1), 1),
                   'support', '💬 New message from JKS Support', ?
        ")->execute([$user_id, $user_id, $message]);

        echo json_encode(['success' => true]); exit();
    }

    // Fetch messages for a user
    if ($_POST['action'] === 'fetch') {
        $user_id = (int)$_POST['user_id'];
        $since   = (int)($_POST['since'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT id, sender, message, is_read, created_at
            FROM messages WHERE user_id = ? AND id > ?
            ORDER BY created_at ASC LIMIT 100
        ");
        $stmt->execute([$user_id, $since]);
        // Mark user messages as read by admin
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND sender = 'user' AND is_read = 0")
            ->execute([$user_id]);
        echo json_encode(['messages' => $stmt->fetchAll()]); exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']); exit();
}

// ── Fetch all conversations (one row per user, latest message) ──
$conversations = $pdo->query("
    SELECT u.id AS user_id, u.name, u.email,
           m.message AS last_msg,
           m.sender  AS last_sender,
           m.created_at AS last_at,
           SUM(CASE WHEN m.sender = 'user' AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
    FROM users u
    JOIN messages m ON m.user_id = u.id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY last_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Messages · JKS Videoke</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .chat-layout { display: grid; grid-template-columns: 300px 1fr; gap: 0; height: calc(100vh - 58px - 56px); min-height: 500px; }
        .convo-list { border-right: 1px solid var(--border); overflow-y: auto; }
        .convo-item { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); cursor: pointer; transition: background 0.14s; display: flex; align-items: flex-start; gap: 10px; }
        .convo-item:hover { background: rgba(255,255,255,0.02); }
        .convo-item.active { background: var(--gold-soft); border-left: 3px solid var(--gold); }
        .convo-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--gold); color: #0A1520; font-size: 13px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .convo-info { flex: 1; min-width: 0; }
        .convo-name { font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .convo-preview { font-size: 11px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
        .convo-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
        .convo-time { font-size: 10px; color: var(--muted); }
        .convo-unread { background: var(--gold); color: #0A1520; font-size: 10px; font-weight: 800; border-radius: 10px; padding: 1px 6px; min-width: 18px; text-align: center; }

        .chat-area { display: flex; flex-direction: column; }
        .chat-topbar { padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; flex-shrink: 0; background: rgba(255,255,255,0.01); }
        .chat-topbar-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--gold); color: #0A1520; font-size: 14px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .chat-topbar-name { font-size: 14px; font-weight: 700; }
        .chat-topbar-email { font-size: 11px; color: var(--muted); margin-top: 1px; }

        .chat-messages { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; }
        .chat-messages::-webkit-scrollbar { width: 4px; }
        .chat-messages::-webkit-scrollbar-thumb { background: rgba(245,197,24,0.15); border-radius: 2px; }

        .chat-bubble { max-width: 72%; display: flex; flex-direction: column; gap: 3px; }
        .chat-bubble.user { align-self: flex-start; }
        .chat-bubble.admin { align-self: flex-end; }
        .chat-bubble-text { padding: 10px 14px; border-radius: 14px; font-size: 13px; line-height: 1.55; word-break: break-word; }
        .chat-bubble.user  .chat-bubble-text { background: var(--bg-elevated); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
        .chat-bubble.admin .chat-bubble-text { background: var(--gold); color: #0A1520; font-weight: 500; border-bottom-right-radius: 4px; }
        .chat-bubble-meta { font-size: 10px; color: var(--muted); }
        .chat-bubble.admin .chat-bubble-meta { text-align: right; }

        .chat-input-wrap { padding: 14px 16px; border-top: 1px solid var(--border); display: flex; gap: 10px; align-items: flex-end; background: rgba(255,255,255,0.01); flex-shrink: 0; }
        .chat-textarea { flex: 1; background: var(--bg-input); border: 1.5px solid rgba(255,255,255,0.07); border-radius: 10px; color: var(--text); font-family: 'Inter', sans-serif; font-size: 13px; padding: 9px 13px; outline: none; resize: none; max-height: 120px; transition: border-color 0.18s; color-scheme: dark; }
        .chat-textarea:focus { border-color: var(--gold); }
        .chat-send-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--gold); color: #0A1520; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.18s; flex-shrink: 0; }
        .chat-send-btn:hover { background: #ffcf2a; }
        .chat-send-btn svg { width: 16px; height: 16px; }

        .chat-empty-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--muted); gap: 12px; }
        .chat-empty-state svg { width: 48px; height: 48px; opacity: 0.15; }
        .chat-empty-state p { font-size: 13px; }

        .no-convos { padding: 40px 20px; text-align: center; color: var(--muted); }
        .no-convos svg { width: 40px; height: 40px; opacity: 0.15; margin: 0 auto 12px; display: block; }
        .no-convos p { font-size: 13px; }
    </style>
</head>
<body>
<div class="admin-layout">
<?php require_once 'admin_shell.php'; renderAdminShell('Customer Messages', 'messages'); ?>

<?php if (empty($conversations)): ?>
<div class="no-convos">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <p>No customer messages yet.</p>
</div>
<?php else: ?>

<div class="chat-layout table-wrap" style="border-radius:var(--radius-md); overflow:hidden;">
    <!-- ── Conversation list ── -->
    <div class="convo-list" id="convoList">
        <?php foreach ($conversations as $c): ?>
        <div class="convo-item" data-uid="<?= $c['user_id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-email="<?= htmlspecialchars($c['email']) ?>">
            <div class="convo-avatar"><?= strtoupper(substr($c['name'],0,1)) ?></div>
            <div class="convo-info">
                <div class="convo-name"><?= htmlspecialchars($c['name']) ?></div>
                <div class="convo-preview">
                    <?= $c['last_sender'] === 'admin' ? '↩ You: ' : '' ?>
                    <?= htmlspecialchars(mb_substr($c['last_msg'], 0, 45)) ?><?= mb_strlen($c['last_msg']) > 45 ? '…' : '' ?>
                </div>
            </div>
            <div class="convo-meta">
                <span class="convo-time"><?= date('M j', strtotime($c['last_at'])) ?></span>
                <?php if ($c['unread_count'] > 0): ?>
                <span class="convo-unread"><?= $c['unread_count'] ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Chat area ── -->
    <div class="chat-area" id="chatArea">
        <div class="chat-empty-state" id="chatEmptyState">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <p>Select a conversation to view messages</p>
        </div>
        <div id="chatActive" style="display:none; flex-direction:column; flex:1; overflow:hidden;">
            <div class="chat-topbar">
                <div class="chat-topbar-avatar" id="chatAvatar">?</div>
                <div>
                    <div class="chat-topbar-name"  id="chatName">—</div>
                    <div class="chat-topbar-email" id="chatEmail">—</div>
                </div>
            </div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-input-wrap">
                <textarea class="chat-textarea" id="adminReplyInput" placeholder="Type a reply…" rows="1"></textarea>
                <button class="chat-send-btn" id="adminSendBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php closeAdminShell(); ?>
</div>

<script>
let activeUserId  = null;
let lastMsgId     = 0;
let pollInterval  = null;

function timeStr(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }) + ' · ' + d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

function renderBubble(m) {
    const div = document.createElement('div');
    div.className = `chat-bubble ${m.sender}`;
    div.dataset.id = m.id;
    div.innerHTML = `
        <div class="chat-bubble-text">${escHtml(m.message)}</div>
        <div class="chat-bubble-meta">${m.sender === 'admin' ? 'You · ' : ''}${timeStr(m.created_at)}</div>`;
    return div;
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

async function loadChat(userId) {
    const msgs = document.getElementById('chatMessages');
    msgs.innerHTML = '';
    lastMsgId = 0;

    const fd = new FormData();
    fd.append('action',  'fetch');
    fd.append('user_id', userId);
    fd.append('since',   0);
    const res  = await fetch('messages.php', { method: 'POST', body: fd });
    const data = await res.json();
    (data.messages || []).forEach(m => {
        msgs.appendChild(renderBubble(m));
        lastMsgId = Math.max(lastMsgId, parseInt(m.id));
    });
    msgs.scrollTop = msgs.scrollHeight;

    // Remove unread badge from convo item
    const item = document.querySelector(`.convo-item[data-uid="${userId}"] .convo-unread`);
    if (item) item.remove();
}

async function pollNewMessages() {
    if (!activeUserId) return;
    const fd = new FormData();
    fd.append('action',  'fetch');
    fd.append('user_id', activeUserId);
    fd.append('since',   lastMsgId);
    const res  = await fetch('messages.php', { method: 'POST', body: fd });
    const data = await res.json();
    const msgs = document.getElementById('chatMessages');
    (data.messages || []).forEach(m => {
        msgs.appendChild(renderBubble(m));
        lastMsgId = Math.max(lastMsgId, parseInt(m.id));
    });
    if (data.messages?.length > 0) msgs.scrollTop = msgs.scrollHeight;
}

// Select conversation
document.querySelectorAll('.convo-item').forEach(item => {
    item.addEventListener('click', () => {
        document.querySelectorAll('.convo-item').forEach(i => i.classList.remove('active'));
        item.classList.add('active');

        activeUserId = item.dataset.uid;
        document.getElementById('chatAvatar').textContent = item.dataset.name.charAt(0).toUpperCase();
        document.getElementById('chatName').textContent   = item.dataset.name;
        document.getElementById('chatEmail').textContent  = item.dataset.email;

        document.getElementById('chatEmptyState').style.display = 'none';
        const active = document.getElementById('chatActive');
        active.style.display = 'flex';

        loadChat(activeUserId);
        clearInterval(pollInterval);
        pollInterval = setInterval(pollNewMessages, 4000);
    });
});

// Send reply
async function sendReply() {
    const input = document.getElementById('adminReplyInput');
    const msg   = input.value.trim();
    if (!msg || !activeUserId) return;
    input.value = '';
    input.style.height = 'auto';

    const fd = new FormData();
    fd.append('action',  'reply');
    fd.append('user_id', activeUserId);
    fd.append('message', msg);
    await fetch('messages.php', { method: 'POST', body: fd });
    await pollNewMessages();
}

document.getElementById('adminSendBtn')?.addEventListener('click', sendReply);
document.getElementById('adminReplyInput')?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
});

// Auto-resize textarea
document.getElementById('adminReplyInput')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
</script>
</body>
</html>