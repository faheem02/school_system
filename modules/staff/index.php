<?php
// =====================================================
// modules/staff/index.php
// Staff CRUD with attendance and permissions info
// =====================================================
require_once '../../config/db.php';
require_once '../../config/auth.php';

requirePermission($pdo, 'staff', 'can_view');

$pageTitle = 'Staff';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && hasPermission($pdo, 'staff', 'can_add')) {
        $lastId  = $pdo->query("SELECT MAX(id) FROM staff")->fetchColumn();
        $staffId = 'STF-' . str_pad(($lastId + 1), 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO staff (staff_id, name, email, phone, department, position, salary, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $staffId,
            trim($_POST['name']),
            trim($_POST['email']),
            trim($_POST['phone']),
            trim($_POST['department']),
            trim($_POST['position']),
            (float)$_POST['salary'],
            $_SESSION['user_id']
        ]);
        logActivity($pdo, 'Added staff: ' . trim($_POST['name']), 'staff', $pdo->lastInsertId());
        setFlash('success', 'Staff member added successfully!');
    }

    elseif ($action === 'edit' && hasPermission($pdo, 'staff', 'can_edit')) {
        $stmt = $pdo->prepare("
            UPDATE staff SET name=?, email=?, phone=?, department=?, position=?, salary=?, status=?
            WHERE id=?
        ");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['email']),
            trim($_POST['phone']),
            trim($_POST['department']),
            trim($_POST['position']),
            (float)$_POST['salary'],
            $_POST['status'],
            (int)$_POST['id']
        ]);
        logActivity($pdo, 'Updated staff: ' . trim($_POST['name']), 'staff', $_POST['id']);
        setFlash('success', 'Staff member updated successfully!');
    }

    elseif ($action === 'delete' && hasPermission($pdo, 'staff', 'can_delete')) {
        $id = (int)$_POST['id'];
        $n  = $pdo->prepare("SELECT name FROM staff WHERE id=?");
        $n->execute([$id]);
        $sName = $n->fetchColumn();
        $pdo->prepare("DELETE FROM staff WHERE id=?")->execute([$id]);
        logActivity($pdo, 'Deleted staff: ' . $sName, 'staff', $id);
        setFlash('success', 'Staff member deleted.');
    }

    redirect('/school_system/modules/staff/index.php');
}

$search     = trim($_GET['search'] ?? '');
$perPage    = 10;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$where      = $search ? "WHERE name LIKE ? OR staff_id LIKE ? OR department LIKE ? OR position LIKE ?" : "";
$param      = "%$search%";

$total      = $pdo->prepare("SELECT COUNT(*) FROM staff $where");
$search ? $total->execute([$param,$param,$param,$param]) : $total->execute();
$totalRec   = $total->fetchColumn();
$totalPages = ceil($totalRec / $perPage);

$stmt = $pdo->prepare("SELECT * FROM staff $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$search ? $stmt->execute([$param,$param,$param,$param]) : $stmt->execute();
$staffList  = $stmt->fetchAll();

include '../../templates/layout_top.php';
?>

<div class="data-table mb-4">
    <div class="table-toolbar">
        <form method="GET" class="d-flex gap-2 flex-grow-1">
            <input type="text" name="search" class="form-control" placeholder="Search name, department, position..."
                   value="<?= sanitize($search) ?>" style="max-width:320px;">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i> Search</button>
            <?php if ($search): ?><a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i> Clear</a><?php endif; ?>
        </form>
        <?php if (hasPermission($pdo, 'staff', 'can_add')): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i> Add Staff
        </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th><th>Staff ID</th><th>Name</th><th>Department</th>
                    <th>Position</th><th>Salary</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $i => $s): ?>
                <tr>
                    <td class="text-muted"><?= $offset+$i+1 ?></td>
                    <td><code><?= sanitize($s['staff_id']) ?></code></td>
                    <td>
                        <div class="fw-semibold"><?= sanitize($s['name']) ?></div>
                        <div class="text-muted" style="font-size:.78rem;"><?= sanitize($s['email']) ?></div>
                    </td>
                    <td><?= sanitize($s['department']) ?></td>
                    <td><?= sanitize($s['position']) ?></td>
                    <td>PKR <?= number_format($s['salary'], 0) ?></td>
                    <td><span class="badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if (hasPermission($pdo, 'staff', 'can_edit')): ?>
                            <button class="btn btn-sm btn-outline-warning"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (hasPermission($pdo, 'staff', 'can_delete')): ?>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="openDeleteModal(<?= $s['id'] ?>, '<?= sanitize($s['name']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($staffList)): ?>
                <tr><td colspan="8" class="text-center py-5 text-muted">
                    <i class="bi bi-search d-block" style="font-size:2rem;"></i>No staff records found.
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
                <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Ali Hassan">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="ali@school.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="+92 300 0000000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department</label>
                            <input type="text" name="department" class="form-control" placeholder="e.g. Administration">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Position</label>
                            <input type="text" name="position" class="form-control" placeholder="e.g. Office Manager">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Monthly Salary (PKR)</label>
                            <input type="number" name="salary" class="form-control" placeholder="e.g. 35000" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department</label>
                            <input type="text" name="department" id="edit_department" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Position</label>
                            <input type="text" name="position" id="edit_position" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Salary (PKR)</label>
                            <input type="number" name="salary" id="edit_salary" class="form-control" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Update Staff</button>
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
                    <h5 class="mt-2">Delete Staff Member?</h5>
                    <p class="text-muted">You are about to delete <strong id="delete_name"></strong>. This cannot be undone.</p>
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
function openEditModal(s) {
    document.getElementById('edit_id').value         = s.id;
    document.getElementById('edit_name').value       = s.name;
    document.getElementById('edit_email').value      = s.email;
    document.getElementById('edit_phone').value      = s.phone;
    document.getElementById('edit_department').value = s.department;
    document.getElementById('edit_position').value   = s.position;
    document.getElementById('edit_salary').value     = s.salary;
    document.getElementById('edit_status').value     = s.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function openDeleteModal(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../../templates/layout_bottom.php'; ?>
