<?php
// =====================================================
// modules/invoices/index.php
// Invoices & Fee management
// This is more complex: linked to students table
// =====================================================
require_once '../../config/db.php';
require_once '../../config/auth.php';

requirePermission($pdo, 'invoices', 'can_view');

$pageTitle = 'Invoices & Fees';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- CREATE INVOICE ----
    if ($action === 'add' && hasPermission($pdo, 'invoices', 'can_add')) {
        // Generate invoice number: INV-2025-001
        $lastId   = $pdo->query("SELECT MAX(id) FROM invoices")->fetchColumn();
        $invNum   = 'INV-' . date('Y') . '-' . str_pad(($lastId + 1), 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO invoices (invoice_number, student_id, amount, description, due_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invNum,
            (int)$_POST['student_id'],
            (float)$_POST['amount'],
            trim($_POST['description']),
            $_POST['due_date'] ?: null,
            $_SESSION['user_id']
        ]);
        logActivity($pdo, 'Created invoice ' . $invNum, 'invoices', $pdo->lastInsertId());
        setFlash('success', 'Invoice ' . $invNum . ' created successfully!');
    }

    // ---- RECORD PAYMENT ----
    elseif ($action === 'pay' && hasPermission($pdo, 'invoices', 'can_edit')) {
        $id         = (int)$_POST['id'];
        $payAmount  = (float)$_POST['pay_amount'];

        // Get current invoice
        $inv = $pdo->prepare("SELECT * FROM invoices WHERE id=?");
        $inv->execute([$id]);
        $invoice = $inv->fetch();

        $newPaid = $invoice['paid_amount'] + $payAmount;

        // Determine new status
        if ($newPaid >= $invoice['amount']) {
            $newPaid   = $invoice['amount']; // don't overpay
            $newStatus = 'paid';
        } elseif ($newPaid > 0) {
            $newStatus = 'partial';
        } else {
            $newStatus = 'unpaid';
        }

        $pdo->prepare("UPDATE invoices SET paid_amount=?, status=? WHERE id=?")
            ->execute([$newPaid, $newStatus, $id]);

        logActivity($pdo, 'Recorded payment PKR ' . number_format($payAmount) . ' for invoice ' . $invoice['invoice_number'], 'invoices', $id);
        setFlash('success', 'Payment recorded. Status: ' . ucfirst($newStatus));
    }

    // ---- DELETE INVOICE ----
    elseif ($action === 'delete' && hasPermission($pdo, 'invoices', 'can_delete')) {
        $id = (int)$_POST['id'];
        $n  = $pdo->prepare("SELECT invoice_number FROM invoices WHERE id=?");
        $n->execute([$id]);
        $invNum = $n->fetchColumn();
        $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$id]);
        logActivity($pdo, 'Deleted invoice ' . $invNum, 'invoices', $id);
        setFlash('success', 'Invoice deleted.');
    }

    redirect('/school_system/modules/invoices/index.php');
}

// -------------------------------------------------------
// FILTERS & PAGINATION
// -------------------------------------------------------
$search     = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$perPage    = 10;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;

// Build WHERE dynamically
$conditions = [];
$params     = [];
if ($search) {
    $conditions[] = "(i.invoice_number LIKE ? OR s.name LIKE ?)";
    $params[]     = "%$search%";
    $params[]     = "%$search%";
}
if ($filterStatus) {
    $conditions[] = "i.status = ?";
    $params[]     = $filterStatus;
}
$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM invoices i LEFT JOIN students s ON i.student_id=s.id $where");
$countStmt->execute($params);
$totalRec   = $countStmt->fetchColumn();
$totalPages = ceil($totalRec / $perPage);

