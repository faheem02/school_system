<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'School Management System' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* ---- Layout ---- */
        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }

        /* Sidebar */
        #sidebar {
            width: 250px;
            min-height: 100vh;
            background: #1a237e;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
            transition: width 0.3s;
        }
        #sidebar .brand {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        #sidebar .brand h6 { color: white; margin: 0; font-size: 0.95rem; }
        #sidebar .brand small { color: rgba(255,255,255,0.6); font-size: 0.75rem; }

        /* Sidebar nav links */
        #sidebar .nav-link {
            color: rgba(255,255,255,0.75);
            padding: 0.65rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            border-radius: 0;
            transition: all 0.2s;
        }
        #sidebar .nav-link:hover,
        #sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.12);
            border-left: 3px solid #90caf9;
        }
        #sidebar .nav-section {
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 1rem 1.5rem 0.25rem;
        }

        /* Main content area (shifted right so sidebar doesn't cover it) */
        #main-content {
            margin-left: 250px;
            min-height: 100vh;
        }

        /* Top navbar */
        #topbar {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        /* Page content padding */
        .page-content { padding: 1.5rem; }

        /* Dashboard stat cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e8eaf0;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }
        .stat-card .stat-num { font-size: 1.75rem; font-weight: 700; line-height: 1; }
        .stat-card .stat-label { color: #666; font-size: 0.85rem; margin-top: 2px; }

        /* Data tables */
        .data-table { background: white; border-radius: 12px; border: 1px solid #e8eaf0; overflow: hidden; }
        .data-table .table { margin: 0; }
        .data-table .table thead th {
            background: #f8f9ff;
            border-bottom: 1px solid #e8eaf0;
            color: #444;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .data-table .table-toolbar {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e8eaf0;
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Badges */
        .badge-active   { background: #e8f5e9; color: #2e7d32; font-weight: 500; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; }
        .badge-inactive { background: #fce4ec; color: #c62828; font-weight: 500; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; }
        .badge-paid     { background: #e8f5e9; color: #2e7d32; font-weight: 500; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; }
        .badge-unpaid   { background: #fff3e0; color: #e65100; font-weight: 500; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; }
        .badge-partial  { background: #e3f2fd; color: #1565c0; font-weight: 500; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; }

        /* Flash messages */
        .flash-message { position: fixed; top: 1rem; right: 1rem; z-index: 9999; min-width: 300px; }

        /* Permission denied icon */
        .perm-denied { color: #ccc; font-size: 0.85rem; }
    </style>
</head>
<body>

<!-- ===================== SIDEBAR ===================== -->
<div id="sidebar">
    <div class="brand">
        <h6><i class="bi bi-building me-2"></i>School System</h6>
        <small><?= sanitize($_SESSION['role_name'] ?? '') ?></small>
    </div>

    <nav class="mt-2">
        <div class="nav-section">Main</div>

        <a href="/school_system/index.php"
           class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <?php if (hasPermission($pdo, 'students', 'can_view')): ?>
        <a href="/school_system/modules/students/index.php"
           class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'students') !== false) ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Students
        </a>
        <?php endif; ?>

        <?php if (hasPermission($pdo, 'teachers', 'can_view')): ?>
        <a href="/school_system/modules/teachers/index.php"
           class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'teachers') !== false) ? 'active' : '' ?>">
            <i class="bi bi-person-badge"></i> Teachers
        </a>
        <?php endif; ?>

        <?php if (hasPermission($pdo, 'invoices', 'can_view')): ?>
        <a href="/school_system/modules/invoices/index.php"
           class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'invoices') !== false) ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> Invoices
        </a>
        <?php endif; ?>

        <?php if (hasPermission($pdo, 'staff', 'can_view')): ?>
        <a href="/school_system/modules/staff/index.php"
           class="nav-link <?= (strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? 'active' : '' ?>">
            <i class="bi bi-person-workspace"></i> Staff
        </a>
        <?php endif; ?>

        <?php if ($_SESSION['role_slug'] === 'super_admin'): ?>
        <div class="nav-section">Admin</div>
        <a href="/school_system/admin/users.php"
           class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'users') !== false) ? 'active' : '' ?>">
            <i class="bi bi-shield-lock"></i> User Management
        </a>
        <a href="/school_system/admin/reports.php"
           class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'reports') !== false) ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Reports
        </a>
        <?php endif; ?>

        <div class="nav-section">Account</div>
        <a href="/school_system/portal/my_permissions.php" class="nav-link">
            <i class="bi bi-key"></i> My Permissions
        </a>
        <a href="/school_system/auth/logout.php" class="nav-link">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </nav>
</div>

<!-- ===================== TOP BAR ===================== -->
<div id="main-content">
    <div id="topbar">
        <h6 class="mb-0 fw-semibold text-dark"><?= $pageTitle ?? 'Dashboard' ?></h6>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end">
                <div style="font-size:0.85rem; font-weight:600;"><?= sanitize($_SESSION['user_name'] ?? '') ?></div>
                <div style="font-size:0.75rem; color:#888;"><?= sanitize($_SESSION['role_name'] ?? '') ?></div>
            </div>
            <div style="width:36px;height:36px;background:#1a237e;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.85rem;">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
            </div>
        </div>
    </div>

    <!-- Flash message display -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="flash-message">
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show shadow" role="alert">
            <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
            <?= sanitize($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>

    <div class="page-content">
