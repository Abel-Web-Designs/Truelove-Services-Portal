<?php
require '../includes/header.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$roles = ['employee', 'time_station', 'work_phone', 'truck_driver', 'mechanic', 'admin'];

$successMsg = '';
$errorMsg   = '';

/* -------------------- CREATE ITEM -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_item'])) {
    $title     = trim($_POST['title'] ?? '');
    $url       = trim($_POST['url'] ?? '');
    $icon      = trim($_POST['icon'] ?? '');
    $btnClass  = trim($_POST['btn_class'] ?? 'btn-outline-primary');
    $section   = trim($_POST['section'] ?? 'General');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $enabled   = isset($_POST['is_enabled']) ? 1 : 0;

    $selectedRoles = $_POST['roles'] ?? [];
    $selectedRoles = array_values(array_intersect($selectedRoles, $roles));

    if ($title && $url) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO dashboard_items (title, url, icon, btn_class, section, sort_order, is_enabled)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title,
                $url,
                $icon ?: null,
                $btnClass ?: 'btn-outline-primary',
                $section ?: 'General',
                $sortOrder,
                $enabled
            ]);

            $itemId = (int)$pdo->lastInsertId();

            if (!empty($selectedRoles)) {
                $ins = $pdo->prepare("INSERT INTO dashboard_item_roles (item_id, role) VALUES (?, ?)");
                foreach ($selectedRoles as $r) {
                    $ins->execute([$itemId, $r]);
                }
            }

            $pdo->commit();
            $successMsg = "Dashboard item created.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMsg = "Error creating item.";
        }
    } else {
        $errorMsg = "Title and URL are required.";
    }
}

/* -------------------- UPDATE ITEM -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    $id        = (int)($_POST['id'] ?? 0);
    $title     = trim($_POST['title'] ?? '');
    $url       = trim($_POST['url'] ?? '');
    $icon      = trim($_POST['icon'] ?? '');
    $btnClass  = trim($_POST['btn_class'] ?? 'btn-outline-primary');
    $section   = trim($_POST['section'] ?? 'General');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $enabled   = isset($_POST['is_enabled']) ? 1 : 0;

    $selectedRoles = $_POST['roles'] ?? [];
    $selectedRoles = array_values(array_intersect($selectedRoles, $roles));

    if ($id && $title && $url) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE dashboard_items
                SET title = ?, url = ?, icon = ?, btn_class = ?, section = ?, sort_order = ?, is_enabled = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $title,
                $url,
                $icon ?: null,
                $btnClass ?: 'btn-outline-primary',
                $section ?: 'General',
                $sortOrder,
                $enabled,
                $id
            ]);

            $pdo->prepare("DELETE FROM dashboard_item_roles WHERE item_id = ?")->execute([$id]);

            if (!empty($selectedRoles)) {
                $ins = $pdo->prepare("INSERT INTO dashboard_item_roles (item_id, role) VALUES (?, ?)");
                foreach ($selectedRoles as $r) {
                    $ins->execute([$id, $r]);
                }
            }

            $pdo->commit();
            $successMsg = "Dashboard item updated.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMsg = "Error updating item.";
        }
    } else {
        $errorMsg = "Invalid update input.";
    }
}

/* -------------------- QUICK TOGGLE ENABLED -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_enabled'])) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE dashboard_items
            SET is_enabled = CASE WHEN is_enabled = 1 THEN 0 ELSE 1 END
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $successMsg = "Item status updated.";
    }
}

/* -------------------- DELETE ITEM -------------------- */
if (isset($_GET['delete'])) {
    $deleteId = (int)($_GET['delete'] ?? 0);

    if ($deleteId > 0) {
        $pdo->prepare("DELETE FROM dashboard_items WHERE id = ?")->execute([$deleteId]);
    }

    $redirectRole = $_GET['role'] ?? 'all';
    header('Location: admin_dashboard_permissions.php?role=' . urlencode($redirectRole));
    exit();
}

