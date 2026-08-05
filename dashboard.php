<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

$role = getUserRole();
$name = $_SESSION['name'] ?? '';

// Fetch announcements (unchanged)
$announcements = $pdo->query("SELECT message, created_at FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Role preview:
 * - Only admins can preview other roles.
 * - Non-admins always see their own role dashboard.
 */
$roles = ['employee','time_station','work_phone','truck_driver','mechanic','admin'];
$previewRole = $role;

if ($role === 'admin') {
    $requested = $_GET['preview_role'] ?? '';
    if (in_array($requested, $roles, true)) {
        $previewRole = $requested;
    }
}

// Fetch enabled dashboard items allowed for preview role
$stmt = $pdo->prepare("
    SELECT di.*
    FROM dashboard_items di
    JOIN dashboard_item_roles dir ON dir.item_id = di.id
    WHERE dir.role = ?
      AND di.is_enabled = 1
    ORDER BY di.section ASC, di.sort_order ASC, di.title ASC
");
$stmt->execute([$previewRole]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group items by section
$sections = [];
foreach ($items as $it) {
    $sec = trim($it['section'] ?? '');
    if ($sec === '') $sec = 'General';
    $sections[$sec][] = $it;
}

require 'includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<div class="container my-4">
    <div class="card p-4 shadow-sm">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h2 class="mb-1">Welcome, <?= htmlspecialchars($name) ?>!</h2>
                <div class="text-muted">
                    Role: <span class="badge bg-secondary"><?= htmlspecialchars($role) ?></span>
                    <?php if ($role === 'admin' && $previewRole !== $role): ?>
                        <span class="ms-2">Previewing:
                            <span class="badge bg-info text-dark"><?= htmlspecialchars($previewRole) ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <?php if ($role === 'admin'): ?>
                    <a href="admin_panel.php" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-person-gear"></i> Admin Panel
                    </a>
                    <a href="admin/admin_dashboard_permissions.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-layout-text-window-reverse"></i> Dashboard Builder
                    </a>
                <?php endif; ?>

                <a href="logout.php" class="btn btn-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Log Out
                </a>
            </div>
        </div>

        

        <?php if ($role === 'admin'): ?>
        <hr>
        <div class="accordion" id="previewroleaccordion">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#previewrole" aria-expanded="false" aria-controls="previewrole">
                        Role Preview
                    </button>
                </h2>
                <div id="previewrole" class="accordion-collapse collapse" data-bs-parent="#previewroleaccordion">
                    <div class="accordion-body">
                        
                            <form method="GET" class="row g-2 align-items-end">
                                <div class="col-12 col-md-5">
                                    <select name="preview_role" class="form-select" onchange="this.form.submit()">
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?= htmlspecialchars($r) ?>" <?= $previewRole === $r ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($r) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 col-md-3 d-grid">
                                    <a class="btn btn-outline-secondary" href="dashboard.php">Reset to your view</a>
                                </div>
                            </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <hr class="my-4">

        <?php if (empty($sections)): ?>
            <div class="alert alert-info mb-0">
                No dashboard buttons are configured for role: <strong><?= htmlspecialchars($previewRole) ?></strong>.
                <?php if ($role === 'admin'): ?>
                    <div class="mt-2">
                        Go to <a href="admin_dashboard_permissions.php">Dashboard Builder</a> to add buttons and assign them to roles.
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($sections as $sectionName => $buttons): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="mb-3">
                                    <?= htmlspecialchars($sectionName) ?>
                                </h5>

                                <?php foreach ($buttons as $b): ?>
                                    <?php
                                    $btnClass = trim($b['btn_class'] ?? 'btn-outline-primary');
                                    if ($btnClass === '') $btnClass = 'btn-outline-primary';
                                    $icon = trim($b['icon'] ?? '');
                                    ?>
                                    <a href="<?= htmlspecialchars($b['url']) ?>"
                                       class="btn <?= htmlspecialchars($btnClass) ?> w-100 mb-2">
                                        <?php if ($icon !== ''): ?>
                                            <i class="bi <?= htmlspecialchars($icon) ?>"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($b['title']) ?>
                                    </a>
                                <?php endforeach; ?>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($announcements)): ?>
            <hr class="my-4">
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <strong>📣 Announcements</strong>
                    <span class="small opacity-75">Latest 5</span>
                </div>
                <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($announcements as $a): ?>
                            <li class="list-group-item">
                                <strong><?= date('M j, Y', strtotime($a['created_at'])) ?>:</strong><br>
                                <?= nl2br(htmlspecialchars($a['message'])) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer class="bg-dark text-white text-center py-3 mt-5">
    <small>&copy; <?= date('Y') ?> Truelove Services All rights reserved.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/service-worker.js')
            .then(() => console.log('Service Worker registered'))
            .catch(err => console.error('SW registration failed:', err));
    }
</script>
</body>
</html>