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

$action     = trim($_POST['action']     ?? 'add');
$videoke_id = (int)($_POST['videoke_id'] ?? 0);

if (!$videoke_id) {
    echo json_encode(['success' => false, 'message' => 'Missing unit ID.']);
    exit();
}

// ── REMOVE from cart ──
if ($action === 'remove') {
    $del = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND videoke_id = ?");
    $del->execute([$user['id'], $videoke_id]);
    echo json_encode(['success' => true]);
    exit();
}

// ── ADD to cart ──
$start_date = trim($_POST['start_date'] ?? '');
$end_date   = trim($_POST['end_date']   ?? '');

if (!$start_date || !$end_date) {
    echo json_encode(['success' => false, 'message' => 'Missing dates.']);
    exit();
}

// Check unit exists and is available
$unit = $pdo->prepare("SELECT * FROM videokes WHERE id = ? LIMIT 1");
$unit->execute([$videoke_id]);
$videoke = $unit->fetch();

if (!$videoke) {
    echo json_encode(['success' => false, 'message' => 'Unit not found.']);
    exit();
}

// Prevent duplicates in cart
$existing = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND videoke_id = ? LIMIT 1");
$existing->execute([$user['id'], $videoke_id]);
if ($existing->fetch()) {
    echo json_encode(['success' => false, 'message' => 'This unit is already in your cart.']);
    exit();
}

// Insert to cart
$ins = $pdo->prepare("
    INSERT INTO cart (user_id, videoke_id, start_date, end_date)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), end_date = VALUES(end_date)
");
$ins->execute([$user['id'], $videoke_id, $start_date, $end_date]);

echo json_encode([
    'success'    => true,
    'unit_number'=> $videoke['unit_number'],
    'name'       => $videoke['name'],
    'price'      => $videoke['price_3days']
]);
