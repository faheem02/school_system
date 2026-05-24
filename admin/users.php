<?php
// =====================================================
// admin/users.php
// Super Admin creates and manages users here
// =====================================================
require_once '../config/db.php';
require_once '../config/auth.php';

requireLogin();

// Only super_admin can access this page
if ($_SESSION['role_slug'] !== 'super_admin') {
    http_response_code(403);
    include '../templates/403.php';
    exit;
}

$pageTitle = 'User Management';

// -------------------------------------------------------
// HANDLE FORM ACTIONS
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- ADD USER ----
    if ($action === 'add') {
        // Check if email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([trim($_POST['email'])]);
        if ($check->fetch()) {
            setFlash('error', 'This email is already registered.');
        } else {
            // Hash the password before saving - NEVER save plain text passwords
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role_id, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                trim($_POST['name']),
                trim($_POST['email']),
                $hashedPassword,
                (int)$_POST['role_id'],
                $_SESSION['user_id']
            ]);

            logActivity($pdo, 'Created user: ' . trim($_POST['name']), 'users', $pdo->lastInsertId());
            setFlash('success', 'User created successfully!');
        }
    }

    // ---- TOGGLE ACTIVE/INACTIVE ----
    elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        logActivity($pdo, 'Toggled user status', 'users', $id);
        setFlash('success', 'User status updated.');
    }

    redirect('/school_system/admin/users.php');
}

// Fetch all users with their role names
$users = $pdo->query("
    SELECT u.*, r.name as role_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    ORDER BY u.created_at DESC
")->fetchAll();

// Fetch all roles for the dropdown in Add User modal
$roles = $pdo->query("SELECT * FROM roles")->fetchAll();

include '../templates/layout_top.php';
?>

<!-- ===================== TOOLBAR ===================== -->
<div class="data-table mb-4">
    <div class="table-toolbar">
        <strong>All Users (<?= count($users) ?>)</strong>
        <button class="btn btn-primary btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-person-plus me-1"></i> Create User
        </button>
    </div>

    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $i => $u): ?>
            <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:50%;background:#e8eaf6;display:flex;align-items:center;justify-content:center;font-weight:700;color:#3949ab;font-size:0.85rem;">
                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                        </div>
                        <?= sanitize($u['name']) ?>
                    </div>
                </td>
                <td class="text-muted"><?= sanitize($u['email']) ?></td>
                <td>
                    <span class="badge bg-light text-dark"><?= sanitize($u['role_name']) ?></span>
                </td>
                <td>
                    <span class="badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>">
                        <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td class="text-muted" style="font-size:0.82rem;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <!-- Don't let admin disable their own account -->
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'danger' : 'success' ?>">
                            <i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>-circle"></i>
                            <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="text-muted" style="font-size:0.82rem;">(you)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>


<!-- =====================================================
     MODAL: CREATE USER
     ===================================================== -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Sara Khan">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email *</label>
                        <input type="email" name="email" class="form-control" required placeholder="sara@school.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password *</label>
                        <input type="password" name="password" class="form-control" required placeholder="Min 8 characters" minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assign Role *</label>
                        <select name="role_id" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= sanitize($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Permissions are automatically assigned based on the role.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../templates/layout_bottom.php'; ?>
