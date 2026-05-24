<?php
// =====================================================
// modules/teachers/index.php
// Teachers CRUD — same pattern as students
// =====================================================
require_once '../../config/db.php';
require_once '../../config/auth.php';

requirePermission($pdo, 'teachers', 'can_view');

$pageTitle = 'Teachers';

// -------------------------------------------------------
// HANDLE ADD / EDIT / DELETE
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && hasPermission($pdo, 'teachers', 'can_add')) {
        $lastId    = $pdo->query("SELECT MAX(id) FROM teachers")->fetchColumn();
        $teacherId = 'TCH-' . str_pad(($lastId + 1), 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO teachers (teacher_id, name, email, phone, subject, qualification, salary, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $teacherId,
            trim($_POST['name']),
            trim($_POST['email']),
            trim($_POST['phone']),
            trim($_POST['subject']),
            trim($_POST['qualification']),
            (float)$_POST['salary'],
            $_SESSION['user_id']
        ]);
        logActivity($pdo, 'Added teacher: ' . trim($_POST['name']), 'teachers', $pdo->lastInsertId());
        setFlash('success', 'Teacher added successfully!');
    }

    elseif ($action === 'edit' && hasPermission($pdo, 'teachers', 'can_edit')) {
        $stmt = $pdo->prepare("
            UPDATE teachers SET name=?, email=?, phone=?, subject=?, qualification=?, salary=?, status=?
            WHERE id=?
        ");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['email']),
            trim($_POST['phone']),
            trim($_POST['subject']),
            trim($_POST['qualification']),
            (float)$_POST['salary'],
            $_POST['status'],
            (int)$_POST['id']
        ]);
        logActivity($pdo, 'Updated teacher: ' . trim($_POST['name']), 'teachers', $_POST['id']);
        setFlash('success', 'Teacher updated successfully!');
    }

    elseif ($action === 'delete' && hasPermission($pdo, 'teachers', 'can_delete')) {
        $id   = (int)$_POST['id'];
        $name = $pdo->prepare("SELECT name FROM teachers WHERE id=?");
        $name->execute([$id]);
        $tName = $name->fetchColumn();

        $pdo->prepare("DELETE FROM teachers WHERE id=?")->execute([$id]);
        logActivity($pdo, 'Deleted teacher: ' . $tName, 'teachers', $id);
        setFlash('success', 'Teacher deleted.');
    }

    redirect('/school_system/modules/teachers/index.php');
}

// -------------------------------------------------------
// SEARCH & PAGINATION
// -------------------------------------------------------
$search  = trim($_GET['search'] ?? '');
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = $search ? "WHERE name LIKE ? OR teacher_id LIKE ? OR subject LIKE ?" : "";
$param  = "%$search%";

$total      = $pdo->prepare("SELECT COUNT(*) FROM teachers $where");
$search ? $total->execute([$param,$param,$param]) : $total->execute();
$totalRec   = $total->fetchColumn();
$totalPages = ceil($totalRec / $perPage);

$stmt = $pdo->prepare("SELECT * FROM teachers $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$search ? $stmt->execute([$param,$param,$param]) : $stmt->execute();
$teachers = $stmt->fetchAll();

include '../../templates/layout_top.php';
?>

<div class="data-table mb-4">
    <div class="table-toolbar">
        <form method="GET" class="d-flex gap-2 flex-grow-1">
            <input type="text" name="search" class="form-control" placeholder="Search by name, ID, subject..."
                   value="<?= sanitize($search) ?>" style="max-width:320px;">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i> Search</button>
            <?php if ($search): ?><a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i> Clear</a><?php endif; ?>
        </form>
        <?php if (hasPermission($pdo, 'teachers', 'can_add')): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i> Add Teacher
        </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th><th>Teacher ID</th><th>Name</th><th>Subject</th>
                    <th>Qualification</th><th>Salary</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $i => $t): ?>
                <tr>
                    <td class="text-muted"><?= $offset+$i+1 ?></td>
                    <td><code><?= sanitize($t['teacher_id']) ?></code></td>
                    <td>
                        <div class="fw-semibold"><?= sanitize($t['name']) ?></div>
                        <div class="text-muted" style="font-size:.78rem;"><?= sanitize($t['email']) ?></div>
                    </td>
                    <td><?= sanitize($t['subject']) ?></td>
                    <td><?= sanitize($t['qualification']) ?></td>
                    <td>PKR <?= number_format($t['salary'], 0) ?></td>
                    <td><span class="badge-<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if (hasPermission($pdo, 'teachers', 'can_edit')): ?>
                            <button class="btn btn-sm btn-outline-warning"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (hasPermission($pdo, 'teachers', 'can_delete')): ?>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="openDeleteModal(<?= $t['id'] ?>, '<?= sanitize($t['name']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($teachers)): ?>
                <tr><td colspan="8" class="text-center py-5 text-muted">
                    <i class="bi bi-search d-block" style="font-size:2rem;"></i>No teachers found.
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
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Teacher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Mr. Usman">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="teacher@school.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="+92 300 0000000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="e.g. Mathematics">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Qualification</label>
                            <input type="text" name="qualification" class="form-control" placeholder="e.g. M.Sc Mathematics">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Monthly Salary (PKR)</label>
                            <input type="number" name="salary" class="form-control" placeholder="e.g. 45000" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Teacher</button>
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
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Teacher</h5>
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
                            <label class="form-label fw-semibold">Subject</label>
                            <input type="text" name="subject" id="edit_subject" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Qualification</label>
                            <input type="text" name="qualification" id="edit_qualification" class="form-control">
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
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Update Teacher</button>
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
                    <h5 class="mt-2">Delete Teacher?</h5>
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
function openEditModal(t) {
    document.getElementById('edit_id').value            = t.id;
    document.getElementById('edit_name').value          = t.name;
    document.getElementById('edit_email').value         = t.email;
    document.getElementById('edit_phone').value         = t.phone;
    document.getElementById('edit_subject').value       = t.subject;
    document.getElementById('edit_qualification').value = t.qualification;
    document.getElementById('edit_salary').value        = t.salary;
    document.getElementById('edit_status').value        = t.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function openDeleteModal(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../../templates/layout_bottom.php'; ?>
