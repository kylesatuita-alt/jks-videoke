<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');
requireLogin();
$user = getCurrentUser();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Get unread count ──────────────────────────
if ($action === 'count') {
    $st = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $st->execute([$user['id']]);
    echo json_encode(['count' => (int)$st->fetchColumn()]);
    exit();
}

// ── Get all notifications ─────────────────────
if ($action === 'list') {
    $st = $pdo->prepare("
        SELECT n.*, v.name AS unit_name, v.unit_number, r.start_date, r.end_date, r.status AS res_status
        FROM notifications n
        JOIN reservations r ON r.id = n.res_id
        JOIN videokes     v ON v.id = r.videoke_id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 30
    ");
    $st->execute([$user['id']]);
    $rows = $st->fetchAll();
    echo json_encode(['notifications' => $rows]);
    exit();
}

// ── Mark one as read ──────────────────────────
if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
        ->execute([$id, $user['id']]);
    echo json_encode(['success' => true]);
    exit();
}

// ── Mark all as read ──────────────────────────
if ($action === 'read_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
        ->execute([$user['id']]);
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['error' => 'Unknown action']);