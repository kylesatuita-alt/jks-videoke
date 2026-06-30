<?php
// Reads from environment variables first (used on real hosting / GitHub Actions / etc).
// Falls back to local XAMPP defaults if env vars aren't set, so local dev still works as-is.
$host     = getenv('DB_HOST')     ?: 'localhost';
$dbname   = getenv('DB_NAME')     ?: 'jks_videoke';
$username = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASS')     ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}
?>