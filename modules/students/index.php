<?php
// =====================================================
// modules/students/index.php
// Full Students CRUD with Bootstrap modals
// =====================================================
require_once '../../config/db.php';
require_once '../../config/auth.php';

requirePermission($pdo, 'students', 'can_view');

$pageTitle = 'Students';

// -------------------------------------------------------
// HANDLE FORM ACTIONS (Add / Edit / Delete)
// All sent via POST from Bootstrap modals
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- ADD STUDENT ----
    if ($action === 'add' && hasPermission($pdo, 'students', 'can_add')) {
        // Generate a student ID like STU-001
        $lastId = $pdo->query("SELECT MAX(id) FROM students")->fetchColumn();
        $studentId = 'STU-' . str_pad(($lastId + 1), 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO students (student_id, name, email, phone, class, date_of_birth, address, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $studentId,
            trim($_POST['name']),
            trim($_POST['email']),
            trim($_POST['phone']),
            trim($_POST['class']),
            $_POST['dob'] ?: null,
            trim($_POST['address']),
            $_SESSION['user_id']
        ]);

        logActivity($pdo, 'Added student: ' . trim($_POST['name']), 'students', $pdo->lastInsertId());
        setFlash('success', 'Student added successfully!');
    }

    // ---- EDIT STUDENT ----
    elseif ($action === 'edit' && hasPermission($pdo, 'students', 'can_edit')) {
        $stmt = $pdo->prepare("
            UPDATE students SET name=?, email=?, phone=?, class=?, date_of_birth=?, address=?, status=?
            WHERE id=?
        ");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['email']),
            trim($_POST['phone']),
            trim($_POST['class']),
            $_POST['dob'] ?: null,
            trim($_POST['address']),
            $_POST['status'],
            (int)$_POST['id']
        ]);

        logActivity($pdo, 'Updated student: ' . trim($_POST['name']), 'students', $_POST['id']);
        setFlash('success', 'Student updated successfully!');
    }

    // ---- DELETE STUDENT ----
    elseif ($action === 'delete' && hasPermission($pdo, 'students', 'can_delete')) {
        $id = (int)$_POST['id'];
        $student = $pdo->prepare("SELECT name FROM students WHERE id=?");
        $student->execute([$id]);
        $sName = $student->fetchColumn();

        $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);

        logActivity($pdo, 'Deleted student: ' . $sName, 'students', $id);
        setFlash('success', 'Student deleted successfully!');
    }

    redirect('/school_system/modules/students/index.php');
}

// -------------------------------------------------------
// SEARCH & PAGINATION
// -------------------------------------------------------
$search  = trim($_GET['search'] ?? '');
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Build WHERE clause for search
$whereClause = $search ? "WHERE name LIKE ? OR student_id LIKE ? OR email LIKE ? OR class LIKE ?" : "";
$searchParam = "%$search%";

// Count total records (for pagination)
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students $whereClause");
if ($search) $countStmt->execute([$searchParam, $searchParam, $searchParam, $searchParam]);
else $countStmt->execute();
$totalRecords = $countStmt->fetchColumn();
$totalPages   = ceil($totalRecords / $perPage);

// Fetch students for current page
$dataStmt = $pdo->prepare("SELECT * FROM students $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
if ($search) $dataStmt->execute([$searchParam, $searchParam, $searchParam, $searchParam]);
else $dataStmt->execute();
$students = $dataStmt->fetchAll();

include '../../templates/layout_top.php';
?>

<!-- ===================== TOOLBAR ===================== -->
<div class="data-table mb-4">
    <div class="table-toolbar">
        <!-- Search bar -->
        <form method="GET" class="d-flex gap-2 flex-grow-1">
            <input type="text" name="search" class="form-control" placeholder="Search by name, ID, class..."
                   value="<?= sanitize($search) ?>" style="max-width:320px;">
            <button type="submit" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-search"></i> Search
            </button>
            <?php if ($search): ?>
            <a href="?" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x"></i> Clear
            </a>
            <?php endif; ?>
        </form>

        <!-- Add button - only shown if user has can_add permission -->
        <?php if (hasPermission($pdo, 'students', 'can_add')): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i> Add Student
        </button>
        <?php endif; ?>
    </div>

    <!-- ===================== TABLE ===================== -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $i => $s): ?>
                <tr>
                    <td class="text-muted" style="font-size:0.82rem;"><?= $offset + $i + 1 ?></td>
                    <td><code><?= sanitize($s['student_id']) ?></code></td>
                    <td>
                        <div class="fw-semibold"><?= sanitize($s['name']) ?></div>
                        <div class="text-muted" style="font-size:0.78rem;"><?= sanitize($s['email']) ?></div>
                    </td>
                    <td><?= sanitize($s['class']) ?></td>
                    <td><?= sanitize($s['phone']) ?></td>
                    <td>
                        <span class="badge-<?= $s['status'] ?>">
                            <?= ucfirst($s['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <!-- Edit button -->
                            <?php if (hasPermission($pdo, 'students', 'can_edit')): ?>
                            <button class="btn btn-sm btn-outline-warning"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"
                                title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>

                            <!-- Delete button -->
                            <?php if (hasPermission($pdo, 'students', 'can_delete')): ?>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="openDeleteModal(<?= $s['id'] ?>, '<?= sanitize($s['name']) ?>')"
                                title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($students)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-search d-block" style="font-size:2rem;"></i>
                        No students found.
                        <?= $search ? 'Try a different search.' : '' ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ===================== PAGINATION ===================== -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
        <small class="text-muted">
            Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalRecords) ?> of <?= $totalRecords ?> students
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>


<!-- =====================================================
     BOOTSTRAP MODAL: ADD STUDENT
     ===================================================== -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Ahmed Ali">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="student@email.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="+92 300 1234567">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Class</label>
                            <input type="text" name="class" class="form-control" placeholder="e.g. Grade 10-A">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" name="dob" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Home address"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- =====================================================
     BOOTSTRAP MODAL: EDIT STUDENT
     ===================================================== -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Student</h5>
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
                            <label class="form-label fw-semibold">Class</label>
                            <input type="text" name="class" id="edit_class" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" name="dob" id="edit_dob" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-1"></i> Update Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- =====================================================
     BOOTSTRAP MODAL: DELETE CONFIRMATION
     ===================================================== -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">

                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center pt-0">
                    <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size:3rem;"></i>
                    <h5 class="mt-2">Delete Student?</h5>
                    <p class="text-muted mb-0">You are about to delete <strong id="delete_name"></strong>. This cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Yes, Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
// Fill the Edit modal with the student's current data
function openEditModal(student) {
    document.getElementById('edit_id').value      = student.id;
    document.getElementById('edit_name').value    = student.name;
    document.getElementById('edit_email').value   = student.email;
    document.getElementById('edit_phone').value   = student.phone;
    document.getElementById('edit_class').value   = student.class;
    document.getElementById('edit_dob').value     = student.date_of_birth;
    document.getElementById('edit_status').value  = student.status;
    document.getElementById('edit_address').value = student.address;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// Fill the Delete modal with the student's name
function openDeleteModal(id, name) {
    document.getElementById('delete_id').value  = id;
    document.getElementById('delete_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../../templates/layout_bottom.php'; ?>
