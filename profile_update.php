<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');
requireLogin();

$user   = getCurrentUser();
$action = trim($_POST['action'] ?? 'info');

// ── UPLOAD AVATAR ─────────────────────────────────────────────
if ($action === 'avatar') {
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file received or upload error.']);
        exit();
    }

    $file    = $_FILES['avatar'];
    $maxSize = 5 * 1024 * 1024; // 5 MB
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'Image must be under 5 MB.']);
        exit();
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, or WebP images are allowed.']);
        exit();
    }

    // Make sure uploads/avatars/ folder exists
    $uploadDir = __DIR__ . '/uploads/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Delete old avatar file from disk
    $oldRow = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
    $oldRow->execute([$user['id']]);
    $oldAvatar = $oldRow->fetchColumn();
    if ($oldAvatar && file_exists(__DIR__ . '/' . $oldAvatar)) {
        unlink(__DIR__ . '/' . $oldAvatar);
    }

    // Save new file
    $ext      = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$mimeType];
    $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
    $savePath = $uploadDir . $filename;
    $dbPath   = 'uploads/avatars/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $savePath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save image. Check folder permissions.']);
        exit();
    }

    // Save path to DB and session
    $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$dbPath, $user['id']]);
    $_SESSION['user']['avatar'] = $dbPath;

    echo json_encode([
        'success'   => true,
        'message'   => 'Profile photo updated!',
        'avatarUrl' => $dbPath,
    ]);
    exit();
}

// ── REMOVE AVATAR ──────────────────────────────────────────────
if ($action === 'remove_avatar') {
    $oldRow = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
    $oldRow->execute([$user['id']]);
    $oldAvatar = $oldRow->fetchColumn();

    if ($oldAvatar && file_exists(__DIR__ . '/' . $oldAvatar)) {
        unlink(__DIR__ . '/' . $oldAvatar);
    }

    $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?")->execute([$user['id']]);
    $_SESSION['user']['avatar'] = null;

    echo json_encode(['success' => true, 'message' => 'Profile photo removed.']);
    exit();
}

// ── UPDATE PROFILE INFO ────────────────────────────────────────
if ($action === 'info') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $location = trim($_POST['location'] ?? '');

    if (empty($name) || empty($email) || empty($phone) || empty($location)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit();
    }

    $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $check->execute([$email, $user['id']]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'That email is already used by another account.']);
        exit();
    }

    $upd = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, location=? WHERE id=?");
    $upd->execute([$name, $email, $phone, $location, $user['id']]);

    $_SESSION['user']['name']  = $name;
    $_SESSION['user']['email'] = $email;

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully!',
        'name'    => $name,
        'email'   => $email,
        'initial' => strtoupper(substr($name, 0, 1)),
    ]);
    exit();
}

// ── CHANGE PASSWORD ────────────────────────────────────────────
if ($action === 'password') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
        exit();
    }

    if (strlen($new) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
        exit();
    }

    if ($new !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        exit();
    }

    $row = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $row->execute([$user['id']]);
    $data = $row->fetch();

    if (!password_verify($current, $data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit();
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $user['id']]);

    echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);