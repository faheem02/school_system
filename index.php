<?php
// =====================================================
// index.php - The Dashboard
// This is what every user sees after login
// =====================================================
require_once 'config/db.php';
require_once 'config/auth.php';

requireLogin(); // redirect to login if not logged in

$pageTitle = 'Dashboard';

// -------------------------------------------------------
// Get counts for the dashboard cards
// Only count what this user is allowed to see
// -------------------------------------------------------
$totalStudents = 0;
$totalTeachers = 0;
$totalInvoices = 0;
$totalStaff    = 0;

if (hasPermission($pdo, 'students', 'can_view')) {
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
}
if (hasPermission($pdo, 'teachers', 'can_view')) {
    $totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers WHERE status='active'")->fetchColumn();
}
if (hasPermission($pdo, 'invoices', 'can_view')) {
    $totalInvoices = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
    $unpaidAmount  = $pdo->query("SELECT COALESCE(SUM(amount - paid_amount),0) FROM invoices WHERE status != 'paid'")->fetchColumn();
}
if (hasPermission($pdo, 'staff', 'can_view')) {
    $totalStaff = $pdo->query("SELECT COUNT(*) FROM staff WHERE status='active'")->fetchColumn();
}

// Recent activity (last 8 actions)
$recentActivity = $pdo->query("
    SELECT l.*, u.name as user_name
    FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 8
")->fetchAll();

include 'templates/layout_top.php';
?>

<!-- ===================== STAT CARDS ===================== -->
<div class="row g-3 mb-4">

    <?php if (hasPermission($pdo, 'students', 'can_view')): ?>
    <div class="col-sm-6 col-xl-3">
        <a href="/school_system/modules/students/index.php" class="text-decoration-none">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e8eaf6;">
                    <i class="bi bi-people" style="color:#3949ab;"></i>
                </div>
                <div>
                    <div class="stat-num"><?= number_format($totalStudents) ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <?php if (hasPermission($pdo, 'teachers', 'can_view')): ?>
    <div class="col-sm-6 col-xl-3">
        <a href="/school_system/modules/teachers/index.php" class="text-decoration-none">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e0f2f1;">
                    <i class="bi bi-person-badge" style="color:#00796b;"></i>
                </div>
                <div>
                    <div class="stat-num"><?= number_format($totalTeachers) ?></div>
                    <div class="stat-label">Total Teachers</div>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <?php if (hasPermission($pdo, 'invoices', 'can_view')): ?>
    <div class="col-sm-6 col-xl-3">
        <a href="/school_system/modules/invoices/index.php" class="text-decoration-none">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff3e0;">
                    <i class="bi bi-receipt" style="color:#e65100;"></i>
                </div>
                <div>
                    <div class="stat-num"><?= number_format($totalInvoices) ?></div>
                    <div class="stat-label">Total Invoices</div>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <?php if (hasPermission($pdo, 'staff', 'can_view')): ?>
    <div class="col-sm-6 col-xl-3">
        <a href="/school_system/modules/staff/index.php" class="text-decoration-none">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fce4ec;">
                    <i class="bi bi-person-workspace" style="color:#c62828;"></i>
                </div>
                <div>
                    <div class="stat-num"><?= number_format($totalStaff) ?></div>
                    <div class="stat-label">Total Staff</div>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

</div>

<!-- ===================== RECENT ACTIVITY ===================== -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="data-table">
            <div class="table-toolbar">
                <strong>Recent Activity</strong>
            </div>
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivity as $log): ?>
                    <tr>
                        <td><?= sanitize($log['user_name'] ?? 'System') ?></td>
                        <td><?= sanitize($log['action']) ?></td>
                        <td><span class="badge bg-light text-dark text-capitalize"><?= sanitize($log['module']) ?></span></td>
                        <td class="text-muted" style="font-size:0.82rem;"><?= date('d M, H:i', strtotime($log['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentActivity)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No activity yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Info Panel -->
    <div class="col-lg-4">
        <div class="data-table p-0">
            <div class="table-toolbar"><strong>Logged in as</strong></div>
            <div class="p-3">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width:48px;height:48px;background:#1a237e;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1.1rem;">
                        <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="fw-semibold"><?= sanitize($_SESSION['user_name']) ?></div>
                        <div class="text-muted" style="font-size:0.82rem;"><?= sanitize($_SESSION['user_email']) ?></div>
                    </div>
                </div>
                <div class="mb-2">
                    <span class="badge" style="background:#e8eaf6;color:#3949ab;font-size:0.82rem;">
                        <i class="bi bi-shield me-1"></i><?= sanitize($_SESSION['role_name']) ?>
                    </span>
                </div>
                <a href="/school_system/portal/my_permissions.php" class="btn btn-outline-primary btn-sm w-100 mt-2">
                    <i class="bi bi-key me-1"></i> View My Permissions
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/layout_bottom.php'; ?>
