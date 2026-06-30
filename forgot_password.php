<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

requireGuest();

$error   = '';
$success = '';
$step    = 'email'; // 'email' or 'reset'
$email   = '';

// ── STEP 1: Check if email exists ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === 'email') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
        $step  = 'email';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'No account found with that email address.';
            $step  = 'email';
        } else {
            $step = 'reset'; // email found, show password fields
        }
    }
}

// ── STEP 2: Save new password ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === 'reset') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // Re-verify email still exists (safety check)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Something went wrong. Please try again.';
        $step  = 'email';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
        $step  = 'reset';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
        $step  = 'reset';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->execute([$hashed, $user['id']]);

        $success = 'Password updated! Redirecting you to log in…';
        header('Refresh: 2; url=index.php');
        $step = 'done';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JKS Videoke — Forgot Password</title>
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

    <?php if ($step === 'done'): ?>

        <p class="form-title">Password Updated</p>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>

    <?php elseif ($step === 'email'): ?>

        <p class="form-title">Forgot Password</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <p style="font-size:13px; color:var(--text-muted); text-align:center; margin-bottom:20px; line-height:1.6;">
            Enter your registered email to reset your password.
        </p>

        <form method="POST" action="forgot_password.php">
            <input type="hidden" name="step" value="email">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                    autocomplete="email"
                >
            </div>

            <button type="submit" class="btn btn-primary">Continue</button>
        </form>

        <div class="divider"><span></span><p>Remembered it?</p><span></span></div>
        <a href="index.php" class="btn btn-ghost">Back to Log In</a>

    <?php elseif ($step === 'reset'): ?>

        <p class="form-title">Set New Password</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <p style="font-size:13px; color:var(--text-muted); text-align:center; margin-bottom:20px; line-height:1.6;">
            Enter a new password for <strong style="color:var(--text-main);"><?= htmlspecialchars($email) ?></strong>.
        </p>

        <form method="POST" action="forgot_password.php">
            <input type="hidden" name="step"  value="reset">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

            <div class="form-group">
                <label for="password">New Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="At least 6 characters"
                    required
                    autocomplete="new-password"
                    minlength="6"
                >
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    placeholder="••••••••"
                    required
                    autocomplete="new-password"
                >
            </div>

            <!-- Live match indicator -->
            <p id="matchMsg" style="font-size:12px; margin-top:-8px; margin-bottom:14px; min-height:18px;"></p>

            <button type="submit" class="btn btn-primary" id="submitBtn">Update Password</button>
        </form>

        <div class="divider"><span></span><p>or</p><span></span></div>
        <a href="forgot_password.php" class="btn btn-ghost">Use a Different Email</a>

    <?php endif; ?>

</div>

<script>
const pw  = document.getElementById('password');
const pw2 = document.getElementById('password_confirm');
const msg = document.getElementById('matchMsg');
const btn = document.getElementById('submitBtn');

if (pw2) {
    [pw, pw2].forEach(el => el.addEventListener('input', checkMatch));
}

function checkMatch() {
    if (!pw2.value) { msg.textContent = ''; return; }
    if (pw.value === pw2.value) {
        msg.textContent = '✓ Passwords match';
        msg.style.color = '#4CAF88';
        if (btn) btn.disabled = false;
    } else {
        msg.textContent = '✗ Passwords do not match';
        msg.style.color = '#E05C5C';
        if (btn) btn.disabled = true;
    }
}
</script>

</body>
</html>