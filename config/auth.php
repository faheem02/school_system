<?php
// =====================================================
// config/auth.php
// Helper functions used everywhere in the system
// =====================================================

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------
// isLoggedIn()
// Returns true if user is logged in, false if not
// -------------------------------------------------------
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// -------------------------------------------------------
// requireLogin()
// Call this at the top of any protected page.
// If user is not logged in, redirect to login page.
// -------------------------------------------------------
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /school_system/auth/login.php');
        exit;
    }
}

// -------------------------------------------------------
// hasPermission($pdo, $module, $action)
// Check if the logged-in user can do $action on $module
//
// Example: hasPermission($pdo, 'students', 'can_add')
// Returns: true or false
// -------------------------------------------------------
function hasPermission($pdo, $module, $action) {
    // Super Admin always has full access - no need to check DB
    if ($_SESSION['role_slug'] === 'super_admin') {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT $action FROM permissions
        WHERE role_id = ? AND module = ?
    ");
    $stmt->execute([$_SESSION['role_id'], $module]);
    $row = $stmt->fetch();

    return $row && $row[$action] == 1;
}

// -------------------------------------------------------
// requirePermission($pdo, $module, $action)
// If user does NOT have permission, stop them and show error
// -------------------------------------------------------
function requirePermission($pdo, $module, $action) {
    requireLogin();
    if (!hasPermission($pdo, $module, $action)) {
        http_response_code(403);
        include __DIR__ . '/../templates/403.php';
        exit;
    }
}

// -------------------------------------------------------
// logActivity($pdo, $action, $module, $record_id)
// Saves a record of what action the user did
// -------------------------------------------------------
function logActivity($pdo, $action, $module, $record_id = null) {
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, module, record_id, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $action,
        $module,
        $record_id,
        $_SERVER['REMOTE_ADDR']
    ]);
}

// -------------------------------------------------------
// sanitize($input)
// Cleans user input to prevent XSS attacks
// Always use this before displaying user input on screen
// -------------------------------------------------------
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// -------------------------------------------------------
// redirect($url)
// Simple redirect helper
// -------------------------------------------------------
function redirect($url) {
    header("Location: $url");
    exit;
}

// -------------------------------------------------------
// setFlash($type, $message)
// getFlash()
// Flash messages: show a success or error message once
// -------------------------------------------------------
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
