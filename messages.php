<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');
requireLogin();
$user = getCurrentUser();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Send a message (user → admin) ────────────
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty.']);
        exit();
    }
    if (strlen($message) > 1000) {
        echo json_encode(['success' => false, 'message' => 'Message too long.']);
        exit();
    }
    $pdo->prepare("INSERT INTO messages (user_id, sender, message) VALUES (?, 'user', ?)")
        ->execute([$user['id'], $message]);
    echo json_encode(['success' => true]);
    exit();
}

// ── Fetch messages for this user ─────────────
if ($action === 'fetch') {
    $since = $_GET['since'] ?? '0';
    $stmt  = $pdo->prepare("
        SELECT id, sender, message, is_read, created_at
        FROM messages
        WHERE user_id = ? AND id > ?
        ORDER BY created_at ASC
        LIMIT 50
    ");
    $stmt->execute([$user['id'], (int)$since]);
    $msgs = $stmt->fetchAll();

    // Mark admin messages as read
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND sender = 'admin' AND is_read = 0")
        ->execute([$user['id']]);

    echo json_encode(['messages' => $msgs]);
    exit();
}

// ── Unread admin message count ────────────────
if ($action === 'unread') {
    $st = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND sender = 'admin' AND is_read = 0");
    $st->execute([$user['id']]);
    echo json_encode(['count' => (int)$st->fetchColumn()]);
    exit();
}

echo json_encode(['error' => 'Unknown action']);