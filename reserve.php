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

$videoke_id  = (int)($_POST['videoke_id']  ?? 0);
$start_date  = trim($_POST['start_date']   ?? '');
$end_date    = trim($_POST['end_date']     ?? '');
$notes       = trim($_POST['notes']        ?? '');
$barangay    = trim($_POST['barangay']     ?? '');
$sitio       = trim($_POST['sitio']        ?? '');
$sitio_other = trim($_POST['sitio_other']  ?? '');
$phone       = trim($_POST['phone']        ?? '');

// Resolve "Other" sitio
$sitio_final = ($sitio === 'Other') ? $sitio_other : $sitio;

// ── Basic validation ──
if (!$videoke_id || !$start_date || !$end_date || !$barangay || !$sitio || !$phone) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit();
}

if ($sitio === 'Other' && !$sitio_other) {
    echo json_encode(['success' => false, 'message' => 'Please enter your sitio/purok name.']);
    exit();
}

if ($start_date < date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Start date cannot be in the past.']);
    exit();
}

if ($end_date <= $start_date) {
    echo json_encode(['success' => false, 'message' => 'End date must be after start date.']);
    exit();
}

// ── Check unit exists ──
$unit = $pdo->prepare("SELECT * FROM videokes WHERE id = ? LIMIT 1");
$unit->execute([$videoke_id]);
$videoke = $unit->fetch();

if (!$videoke) {
    echo json_encode(['success' => false, 'message' => 'Videoke unit not found.']);
    exit();
}

// ── Check availability ──
$conflict = $pdo->prepare("
    SELECT id FROM reservations
    WHERE videoke_id = ?
      AND status IN ('pending','confirmed','active','delivering')
      AND start_date <= ? AND end_date >= ?
    LIMIT 1
");
$conflict->execute([$videoke_id, $end_date, $start_date]);

if ($conflict->fetch()) {
    echo json_encode(['success' => false, 'message' => 'This unit is already reserved for those dates. Please choose different dates.']);
    exit();
}

// ── Get delivery fee ──
$feeStmt = $pdo->prepare("
    SELECT delivery_fee FROM delivery_areas
    WHERE barangay = ? AND sitio = ? LIMIT 1
");
$feeStmt->execute([$barangay, $sitio]);
$feeRow = $feeStmt->fetch();

if (!$feeRow) {
    echo json_encode(['success' => false, 'message' => 'Could not determine delivery fee. Please re-select your location.']);
    exit();
}

// delivery_fee IS the all-in total price (rental + delivery bundled)
$total_price = (float)$feeRow['delivery_fee'];

// ── Insert reservation ──
$notesFull = "Phone: $phone | Barangay: $barangay | Sitio: $sitio_final" . ($notes ? " | Notes: $notes" : '');

$insert = $pdo->prepare("
    INSERT INTO reservations (user_id, videoke_id, start_date, end_date, status, total_price, notes)
    VALUES (?, ?, ?, ?, 'pending', ?, ?)
");
$insert->execute([
    $user['id'],
    $videoke_id,
    $start_date,
    $end_date,
    $total_price,
    $notesFull
]);

echo json_encode([
    'success' => true,
    'message' => 'Reservation submitted! We will contact you shortly.',
    'total'   => $total_price
]);