// Fetch
$dataStmt = $pdo->prepare("
    SELECT i.*, s.name as student_name, s.student_id as s_id
    FROM invoices i
    LEFT JOIN students s ON i.student_id = s.id
    $where
    ORDER BY i.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$invoices = $dataStmt->fetchAll();

// Summary cards
$summary = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(amount) as total_amount,
        SUM(paid_amount) as total_paid,
        SUM(amount - paid_amount) as total_due,
        SUM(status='paid') as count_paid,
        SUM(status='unpaid') as count_unpaid,
        SUM(status='partial') as count_partial
    FROM invoices
")->fetch();

// Students list for Add modal dropdown
$students = $pdo->query("SELECT id, student_id, name FROM students WHERE status='active' ORDER BY name")->fetchAll();

include '../../templates/layout_top.php';
?>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8f5e9;"><i class="bi bi-check-circle" style="color:#2e7d32;"></i></div>
            <div>
                <div class="stat-num"><?= $summary['count_paid'] ?></div>
                <div class="stat-label">Paid</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3e0;"><i class="bi bi-clock" style="color:#e65100;"></i></div>
            <div>
                <div class="stat-num"><?= $summary['count_unpaid'] ?></div>
                <div class="stat-label">Unpaid</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e3f2fd;"><i class="bi bi-receipt" style="color:#1565c0;"></i></div>
            <div>
                <div class="stat-num">PKR <?= number_format($summary['total_paid'] ?? 0) ?></div>
                <div class="stat-label">Total Collected</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fce4ec;"><i class="bi bi-exclamation-circle" style="color:#c62828;"></i></div>
            <div>
                <div class="stat-num">PKR <?= number_format($summary['total_due'] ?? 0) ?></div>
                <div class="stat-label">Outstanding Due</div>
            </div>
        </div>
    </div>
</div>

<div class="data-table mb-4">
    <div class="table-toolbar flex-wrap">
        <!-- Search + filter -->
        <form method="GET" class="d-flex gap-2 flex-grow-1 flex-wrap">
            <input type="text" name="search" class="form-control" placeholder="Search invoice # or student..."
                   value="<?= sanitize($search) ?>" style="max-width:260px;">
            <select name="status" class="form-select" style="max-width:140px;">
                <option value="">All Status</option>
                <option value="unpaid"  <?= $filterStatus=='unpaid'  ?'selected':'' ?>>Unpaid</option>
                <option value="partial" <?= $filterStatus=='partial' ?'selected':'' ?>>Partial</option>
                <option value="paid"    <?= $filterStatus=='paid'    ?'selected':'' ?>>Paid</option>
            </select>
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i> Filter</button>
            <?php if ($search||$filterStatus): ?>
            <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i> Clear</a>
            <?php endif; ?>
        </form>
        <?php if (hasPermission($pdo, 'invoices', 'can_add')): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i> Create Invoice
        </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th><th>Invoice #</th><th>Student</th><th>Amount</th>
                    <th>Paid</th><th>Due</th><th>Status</th><th>Due Date</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $i => $inv): ?>
                <?php $due = $inv['amount'] - $inv['paid_amount']; ?>
                <tr>
                    <td class="text-muted"><?= $offset+$i+1 ?></td>
                    <td><code><?= sanitize($inv['invoice_number']) ?></code></td>
                    <td>
                        <div class="fw-semibold"><?= sanitize($inv['student_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:.78rem;"><?= sanitize($inv['s_id'] ?? '') ?></div>
                    </td>
                    <td>PKR <?= number_format($inv['amount'], 0) ?></td>
                    <td class="text-success">PKR <?= number_format($inv['paid_amount'], 0) ?></td>
                    <td class="<?= $due > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">PKR <?= number_format($due, 0) ?></td>
                    <td><span class="badge-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span></td>
                    <td class="text-muted" style="font-size:.82rem;">
                        <?= $inv['due_date'] ? date('d M Y', strtotime($inv['due_date'])) : '—' ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <!-- Pay button (edit permission) -->
                            <?php if (hasPermission($pdo, 'invoices', 'can_edit') && $inv['status'] !== 'paid'): ?>
                            <button class="btn btn-sm btn-outline-success"
                                onclick="openPayModal(<?= $inv['id'] ?>, '<?= sanitize($inv['invoice_number']) ?>', <?= $due ?>)"
                                title="Record Payment">
                                <i class="bi bi-cash-coin"></i>
                            </button>
                            <?php endif; ?>
                            <!-- Delete button -->
                            <?php if (hasPermission($pdo, 'invoices', 'can_delete')): ?>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="openDeleteModal(<?= $inv['id'] ?>, '<?= sanitize($inv['invoice_number']) ?>')"
                                title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="9" class="text-center py-5 text-muted">
                    <i class="bi bi-receipt d-block" style="font-size:2rem;"></i>No invoices found.
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
        <small class="text-muted">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalRec) ?> of <?= $totalRec ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p=1;$p<=$totalPages;$p++): ?>
            <li class="page-item <?= $p==$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- ADD INVOICE MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Create Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Student *</label>
                        <select name="student_id" class="form-select" required>
                            <option value="">— Select Student —</option>
                            <?php foreach ($students as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= sanitize($st['name']) ?> (<?= sanitize($st['student_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (PKR) *</label>
                        <input type="number" name="amount" class="form-control" required placeholder="e.g. 15000" min="1" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Monthly tuition fee - January 2025">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RECORD PAYMENT MODAL -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="pay">
                <input type="hidden" name="id" id="pay_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-1">Invoice: <strong id="pay_inv_num"></strong></p>
                    <p class="text-muted mb-3">Outstanding: <strong class="text-danger" id="pay_due"></strong></p>
                    <label class="form-label fw-semibold">Payment Amount (PKR) *</label>
                    <input type="number" name="pay_amount" id="pay_amount" class="form-control" required min="1" step="0.01" placeholder="Enter amount received">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body text-center pt-4">
                    <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size:3rem;"></i>
                    <h5 class="mt-2">Delete Invoice?</h5>
                    <p class="text-muted">Delete invoice <strong id="delete_name"></strong>? This cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPayModal(id, invNum, due) {
    document.getElementById('pay_id').value      = id;
    document.getElementById('pay_inv_num').textContent = invNum;
    document.getElementById('pay_due').textContent     = 'PKR ' + due.toLocaleString();
    document.getElementById('pay_amount').max          = due;
    new bootstrap.Modal(document.getElementById('payModal')).show();
}
function openDeleteModal(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../../templates/layout_bottom.php'; ?>
