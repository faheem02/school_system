<?php
// =====================================================
// portal/my_permissions.php
// Every user can see their own access rights here
// =====================================================
require_once '../config/db.php';
require_once '../config/auth.php';

requireLogin();

$pageTitle = 'My Permissions';

// Get this user's permissions from DB
$stmt = $pdo->prepare("
    SELECT * FROM permissions WHERE role_id = ?
");
$stmt->execute([$_SESSION['role_id']]);
$permissions = $stmt->fetchAll();

// Index by module name for easy lookup
$permMap = [];
foreach ($permissions as $p) {
    $permMap[$p['module']] = $p;
}

$modules = ['students', 'teachers', 'invoices', 'staff'];

include '../templates/layout_top.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">

        <!-- User info card -->
        <div class="data-table mb-4 p-4">
            <div class="d-flex align-items-center gap-3">
                <div style="width:56px;height:56px;background:#1a237e;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1.3rem;">
                    <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                </div>
                <div>
                    <h5 class="mb-0"><?= sanitize($_SESSION['user_name']) ?></h5>
                    <div class="text-muted"><?= sanitize($_SESSION['user_email']) ?></div>
                    <span class="badge mt-1" style="background:#e8eaf6;color:#3949ab;">
                        <i class="bi bi-shield me-1"></i><?= sanitize($_SESSION['role_name']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Permissions table -->
        <div class="data-table">
            <div class="table-toolbar">
                <strong><i class="bi bi-key me-2"></i>My Access Permissions</strong>
            </div>
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th class="text-center">View</th>
                        <th class="text-center">Add</th>
                        <th class="text-center">Edit</th>
                        <th class="text-center">Delete</th>
                        <th>Access Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $module): ?>
                    <?php
                    // Super Admin gets everything; others use DB
                    if ($_SESSION['role_slug'] === 'super_admin') {
                        $v = $a = $e = $d = 1;
                    } else {
                        $p = $permMap[$module] ?? [];
                        $v = $p['can_view']   ?? 0;
                        $a = $p['can_add']    ?? 0;
                        $e = $p['can_edit']   ?? 0;
                        $d = $p['can_delete'] ?? 0;
                    }

                    // Calculate access level label
                    $total = $v + $a + $e + $d;
                    if ($total == 4)     { $level = ['Full access',    'success']; }
                    elseif ($total >= 2) { $level = ['Partial access', 'warning']; }
                    elseif ($total == 1) { $level = ['View only',      'info'];    }
                    else                 { $level = ['No access',      'danger'];  }
                    ?>
                    <tr>
                        <td>
                            <i class="bi bi-<?= ['students'=>'people','teachers'=>'person-badge','invoices'=>'receipt','staff'=>'person-workspace'][$module] ?> me-2 text-muted"></i>
                            <strong class="text-capitalize"><?= $module ?></strong>
                        </td>
                        <td class="text-center"><?= $v ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                        <td class="text-center"><?= $a ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                        <td class="text-center"><?= $e ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                        <td class="text-center"><?= $d ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                        <td>
                            <span class="badge bg-<?= $level[1] ?> bg-opacity-10 text-<?= $level[1] ?>" style="font-size:0.8rem;padding:5px 10px;">
                                <?= $level[0] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../templates/layout_bottom.php'; ?>
