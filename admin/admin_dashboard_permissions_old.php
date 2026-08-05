<?php
require '../includes/header.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$roles = ['employee','time_station','work_phone','truck_driver','mechanic','admin'];

$successMsg = '';
$errorMsg = '';

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
                INSERT INTO dashboard_items (title,url,icon,btn_class,section,sort_order,is_enabled)
                VALUES (?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $title,
                $url,
                $icon ?: null,
                $btnClass ?: 'btn-outline-primary',
                $section,
                $sortOrder,
                $enabled
            ]);

            $itemId = (int)$pdo->lastInsertId();

            if (!empty($selectedRoles)) {
                $ins = $pdo->prepare("INSERT INTO dashboard_item_roles (item_id, role) VALUES (?,?)");
                foreach ($selectedRoles as $r) {
                    $ins->execute([$itemId, $r]);
                }
            }

            $pdo->commit();
            $successMsg = "Dashboard item created.";
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errorMsg = "Error creating item.";
        }
    } else {
        $errorMsg = "Title and URL are required.";
    }
}

/* -------------------- UPDATE ITEM (fields + roles) -------------------- */
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
                SET title=?, url=?, icon=?, btn_class=?, section=?, sort_order=?, is_enabled=?
                WHERE id=?
            ");
            $stmt->execute([
                $title,
                $url,
                $icon ?: null,
                $btnClass ?: 'btn-outline-primary',
                $section,
                $sortOrder,
                $enabled,
                $id
            ]);

            // replace roles
            $pdo->prepare("DELETE FROM dashboard_item_roles WHERE item_id=?")->execute([$id]);

            if (!empty($selectedRoles)) {
                $ins = $pdo->prepare("INSERT INTO dashboard_item_roles (item_id, role) VALUES (?,?)");
                foreach ($selectedRoles as $r) {
                    $ins->execute([$id, $r]);
                }
            }

            $pdo->commit();
            $successMsg = "Dashboard item updated.";
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errorMsg = "Error updating item.";
        }
    } else {
        $errorMsg = "Invalid update input.";
    }
}

/* -------------------- DELETE ITEM -------------------- */
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId) {
        $pdo->prepare("DELETE FROM dashboard_items WHERE id=?")->execute([$deleteId]); // cascades roles
    }
    header('Location: admin_dashboard_permissions.php');
    exit();
}

