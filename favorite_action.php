<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');
requireLogin();

$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$videoke_id = (int)($_POST['videoke_id'] ?? 0);

if (!$videoke_id) {
    echo json_encode(['success' => false, 'message' => 'Missing unit ID.']);
    exit();
}

// Check if already favorited
$check = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND videoke_id = ? LIMIT 1");
$check->execute([$user['id'], $videoke_id]);
$existing = $check->fetch();

if ($existing) {
    // Remove favorite
    $del = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND videoke_id = ?");
    $del->execute([$user['id'], $videoke_id]);
    echo json_encode(['success' => true, 'favorited' => false]);
} else {
    // Add favorite
    $ins = $pdo->prepare("INSERT INTO favorites (user_id, videoke_id) VALUES (?, ?)");
    $ins->execute([$user['id'], $videoke_id]);
    echo json_encode(['success' => true, 'favorited' => true]);
}