<?php
// =====================================================
// auth/logout.php
// Destroys the session and sends user to login page
// =====================================================
require_once '../config/db.php';
require_once '../config/auth.php';

if (isLoggedIn()) {
    logActivity($pdo, 'User logged out', 'auth', $_SESSION['user_id']);
}

// Destroy the entire session
session_destroy();

// Send user back to login
header('Location: /school_system/auth/login.php');
exit;
