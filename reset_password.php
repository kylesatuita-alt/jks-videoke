<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

requireGuest();

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';
$validToken = false;
$user = null;

// Validate token on every load
if (empty($token)) {
    $error = 'Invalid or missing reset link. Please request a new one.';
} else {
    $stmt = $pdo->prepare("
        SELECT id, name, email
        FROM users
        WHERE reset_token = ?
          AND reset_token_expires > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'This reset link has expired or is invalid. Please request a new one.';
    } else {
        $validToken = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['password']     ?? '';
    $confirmPass = $_POST['password_confirm'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPass) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        $upd = $pdo->prepare("
            UPDATE users
            SET password = ?, reset_token = NULL, reset_token_expires = NULL
            WHERE id = ?
        ");
        $upd->execute([$hashed, $user['id']]);

        $success = 'Your password has been reset. You can now log in.';
        $validToken = false; // hide the form
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JKS Videoke — Reset Password</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="card">

    <!-- Brand -->
    <div class="brand">
        <div class="brand-logo">
            <svg viewBox="0 0 70 70" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="35" cy="35" r="33" stroke="#F5C518" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.4"/>
                <rect x="27" y="10" width="16" height="26" rx="8" fill="#F5C518"/>
                <path d="M20 34 C20 48 50 48 50 34" stroke="#F5C518" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                <line x1="35" y1="47" x2="35" y2="57" stroke="#F5C518" stroke-width="2.5" stroke-linecap="round"/>
                <line x1="26" y1="57" x2="44" y2="57" stroke="#F5C518" stroke-width="2.5" stroke-linecap="round"/>
                <path d="M55 22 C58 25 58 29 55 32" stroke="#F5C518" stroke-width="1.8" stroke-linecap="round" fill="none" opacity="0.6"/>
                <path d="M59 18 C64 23 64 31 59 36" stroke="#F5C518" stroke-width="1.5" stroke-linecap="round" fill="none" opacity="0.35"/>
            </svg>
        </div>
        <h1>JKS Videoke</h1>
        <p>For Rent · Delivered to You</p>
    </div>

    <p class="form-title">Reset Password</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <a href="forgot_password.php" class="btn btn-ghost" style="margin-top:8px;">Request New Link</a>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <a href="index.php" class="btn btn-primary" style="margin-top:8px;">Log In</a>
    <?php endif; ?>

    <?php if ($validToken): ?>

        <p style="font-size:13px; color:var(--text-muted); text-align:center; margin-bottom:20px; line-height:1.6;">
            Hi <strong style="color:var(--text-main);"><?= htmlspecialchars($user['name']) ?></strong>,
            enter your new password below.
        </p>

        <form method="POST" action="reset_password.php?token=<?= htmlspecialchars($token) ?>">

            <div class="form-group">
                <label for="password">New Password</label>
                <div style="position:relative;">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="At least 8 characters"
                        required
                        autocomplete="new-password"
                        minlength="8"
                        style="padding-right:44px;"
                    >
                    <button type="button" class="eye-toggle" data-target="password"
                        style="position:absolute; right:12px; top:50%; transform:translateY(-50%);
                               background:none; border:none; cursor:pointer; padding:4px; color:var(--text-muted);
                               display:flex; align-items:center; -webkit-tap-highlight-color:transparent;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm New Password</label>
                <div style="position:relative;">
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        placeholder="••••••••"
                        required
                        autocomplete="new-password"
                        style="padding-right:44px;"
                    >
                    <button type="button" class="eye-toggle" data-target="password_confirm"
                        style="position:absolute; right:12px; top:50%; transform:translateY(-50%);
                               background:none; border:none; cursor:pointer; padding:4px; color:var(--text-muted);
                               display:flex; align-items:center; -webkit-tap-highlight-color:transparent;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Live match indicator -->
            <p id="matchMsg" style="font-size:12px; margin-top:-8px; margin-bottom:14px; display:none;"></p>

            <button type="submit" class="btn btn-primary" id="submitBtn">Set New Password</button>

        </form>

    <?php endif; ?>

</div>

<script>
// Show / hide password toggles
const eyeOpen   = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
const eyeClosed = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>`;

document.querySelectorAll('.eye-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.target);
        if (!input) return;
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.querySelector('svg').innerHTML = isHidden ? eyeClosed : eyeOpen;
        btn.style.color = isHidden ? 'var(--gold)' : 'var(--text-muted)';
    });
});

// Live password match indicator
const pw  = document.getElementById('password');
const pw2 = document.getElementById('password_confirm');
const msg = document.getElementById('matchMsg');
const btn = document.getElementById('submitBtn');

if (pw2) {
    pw2.addEventListener('input', checkMatch);
    pw && pw.addEventListener('input', checkMatch);
}

function checkMatch() {
    if (!pw2.value) { msg.style.display = 'none'; return; }
    msg.style.display = 'block';
    if (pw.value === pw2.value) {
        msg.textContent = '✓ Passwords match';
        msg.style.color = 'var(--success, #4CAF88)';
        btn && (btn.disabled = false);
    } else {
        msg.textContent = '✗ Passwords do not match';
        msg.style.color = 'var(--danger, #E05C5C)';
        btn && (btn.disabled = true);
    }
}
</script>

</body>
</html>