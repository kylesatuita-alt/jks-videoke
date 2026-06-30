<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireLogin();
$user = getCurrentUser();

if ($user['role'] !== 'admin') {
    header('Location: ../home.php');
    exit();
}