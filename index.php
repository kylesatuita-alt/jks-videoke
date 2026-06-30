<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Already logged in? Go to home
requireGuest();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = [
                'id'       => $user['id'],
                'name'     => $user['name'],
                'email'    => $user['email'],
                'role'     => $user['role'],
            ];
            $redirect = $user['role'] === 'admin' ? 'admin/dashboard.php' : 'home.php';
            header('Location: ' . $redirect);
            exit();
        } else {
            $error = 'Incorrect email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JKS Videoke — Log In</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="card">

    <!-- Brand -->
    <div class="brand">
        <div class="brand-logo">
            <svg viewBox="0 0 70 70" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Glow ring -->
                <circle cx="35" cy="35" r="33" stroke="#F5C518" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.4"/>
                <!-- Mic body -->
                <rect x="27" y="10" width="16" height="26" rx="8" fill="#F5C518"/>
                <!-- Mic stand arc -->
                <path d="M20 34 C20 48 50 48 50 34" stroke="#F5C518" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                <!-- Stand stem -->
                <line x1="35" y1="47" x2="35" y2="57" stroke="#F5C518" stroke-width="2.5" stroke-linecap="round"/>
                <!-- Stand base -->
                <line x1="26" y1="57" x2="44" y2="57" stroke="#F5C518" stroke-width="2.5" stroke-linecap="round"/>
                <!-- Sound lines -->
                <path d="M55 22 C58 25 58 29 55 32" stroke="#F5C518" stroke-width="1.8" stroke-linecap="round" fill="none" opacity="0.6"/>
                <path d="M59 18 C64 23 64 31 59 36" stroke="#F5C518" stroke-width="1.5" stroke-linecap="round" fill="none" opacity="0.35"/>
            </svg>
        </div>
        <h1>JKS Videoke</h1>
        <p>For Rent · Delivered to You</p>
    </div>

    <p class="form-title">Welcome Back</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php">
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

        <div class="form-group">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                <label for="password" style="margin-bottom:0;">Password</label>
                <a href="forgot_password.php" style="font-size:11px; color:var(--text-muted); text-decoration:none; letter-spacing:0.3px; transition:color 0.2s;"
                   onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color='var(--text-muted)'">
                    Forgot password?
                </a>
            </div>
            <div style="position:relative;">
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                    style="padding-right: 44px;"
                >
                <button type="button" id="togglePass"
                    style="position:absolute; right:12px; top:50%; transform:translateY(-50%);
                           background:none; border:none; cursor:pointer; padding:4px; color:var(--text-muted);
                           display:flex; align-items:center; -webkit-tap-highlight-color:transparent;"
                    aria-label="Show password">
                    <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Log In</button>
    </form>

    <div class="divider"><span></span><p>Don't have an account?</p><span></span></div>

    <a href="register.php" class="btn btn-ghost">Create an Account</a>

</div>

<script src="js/main.js"></script>
<script>
// Show / hide password toggle
const togglePass = document.getElementById('togglePass');
const passwordInput = document.getElementById('password');
const eyeIcon = document.getElementById('eyeIcon');

const eyeOpen  = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
const eyeClosed = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>`;

togglePass?.addEventListener('click', () => {
    const isHidden = passwordInput.type === 'password';
    passwordInput.type = isHidden ? 'text' : 'password';
    eyeIcon.innerHTML  = isHidden ? eyeClosed : eyeOpen;
    togglePass.style.color = isHidden ? 'var(--gold)' : 'var(--text-muted)';
});
</script>
</html>