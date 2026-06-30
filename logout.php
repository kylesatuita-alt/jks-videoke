<?php
require_once 'includes/auth.php';

// Destroy session and redirect to login
$_SESSION = [];
session_destroy();

header('Location: index.php');
exit();
?>
