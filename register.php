<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

requireGuest();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $location = trim($_POST['location'] ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    // ── Validation ──
    if (empty($name) || empty($email) || empty($phone) || empty($location) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Capitalize each word of the name (server-side safety)
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'That email is already registered. Please log in.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("
                INSERT INTO users (name, email, phone, location, password, role)
                VALUES (?, ?, ?, ?, ?, 'user')
            ");
            $insert->execute([$name, $email, $phone, $location, $hash]);

            $success = 'Account created! Redirecting you to log in…';
            header('Refresh: 2; url=index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JKS Videoke — Register</title>
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

    <p class="form-title">Create an Account</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">

        <div class="form-group">
            <label for="name">Full Name</label>
            <input
                type="text"
                id="name"
                name="name"
                placeholder="Juan Dela Cruz"
                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                required
                autocomplete="name"
            >
        </div>

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
            <label for="phone">Phone Number</label>
            <input
                type="tel"
                id="phone"
                name="phone"
                placeholder="09XX XXX XXXX"
                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                required
                autocomplete="tel"
            >
        </div>

        <div class="form-group">
            <label for="location">Location</label>
            <input
                type="text"
                id="location"
                name="location"
                placeholder="e.g. Brgy. San Jose, Iloilo City"
                value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="At least 6 characters"
                required
                autocomplete="new-password"
            >
        </div>

        <div class="form-group">
            <label for="confirm">Confirm Password</label>
            <input
                type="password"
                id="confirm"
                name="confirm"
                placeholder="Repeat password"
                required
                autocomplete="new-password"
            >
        </div>

        <button type="submit" class="btn btn-primary">Create Account</button>
    </form>

    <div class="divider"><span></span><p>Already have an account?</p><span></span></div>

    <a href="index.php" class="btn btn-ghost">Back to Log In</a>

</div>

<script src="js/main.js"></script>
</body>
</html>
