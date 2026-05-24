<?php
// =====================================================
// config/db.php
// Database connection using PDO
// PDO is safer than old mysql_ functions
// =====================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'school_system');
define('DB_USER', 'root');        // default XAMPP username
define('DB_PASS', '');            // default XAMPP password is empty

try {
    // PDO connection - this is the safe, modern way to connect
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS
    );

    // This makes PDO throw errors instead of silently failing
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // This makes fetched rows return as arrays with column names
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // If connection fails, stop everything and show the error
    die("Database connection failed: " . $e->getMessage());
}
