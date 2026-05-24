<?php $pageTitle = 'Access Denied'; include __DIR__ . '/layout_top.php'; ?>
<div class="text-center py-5">
    <i class="bi bi-shield-x" style="font-size:5rem; color:#e53935;"></i>
    <h3 class="mt-3">Access Denied</h3>
    <p class="text-muted">You do not have permission to access this page.<br>Contact your administrator if you think this is a mistake.</p>
    <a href="/school_system/index.php" class="btn btn-primary mt-2">
        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
    </a>
</div>
<?php include __DIR__ . '/layout_bottom.php'; ?>
