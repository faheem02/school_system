<?php
// =====================================================
// admin/reports.php
// Reports page — Super Admin only
// Shows financial summary, module stats, activity log
// =====================================================
require_once '../config/db.php';
require_once '../config/auth.php';

requireLogin();

if ($_SESSION['role_slug'] !== 'super_admin') {
    http_response_code(403);
    include '../templates/403.php';
    exit;
}

$pageTitle = 'Reports';

// ---- Overall counts ----
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers WHERE status='active'")->fetchColumn();
$totalStaff    = $pdo->query("SELECT COUNT(*) FROM staff WHERE status='active'")->fetchColumn();
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();

// ---- Financial summary ----
$finance = $pdo->query("
    SELECT
        COUNT(*)                        AS total_invoices,
        COALESCE(SUM(amount),0)         AS total_billed,
        COALESCE(SUM(paid_amount),0)    AS total_collected,
        COALESCE(SUM(amount-paid_amount),0) AS total_outstanding,
        SUM(status='paid')              AS paid_count,
        SUM(status='unpaid')            AS unpaid_count,
        SUM(status='partial')           AS partial_count
    FROM invoices
")->fetch();

// ---- Monthly collection (last 6 months) ----
$monthly = $pdo->query("
    SELECT
        DATE_FORMAT(created_at, '%b %Y') AS month,
        COUNT(*) AS invoices_created,
        COALESCE(SUM(paid_amount),0) AS collected
    FROM invoices
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY MIN(created_at) ASC
")->fetchAll();

// ---- Top fee payers (students with most paid) ----
$topPayers = $pdo->query("
    SELECT s.name, s.student_id, SUM(i.paid_amount) AS total_paid, COUNT(i.id) AS inv_count
    FROM invoices i
    JOIN students s ON i.student_id = s.id
    GROUP BY s.id
    ORDER BY total_paid DESC
    LIMIT 5
")->fetchAll();

// ---- Outstanding fees by student ----
$outstanding = $pdo->query("
    SELECT s.name, s.student_id, SUM(i.amount - i.paid_amount) AS due
    FROM invoices i
    JOIN students s ON i.student_id = s.id
    WHERE i.status != 'paid'
    GROUP BY s.id
    ORDER BY due DESC
    LIMIT 5
")->fetchAll();

// ---- Recent activity log ----
$activityLog = $pdo->query("
    SELECT l.*, u.name AS user_name, r.name AS role_name
    FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN roles r ON u.role_id = r.id
    ORDER BY l.created_at DESC
    LIMIT 20
")->fetchAll();

include '../templates/layout_top.php';
?>

<!-- ===== OVERVIEW CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8eaf6;"><i class="bi bi-people" style="color:#3949ab;"></i></div>
            <div><div class="stat-num"><?= $totalStudents ?></div><div class="stat-label">Active Students</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e0f2f1;"><i class="bi bi-person-badge" style="color:#00796b;"></i></div>
            <div><div class="stat-num"><?= $totalTeachers ?></div><div class="stat-label">Active Teachers</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fce4ec;"><i class="bi bi-person-workspace" style="color:#c62828;"></i></div>
            <div><div class="stat-num"><?= $totalStaff ?></div><div class="stat-label">Active Staff</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f3e5f5;"><i class="bi bi-shield-check" style="color:#6a1b9a;"></i></div>
            <div><div class="stat-num"><?= $totalUsers ?></div><div class="stat-label">System Users</div></div>
        </div>
    </div>
</div>

<!-- ===== FINANCIAL REPORT ===== -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="data-table p-0 h-100">
            <div class="table-toolbar"><strong><i class="bi bi-bar-chart-line me-2"></i>Financial Summary</strong></div>
            <div class="p-3">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Total Invoiced</span>
                    <strong>PKR <?= number_format($finance['total_billed']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Total Collected</span>
                    <strong class="text-success">PKR <?= number_format($finance['total_collected']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Outstanding Due</span>
                    <strong class="text-danger">PKR <?= number_format($finance['total_outstanding']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Paid Invoices</span>
                    <span class="badge-paid"><?= $finance['paid_count'] ?> invoices</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Partial Payments</span>
                    <span class="badge-partial"><?= $finance['partial_count'] ?> invoices</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Unpaid Invoices</span>
                    <span class="badge-unpaid"><?= $finance['unpaid_count'] ?> invoices</span>
                </div>

                <!-- Collection Rate Progress Bar -->
                <?php
                $rate = $finance['total_billed'] > 0
                    ? round(($finance['total_collected'] / $finance['total_billed']) * 100)
                    : 0;
                ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Collection Rate</small>
                        <small class="fw-bold"><?= $rate ?>%</small>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-success" style="width:<?= $rate ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <!-- Monthly Collection Table -->
        <div class="data-table mb-3">
            <div class="table-toolbar"><strong><i class="bi bi-calendar3 me-2"></i>Monthly Collection (Last 6 Months)</strong></div>
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Month</th><th>Invoices Created</th><th>Amount Collected</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly as $m): ?>
                    <tr>
                        <td><?= sanitize($m['month']) ?></td>
                        <td><?= $m['invoices_created'] ?></td>
                        <td class="text-success fw-semibold">PKR <?= number_format($m['collected']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($monthly)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No data yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== TOP PAYERS & OUTSTANDING ===== -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="data-table">
            <div class="table-toolbar"><strong><i class="bi bi-trophy me-2"></i>Top Fee Payers</strong></div>
            <table class="table align-middle mb-0">
                <thead><tr><th>Student</th><th>Invoices</th><th>Total Paid</th></tr></thead>
                <tbody>
                    <?php foreach ($topPayers as $tp): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= sanitize($tp['name']) ?></div>
                            <div class="text-muted" style="font-size:.78rem;"><?= sanitize($tp['student_id']) ?></div>
                        </td>
                        <td><?= $tp['inv_count'] ?></td>
                        <td class="text-success fw-semibold">PKR <?= number_format($tp['total_paid']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topPayers)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No data yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-md-6">
        <div class="data-table">
            <div class="table-toolbar"><strong><i class="bi bi-exclamation-circle me-2"></i>Highest Outstanding</strong></div>
            <table class="table align-middle mb-0">
                <thead><tr><th>Student</th><th>Amount Due</th></tr></thead>
                <tbody>
                    <?php foreach ($outstanding as $o): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= sanitize($o['name']) ?></div>
                            <div class="text-muted" style="font-size:.78rem;"><?= sanitize($o['student_id']) ?></div>
                        </td>
                        <td class="text-danger fw-semibold">PKR <?= number_format($o['due']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($outstanding)): ?>
                    <tr><td colspan="2" class="text-center text-muted py-3">No outstanding fees.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== ACTIVITY LOG ===== -->
<div class="data-table">
    <div class="table-toolbar"><strong><i class="bi bi-clock-history me-2"></i>Full Activity Log (Last 20 Actions)</strong></div>
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>IP Address</th><th>Time</th></tr>
        </thead>
        <tbody>
            <?php foreach ($activityLog as $log): ?>
            <tr>
                <td class="fw-semibold"><?= sanitize($log['user_name'] ?? 'System') ?></td>
                <td><span class="badge bg-light text-dark" style="font-size:.78rem;"><?= sanitize($log['role_name'] ?? '—') ?></span></td>
                <td><?= sanitize($log['action']) ?></td>
                <td><span class="badge bg-light text-dark text-capitalize"><?= sanitize($log['module']) ?></span></td>
                <td class="text-muted" style="font-size:.78rem;"><?= sanitize($log['ip_address']) ?></td>
                <td class="text-muted" style="font-size:.78rem;"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($activityLog)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No activity recorded yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../templates/layout_bottom.php'; ?>