/* -------------------- LOAD ITEMS + ROLES -------------------- */
$items = $pdo->query("
    SELECT * 
    FROM dashboard_items 
    ORDER BY section ASC, sort_order ASC, title ASC
")->fetchAll(PDO::FETCH_ASSOC);

$itemRolesRaw = $pdo->query("SELECT item_id, role FROM dashboard_item_roles")->fetchAll(PDO::FETCH_ASSOC);
$itemRoles = []; // [item_id] => [role, role...]
foreach ($itemRolesRaw as $r) {
    $itemRoles[(int)$r['item_id']][] = $r['role'];
}

/* -------------------- ROLE TABS + FILTERING -------------------- */
$activeTab = $_GET['role'] ?? 'all';
$activeTab = in_array($activeTab, $roles, true) ? $activeTab : 'all';

function itemVisibleForRole(int $itemId, string $role, array $itemRoles): bool {
    $allowed = $itemRoles[$itemId] ?? [];

    // If no roles assigned, treat as "visible to all roles"
    // Change this to `return false;` if you want "no roles = nobody".
    if (empty($allowed)) return true;

    return in_array($role, $allowed, true);
}

function roleLabel(string $r): string {
    // nicer labels for tabs
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

function roleBadgeClass(string $r): string {
    // purely visual; safe defaults
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

function visibleCountForRole(string $role, array $items, array $itemRoles): int {
    $count = 0;
    foreach ($items as $it) {
        $id = (int)$it['id'];
        if (itemVisibleForRole($id, $role, $itemRoles)) $count++;
    }
    return $count;
}

?>
<div class="container mt-5" data-bs-theme="dark">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-0">Dashboard Role Builder</h3>
            <div class="text-muted small">Control which roles can see which dashboard buttons</div>
        </div>
        <a href="../admin_panel.php" class="btn btn-outline-secondary btn-sm">← Back to Admin</a>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <!-- CREATE NEW ITEM -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Add Dashboard Button</div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="create_item" value="1">

                <div class="col-12 col-md-4">
                    <label class="form-label">Title</label>
                    <input name="title" class="form-control" placeholder="Time Clock" required>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">URL</label>
                    <input name="url" class="form-control" placeholder="clock.php" required>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Section (card group)</label>
                    <input name="section" class="form-control" placeholder="Time Management" value="General" required>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label">Icon (Bootstrap Icons)</label>
                    <input name="icon" class="form-control" placeholder="bi-clock">
                    <div class="text-muted small">Example: bi-clock, bi-tools</div>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label">Button Class</label>
                    <select name="btn_class" class="form-select">
                        <option value="btn-outline-primary" selected>btn-outline-primary</option>
                        <option value="btn-primary">btn-primary</option>
                        <option value="btn-outline-secondary">btn-outline-secondary</option>
                        <option value="btn-secondary">btn-secondary</option>
                        <option value="btn-outline-dark">btn-outline-dark</option>
                        <option value="btn-warning">btn-warning</option>
                        <option value="btn-danger">btn-danger</option>
                        <option value="btn-success">btn-success</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Sort</label>
                    <input name="sort_order" type="number" class="form-control" value="0">
                </div>

                <div class="col-6 col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="new_enabled" checked>
                        <label class="form-check-label" for="new_enabled">Enabled</label>
                    </div>
                </div>

                <div class="col-12 col-md-8">
                    <label class="form-label">Roles that can see this</label>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach ($roles as $r): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="roles[]" value="<?= htmlspecialchars($r) ?>" id="new_role_<?= htmlspecialchars($r) ?>">
                                <label class="form-check-label" for="new_role_<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-muted small mt-1">
                        If you leave roles blank, it will be visible to <strong>all roles</strong>.
                    </div>
                </div>

                <div class="col-12 col-md-4 d-grid align-items-end">
                    <button class="btn btn-primary">Add Button</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EXISTING ITEMS (ROLE TABS) -->
    <div class="card shadow-sm">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>Existing Buttons</div>
            <div class="text-muted small">
                Viewing:
                <?php if ($activeTab === 'all'): ?>
                    <span class="badge text-bg-light text-dark">All</span>
                <?php else: ?>
                    <span class="badge <?= roleBadgeClass($activeTab) ?>"><?= htmlspecialchars(roleLabel($activeTab)) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body">

            <!-- Tabs -->
            <ul class="nav nav-pills mb-3 flex-wrap gap-2">
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'all' ? 'active' : '' ?>"
                       href="?role=all">
                        All
                        <span class="badge text-bg-dark ms-1"><?= count($items) ?></span>
                    </a>
                </li>

                <?php foreach ($roles as $r): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === $r ? 'active' : '' ?>"
                           href="?role=<?= urlencode($r) ?>">
                            <?= htmlspecialchars(roleLabel($r)) ?>
                            <span class="badge text-bg-dark ms-1"><?= visibleCountForRole($r, $items, $itemRoles) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php
            // Filter items if a specific role tab is selected
            $filteredItems = [];
            foreach ($items as $it) {
                $id = (int)$it['id'];
                if ($activeTab === 'all' || itemVisibleForRole($id, $activeTab, $itemRoles)) {
                    $filteredItems[] = $it;
                }
            }
            ?>

            <?php if (empty($filteredItems)): ?>
                <div class="alert alert-info mb-0">
                    No dashboard items found for this view.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-striped align-middle">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>URL</th>
                            <th>Section</th>
                            <th>Icon</th>
                            <th>Button</th>
                            <th>Sort</th>
                            <th>Enabled</th>
                            <th style="width: 280px;">Roles</th>
                            <th class="text-end" style="width: 160px;">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($filteredItems as $it): ?>
                            <?php
                            $id = (int)$it['id'];
                            $allowed = $itemRoles[$id] ?? [];
                            ?>
                            <tr>
                                <form method="POST">
                                    <input type="hidden" name="update_item" value="1">
                                    <input type="hidden" name="id" value="<?= $id ?>">

                                    <td>
                                        <input class="form-control form-control-sm"
                                               name="title"
                                               value="<?= htmlspecialchars($it['title']) ?>"
                                               required>
                                        <?php if ($activeTab !== 'all'): ?>
                                            <?php $canSee = itemVisibleForRole($id, $activeTab, $itemRoles); ?>
                                            <div class="small mt-1">
                                                <?php if ($canSee): ?>
                                                    <span class="badge text-bg-success">Visible to <?= htmlspecialchars(roleLabel($activeTab)) ?></span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-secondary">Not visible to <?= htmlspecialchars(roleLabel($activeTab)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td><input class="form-control form-control-sm" name="url" value="<?= htmlspecialchars($it['url']) ?>" required></td>
                                    <td><input class="form-control form-control-sm" name="section" value="<?= htmlspecialchars($it['section']) ?>" required></td>
                                    <td><input class="form-control form-control-sm" name="icon" value="<?= htmlspecialchars($it['icon'] ?? '') ?>" placeholder="bi-..."></td>

                                    <td>
                                        <select class="form-select form-select-sm" name="btn_class">
                                            <?php
                                            $classes = [
                                                'btn-outline-primary','btn-primary',
                                                'btn-outline-secondary','btn-secondary',
                                                'btn-warning','btn-danger','btn-success',
                                                'btn-outline-dark'
                                            ];
                                            foreach ($classes as $c):
                                            ?>
                                                <option value="<?= $c ?>" <?= ($it['btn_class'] === $c ? 'selected' : '') ?>><?= $c ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td><input class="form-control form-control-sm" name="sort_order" type="number" value="<?= (int)$it['sort_order'] ?>"></td>

                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox" name="is_enabled" <?= ((int)$it['is_enabled'] === 1 ? 'checked' : '') ?>>
                                    </td>

                                    <td>
                                        <!-- quick summary badges -->
                                        <div class="d-flex flex-wrap gap-1 mb-2">
                                            <?php if (empty($allowed)): ?>
                                                <span class="badge text-bg-light text-dark">All roles</span>
                                            <?php else: ?>
                                                <?php foreach ($allowed as $ar): ?>
                                                    <span class="badge <?= roleBadgeClass($ar) ?>"><?= htmlspecialchars(roleLabel($ar)) ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>

                                        <!-- editable checkboxes -->
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($roles as $r): ?>
                                                <?php $checked = in_array($r, $allowed, true); ?>
                                                <div class="form-check">
                                                    <input class="form-check-input"
                                                           type="checkbox"
                                                           name="roles[]"
                                                           value="<?= htmlspecialchars($r) ?>"
                                                           id="role_<?= $id ?>_<?= htmlspecialchars($r) ?>"
                                                           <?= $checked ? 'checked' : '' ?>>
                                                    <label class="form-check-label small" for="role_<?= $id ?>_<?= htmlspecialchars($r) ?>">
                                                        <?= htmlspecialchars($r) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button class="btn btn-sm btn-primary">Save</button>
                                            <a class="btn btn-sm btn-outline-danger"
                                               href="?role=<?= urlencode($activeTab) ?>&delete=<?= $id ?>"
                                               onclick="return confirm('Delete this dashboard item?')">Delete</a>
                                        </div>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>