<?php
// =====================================================
// auth/login.php
// The login page - first page every user sees
// =====================================================
require_once '../config/db.php';
require_once '../config/auth.php';

// If already logged in, go to dashboard
if (isLoggedIn()) {
    redirect('/school_system/index.php');
}

$error = '';

// -------------------------------------------------------
// Handle form submission (when user clicks Login button)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Find the user by email - using prepared statement (safe from SQL injection)
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name, r.slug as role_slug
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.email = ? AND u.is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // password_verify() checks if the entered password matches the stored hash
        if ($user && password_verify($password, $user['password'])) {
            // Login successful! Save user info in session
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role_id']    = $user['role_id'];
            $_SESSION['role_name']  = $user['role_name'];
            $_SESSION['role_slug']  = $user['role_slug'];

            // Log this login
            logActivity($pdo, 'User logged in', 'auth', $user['id']);

            redirect('/school_system/index.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — School Management System</title>
    <!-- Bootstrap 5 CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .login-header {
            background: #1a237e;
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .btn-login {
            background: #1a237e;
            color: white;
            border: none;
            padding: 0.75rem;
            font-size: 1rem;
            border-radius: 8px;
        }
        .btn-login:hover { background: #283593; color: white; }
    </style>
</head>
<body>
    <div class="login-card card">
        <div class="login-header">
            <i class="bi bi-building" style="font-size: 2.5rem;"></i>
            <h4 class="mt-2 mb-0">School Management System</h4>
            <small class="opacity-75">Sign in to your account</small>
        </div>
        <div class="login-body">

            <?php if ($error): ?>
                <!-- This shows a red error box if login fails -->
                <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            placeholder="admin@school.com"
                            value="<?= sanitize($_POST['email'] ?? '') ?>"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                        >
                    </div>
                </div>

                <button type="submit" class="btn btn-login w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>

            <div class="text-center mt-3">
                <small class="text-muted">
                    Default login: admin@school.com / Admin@123
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