/* -------------------- LOAD ITEMS + ROLES -------------------- */
$items = $pdo->query("
    SELECT *
    FROM dashboard_items
    ORDER BY section ASC, sort_order ASC, title ASC
")->fetchAll(PDO::FETCH_ASSOC);

$itemRolesRaw = $pdo->query("
    SELECT item_id, role
    FROM dashboard_item_roles
")->fetchAll(PDO::FETCH_ASSOC);

$itemRoles = [];
foreach ($itemRolesRaw as $r) {
    $itemRoles[(int)$r['item_id']][] = $r['role'];
}

/* -------------------- FILTERING -------------------- */
$activeTab = $_GET['role'] ?? 'all';
$activeTab = in_array($activeTab, $roles, true) ? $activeTab : 'all';

$search = trim($_GET['search'] ?? '');

/* -------------------- HELPERS -------------------- */
function itemVisibleForRole(int $itemId, string $role, array $itemRoles): bool
{
    $allowed = $itemRoles[$itemId] ?? [];

    if (empty($allowed)) {
        return true; // no roles assigned = visible to all
    }

    return in_array($role, $allowed, true);
}

function roleLabel(string $r): string
{
    return match ($r) {
        'employee'      => 'Employee',
        'time_station'  => 'Time Station',
        'work_phone'    => 'Work Phone',
        'truck_driver'  => 'Truck Driver',
        'mechanic'      => 'Mechanic',
        'admin'         => 'Admin',
        default         => $r
    };
}

function roleBadgeClass(string $r): string
{
    return match ($r) {
        'admin'         => 'text-bg-danger',
        'mechanic'      => 'text-bg-warning',
        'truck_driver'  => 'text-bg-info',
        'work_phone'    => 'text-bg-primary',
        'time_station'  => 'text-bg-success',
        'employee'      => 'text-bg-secondary',
        default         => 'text-bg-dark'
    };
}

function visibleCountForRole(string $role, array $items, array $itemRoles): int
{
    $count = 0;
    foreach ($items as $it) {
        $id = (int)$it['id'];
        if (itemVisibleForRole($id, $role, $itemRoles)) {
            $count++;
        }
    }
    return $count;
}

function itemMatchesSearch(array $item, array $allowedRoles, string $search): bool
{
    if ($search === '') {
        return true;
    }

    $haystack = strtolower(
        ($item['title'] ?? '') . ' ' .
        ($item['url'] ?? '') . ' ' .
        ($item['section'] ?? '') . ' ' .
        ($item['icon'] ?? '') . ' ' .
        ($item['btn_class'] ?? '') . ' ' .
        implode(' ', $allowedRoles)
    );

    return str_contains($haystack, strtolower($search));
}

$filteredItems = [];
foreach ($items as $it) {
    $id = (int)$it['id'];

    if ($activeTab !== 'all' && !itemVisibleForRole($id, $activeTab, $itemRoles)) {
        continue;
    }

    $allowed = $itemRoles[$id] ?? [];
    if (!itemMatchesSearch($it, $allowed, $search)) {
        continue;
    }

    $filteredItems[] = $it;
}

$buttonClasses = [
    'btn-outline-primary',
    'btn-primary',
    'btn-outline-secondary',
    'btn-secondary',
    'btn-outline-dark',
    'btn-warning',
    'btn-danger',
    'btn-success'
];
?>

<div class="container py-4" data-bs-theme="dark">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h3 class="mb-1 text-light">Dashboard Role Builder</h3>
            <div class="text-light">Control which roles can see which dashboard buttons</div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addDashboardItem">
                + Add Dashboard Button
            </button>
            <a href="../admin_panel.php" class="btn btn-outline-secondary btn-sm">← Back to Admin</a>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <!-- ADD NEW ITEM -->
    <div class="collapse show mb-4" id="addDashboardItem">
        <div class="card shadow-sm border-secondary">
            <div class="card-header fw-semibold text-light">Add Dashboard Button</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="create_item" value="1">

                    <div class="col-12 col-md-6">
                        <label class="form-label text-light">Title</label>
                        <input name="title" class="form-control" placeholder="Time Clock" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label text-light">URL</label>
                        <input name="url" class="form-control" placeholder="clock.php" required>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label text-light">Section</label>
                        <input name="section" class="form-control" placeholder="Time Management" value="General" required>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label text-light">Icon (Bootstrap Icons)</label>
                        <input name="icon" class="form-control" placeholder="bi-clock">
                        <div class="small text-light mt-1">Example: bi-clock, bi-tools</div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label text-light">Button Class</label>
                        <select name="btn_class" class="form-select">
                            <?php foreach ($buttonClasses as $class): ?>
                                <option value="<?= htmlspecialchars($class) ?>" <?= $class === 'btn-outline-primary' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label text-light">Sort</label>
                        <input name="sort_order" type="number" class="form-control" value="0">
                    </div>

                    <div class="col-6 col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_enabled" id="new_enabled" checked>
                            <label class="form-check-label text-light" for="new_enabled">Enabled</label>
                        </div>
                    </div>

                    <div class="col-12 col-md-8">
                        <label class="form-label text-light">Roles that can see this</label>
                        <div class="row g-2">
                            <?php foreach ($roles as $r): ?>
                                <div class="col-6 col-md-4">
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="roles[]"
                                            value="<?= htmlspecialchars($r) ?>"
                                            id="new_role_<?= htmlspecialchars($r) ?>"
                                        >
                                        <label class="form-check-label text-light" for="new_role_<?= htmlspecialchars($r) ?>">
                                            <?= htmlspecialchars(roleLabel($r)) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="small text-light mt-2">
                            If you leave roles blank, it will be visible to <strong>all roles</strong>.
                        </div>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary">Add Button</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="card shadow-sm border-secondary mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="mb-0 text-light">Existing Buttons</h5>
                <div class="small text-light">
                    Viewing:
                    <?php if ($activeTab === 'all'): ?>
                        <span class="badge text-bg-light text-dark">All</span>
                    <?php else: ?>
                        <span class="badge <?= roleBadgeClass($activeTab) ?>">
                            <?= htmlspecialchars(roleLabel($activeTab)) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <ul class="nav nav-pills mb-3 flex-wrap gap-2">
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'all' ? 'active' : '' ?>"
                       href="?role=all&search=<?= urlencode($search) ?>">
                        All
                        <span class="badge text-bg-dark ms-1"><?= count($items) ?></span>
                    </a>
                </li>

                <?php foreach ($roles as $r): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === $r ? 'active' : '' ?>"
                           href="?role=<?= urlencode($r) ?>&search=<?= urlencode($search) ?>">
                            <?= htmlspecialchars(roleLabel($r)) ?>
                            <span class="badge text-bg-dark ms-1"><?= visibleCountForRole($r, $items, $itemRoles) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="GET" class="row g-2">
                <input type="hidden" name="role" value="<?= htmlspecialchars($activeTab) ?>">

                <div class="col-12 col-md-9">
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search title, URL, section, icon, button class, or role..."
                    >
                </div>

                <div class="col-6 col-md-2 d-grid">
                    <button class="btn btn-outline-light">Search</button>
                </div>

                <div class="col-6 col-md-1 d-grid">
                    <a href="?role=<?= urlencode($activeTab) ?>" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- CARDS -->
    <?php if (empty($filteredItems)): ?>
        <div class="alert alert-info">No dashboard items found for this view.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($filteredItems as $it): ?>
                <?php
                $id      = (int)$it['id'];
                $allowed = $itemRoles[$id] ?? [];
                ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card shadow-sm border-secondary h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <h5 class="mb-1 text-light"><?= htmlspecialchars($it['title']) ?></h5>
                                    <div class="small text-light">
                                        Section: <?= htmlspecialchars($it['section']) ?>
                                    </div>
                                </div>

                                <?php if ((int)$it['is_enabled'] === 1): ?>
                                    <span class="badge bg-success">Enabled</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Disabled</span>
                                <?php endif; ?>
                            </div>

                            <div class="mb-2">
                                <div class="small text-light mb-1"><strong>URL:</strong></div>
                                <div class="small text-light text-break"><?= htmlspecialchars($it['url']) ?></div>
                            </div>

                            <div class="mb-2">
                                <div class="small text-light mb-1"><strong>Icon:</strong></div>
                                <div class="small text-light"><?= htmlspecialchars($it['icon'] ?: '—') ?></div>
                            </div>

                            <div class="mb-2">
                                <div class="small text-light mb-1"><strong>Button Class:</strong></div>
                                <div class="small text-light"><?= htmlspecialchars($it['btn_class']) ?></div>
                            </div>

                            <div class="mb-2">
                                <div class="small text-light mb-1"><strong>Sort Order:</strong></div>
                                <div class="small text-light"><?= (int)$it['sort_order'] ?></div>
                            </div>

                            <div class="mb-3">
                                <div class="small text-light mb-1"><strong>Visible To:</strong></div>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php if (empty($allowed)): ?>
                                        <span class="badge text-bg-light text-dark">All roles</span>
                                    <?php else: ?>
                                        <?php foreach ($allowed as $ar): ?>
                                            <span class="badge <?= roleBadgeClass($ar) ?>">
                                                <?= htmlspecialchars(roleLabel($ar)) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mt-auto d-flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editModal<?= $id ?>"
                                >
                                    Edit
                                </button>

                                <form method="POST" class="m-0">
                                    <input type="hidden" name="toggle_enabled" value="1">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button class="btn btn-sm btn-outline-success">
                                        <?= (int)$it['is_enabled'] === 1 ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>

                                <a
                                    class="btn btn-sm btn-outline-danger"
                                    href="?role=<?= urlencode($activeTab) ?>&search=<?= urlencode($search) ?>&delete=<?= $id ?>"
                                    onclick="return confirm('Delete this dashboard item?')"
                                >
                                    Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- EDIT MODAL -->
                <div class="modal fade" id="editModal<?= $id ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content bg-dark text-light border-secondary">
                            <form method="POST">
                                <div class="modal-header border-secondary">
                                    <h5 class="modal-title">Edit Dashboard Item</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>

                                <div class="modal-body">
                                    <input type="hidden" name="update_item" value="1">
                                    <input type="hidden" name="id" value="<?= $id ?>">

                                    <div class="row g-3">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-light">Title</label>
                                            <input
                                                class="form-control"
                                                name="title"
                                                value="<?= htmlspecialchars($it['title']) ?>"
                                                required
                                            >
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label text-light">URL</label>
                                            <input
                                                class="form-control"
                                                name="url"
                                                value="<?= htmlspecialchars($it['url']) ?>"
                                                required
                                            >
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-light">Section</label>
                                            <input
                                                class="form-control"
                                                name="section"
                                                value="<?= htmlspecialchars($it['section']) ?>"
                                                required
                                            >
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-light">Icon</label>
                                            <input
                                                class="form-control"
                                                name="icon"
                                                value="<?= htmlspecialchars($it['icon'] ?? '') ?>"
                                                placeholder="bi-clock"
                                            >
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <label class="form-label text-light">Button Class</label>
                                            <select class="form-select" name="btn_class">
                                                <?php foreach ($buttonClasses as $c): ?>
                                                    <option value="<?= htmlspecialchars($c) ?>" <?= $it['btn_class'] === $c ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($c) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-6 col-md-3">
                                            <label class="form-label text-light">Sort Order</label>
                                            <input
                                                class="form-control"
                                                name="sort_order"
                                                type="number"
                                                value="<?= (int)$it['sort_order'] ?>"
                                            >
                                        </div>

                                        <div class="col-6 col-md-3 d-flex align-items-end">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="is_enabled"
                                                    id="enabled_<?= $id ?>"
                                                    <?= (int)$it['is_enabled'] === 1 ? 'checked' : '' ?>
                                                >
                                                <label class="form-check-label text-light" for="enabled_<?= $id ?>">
                                                    Enabled
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label text-light">Roles that can see this</label>
                                            <div class="row g-2">
                                                <?php foreach ($roles as $r): ?>
                                                    <?php $checked = in_array($r, $allowed, true); ?>
                                                    <div class="col-6 col-md-4">
                                                        <div class="form-check">
                                                            <input
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                name="roles[]"
                                                                value="<?= htmlspecialchars($r) ?>"
                                                                id="role_<?= $id ?>_<?= htmlspecialchars($r) ?>"
                                                                <?= $checked ? 'checked' : '' ?>
                                                            >
                                                            <label class="form-check-label text-light" for="role_<?= $id ?>_<?= htmlspecialchars($r) ?>">
                                                                <?= htmlspecialchars(roleLabel($r)) ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <div class="small text-light mt-2">
                                                If you leave roles blank, it will be visible to <strong>all roles</strong>.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>