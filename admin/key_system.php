<?php
// admin/key_system.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// OPTIONAL: restrict to admin/manager only
// if (getUserRole() !== 'admin') { header("Location: ../dashboard.php"); exit; }

date_default_timezone_set('America/Indiana/Indianapolis');

$EMP_TABLE = 'employees'; // or 'employee'

function json_out($arr)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

function equipment_display($row)
{
    $parts = [];
    if (!empty($row['equipment_type'])) $parts[] = $row['equipment_type'];
    if (!empty($row['equipment_name'])) $parts[] = $row['equipment_name'];
    $txt = implode(' — ', $parts);
    if (!empty($row['equipment_serial'])) $txt .= " (VIN: " . $row['equipment_serial'] . ")";
    return $txt ?: 'Equipment';
}

$action = $_GET['action'] ?? '';
$errors = [];

/* -------------------- AJAX: SEARCH EMPLOYEES (ACTIVE ONLY) -------------------- */
if ($action === 'search_employees') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') json_out([]);

    $sql = "
        SELECT `id`, `name`
        FROM `$EMP_TABLE`
        WHERE `is_active` = 1
          AND `name` LIKE ?
        ORDER BY `name`
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['%' . $q . '%']);
    json_out($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* -------------------- AJAX: SEARCH EQUIPMENT -------------------- */
if ($action === 'search_equipment') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') json_out([]);

    $stmt = $pdo->prepare("
        SELECT
            `id`,
            `name`,
            `type_new`,
            `serial_number`
        FROM `equipment`
        WHERE (`name` LIKE ? OR `type_new` LIKE ? OR `serial_number` LIKE ?)
        ORDER BY `type_new`, `name`
        LIMIT 10
    ");
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like, $like]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $labelParts = [];
        if (!empty($r['type_new'])) $labelParts[] = $r['type_new'];
        if (!empty($r['name'])) $labelParts[] = $r['name'];
        $label = implode(' — ', $labelParts);

        if (!empty($r['serial_number'])) {
            $label .= " (VIN: " . $r['serial_number'] . ")";
        }

        $rows[] = [
            'id' => (int)$r['id'],
            'label' => $label,
            'name' => $r['name'],
            'type_new' => $r['type_new'],
            'serial_number' => $r['serial_number'],
        ];
    }

    json_out($rows);
}

/* -------------------- CHECKOUT -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'checkout') {
    $equipment_id = (int)($_POST['equipment_id'] ?? 0);
    $employee_id  = (int)($_POST['employee_id'] ?? 0);
    $notes        = trim($_POST['notes'] ?? '');

    if ($equipment_id <= 0) $errors[] = "Select a piece of equipment.";
    if ($employee_id <= 0)  $errors[] = "Select an employee.";

    if (!$errors) {
        $sql = "SELECT COUNT(*) FROM `$EMP_TABLE` WHERE `id` = ? AND `is_active` = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employee_id]);
        if ((int)$stmt->fetchColumn() !== 1) {
            $errors[] = "Employee is not active (or not found).";
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `equipment` WHERE `id` = ?");
        $stmt->execute([$equipment_id]);
        if ((int)$stmt->fetchColumn() !== 1) {
            $errors[] = "Equipment not found.";
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `key_checkouts` WHERE `equipment_id` = ? AND `checked_in_at` IS NULL");
        $stmt->execute([$equipment_id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = "That equipment is currently checked out.";
        }
    }

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $stmt = $pdo->prepare("
            INSERT INTO `key_checkouts`
                (`equipment_id`, `employee_id`, `checked_out_at`, `notes`, `checked_out_by_user_id`)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $equipment_id,
            $employee_id,
            $now,
            ($notes !== '' ? $notes : null),
            ($userId ?: null)
        ]);

        header("Location: key_system.php?ok=checked_out");
        exit;
    }
}

/* -------------------- CHECKIN -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'checkin') {
    $checkout_id = (int)($_POST['checkout_id'] ?? 0);

    if ($checkout_id <= 0) {
        $errors[] = "Invalid checkout record.";
    } else {
        $now = date('Y-m-d H:i:s');
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $stmt = $pdo->prepare("
            UPDATE `key_checkouts`
            SET `checked_in_at` = ?, `checked_in_by_user_id` = ?
            WHERE `id` = ? AND `checked_in_at` IS NULL
        ");
        $stmt->execute([$now, ($userId ?: null), $checkout_id]);

        header("Location: key_system.php?ok=checked_in");
        exit;
    }
}

/* -------------------- PAGE DATA -------------------- */
$flashOk = $_GET['ok'] ?? '';
$flashErr = $errors ? implode(' ', $errors) : '';

$currentSql = "
    SELECT
        kc.id AS checkout_id,
        e.`name` AS employee_name,
        kc.checked_out_at,
        kc.notes,
        eq.id AS equipment_id,
        eq.`name` AS equipment_name,
        eq.`type_new` AS equipment_type,
        eq.`serial_number` AS equipment_serial
    FROM `key_checkouts` kc
    JOIN `equipment` eq ON eq.id = kc.equipment_id
    JOIN `$EMP_TABLE` e ON e.id = kc.employee_id
    WHERE kc.checked_in_at IS NULL
    ORDER BY kc.checked_out_at DESC
";
$current = $pdo->query($currentSql)->fetchAll(PDO::FETCH_ASSOC);

$hist_date = $_GET['hist_date'] ?? date('Y-m-d');
$hist_eq   = trim($_GET['hist_equipment'] ?? '');
$hist_emp  = trim($_GET['hist_emp'] ?? '');

$historyRows = [];
if ($hist_date) {
    $dayStart = $hist_date . " 00:00:00";
    $dayEnd   = $hist_date . " 23:59:59";

    $sql = "
        SELECT
            kc.id,
            e.`name` AS employee_name,
            kc.checked_out_at,
            kc.checked_in_at,
            kc.notes,
            eq.`type_new` AS equipment_type,
            eq.`name` AS equipment_name,
            eq.`serial_number` AS equipment_serial
        FROM `key_checkouts` kc
        JOIN `equipment` eq ON eq.id = kc.equipment_id
        JOIN `$EMP_TABLE` e ON e.id = kc.employee_id
        WHERE kc.checked_out_at <= :dayEnd
          AND (kc.checked_in_at IS NULL OR kc.checked_in_at >= :dayStart)
    ";

    $params = [':dayStart' => $dayStart, ':dayEnd' => $dayEnd];

    if ($hist_eq !== '') {
        $sql .= " AND (eq.`name` LIKE :eq OR eq.`type_new` LIKE :eq OR eq.`serial_number` LIKE :eq) ";
        $params[':eq'] = '%' . $hist_eq . '%';
    }

    if ($hist_emp !== '') {
        $sql .= " AND e.`name` LIKE :emp ";
        $params[':emp'] = '%' . $hist_emp . '%';
    }

    $sql .= " ORDER BY eq.`type_new`, eq.`name`, e.`name`, kc.checked_out_at DESC ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $historyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1 text-light">Equipment / Key Check Out</h1>
            <div class="text-light">Manage check-outs, returns, and history.</div>
        </div>
        <a href="../dashboard.php" class="btn btn-outline-light">Back to Dashboard</a>
    </div>

    <?php if ($flashOk === 'checked_out'): ?>
        <div class="alert alert-success">Equipment checked out successfully.</div>
    <?php elseif ($flashOk === 'checked_in'): ?>
        <div class="alert alert-success">Equipment checked in successfully.</div>
    <?php endif; ?>

    <?php if ($flashErr): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($flashErr) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="card bg-black border-secondary shadow-sm h-100">
                <div class="card-header border-secondary text-light fw-semibold">
                    Check Out Equipment
                </div>
                <div class="card-body">
                    <form method="post" autocomplete="off">
                        <input type="hidden" name="form" value="checkout">

                        <div class="mb-3 position-relative">
                            <label class="form-label text-light">Equipment / Truck / Key</label>
                            <input id="eqSearch" class="form-control bg-dark text-light border-secondary"
                                   placeholder="Search by name, type, or serial...">
                            <input type="hidden" name="equipment_id" id="equipment_id">
                            <div id="eqResults" class="list-group position-absolute w-100 mt-1 shadow-sm" style="z-index: 1050;"></div>
                            <div class="form-text text-light">Searches equipment name, type, and serial number.</div>
                        </div>

                        <div class="mb-3 position-relative">
                            <label class="form-label text-light">Employee</label>
                            <input id="empSearch" class="form-control bg-dark text-light border-secondary"
                                   placeholder="Search active employee...">
                            <input type="hidden" name="employee_id" id="employee_id">
                            <div id="empResults" class="list-group position-absolute w-100 mt-1 shadow-sm" style="z-index: 1050;"></div>
                            <div class="form-text text-light">Only active employees appear here.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-light">Notes</label>
                            <textarea name="notes" rows="3" class="form-control bg-dark text-light border-secondary"
                                      placeholder="Optional notes..."></textarea>
                        </div>

                        <div class="d-grid">
                            <button class="btn btn-primary">Check Out</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card bg-black border-secondary shadow-sm h-100">
                <div class="card-header border-secondary text-light fw-semibold d-flex justify-content-between align-items-center">
                    <span>Currently Checked Out</span>
                    <span class="badge bg-primary"><?= count($current) ?></span>
                </div>
                <div class="card-body">
                    <?php if (!$current): ?>
                        <div class="alert alert-info mb-0">Nothing is currently checked out.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Employee</th>
                                    <th>Checked Out</th>
                                    <th>Notes</th>
                                    <th class="text-end">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($current as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-light"><?= htmlspecialchars(equipment_display($row)) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                        <td>
                                            <div><?= date('m/d/Y', strtotime($row['checked_out_at'])) ?></div>
                                            <small class="text-light"><?= date('g:i A', strtotime($row['checked_out_at'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($row['notes'] ?? '') ?: '<span class="text-light">—</span>' ?></td>
                                        <td class="text-end">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="form" value="checkin">
                                                <input type="hidden" name="checkout_id" value="<?= (int)$row['checkout_id'] ?>">
                                                <button class="btn btn-success btn-sm">Check In</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card bg-black border-secondary shadow-sm">
                <div class="card-header border-secondary text-light fw-semibold">
                    History Search
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end mb-4">
                        <div class="col-12 col-md-3">
                            <label class="form-label text-light">Date</label>
                            <input type="date" name="hist_date" value="<?= htmlspecialchars($hist_date) ?>"
                                   class="form-control bg-dark text-light border-secondary">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label text-light">Equipment Filter</label>
                            <input type="text" name="hist_equipment" value="<?= htmlspecialchars($hist_eq) ?>"
                                   class="form-control bg-dark text-light border-secondary"
                                   placeholder="Truck, type, serial...">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label text-light">Employee Filter</label>
                            <input type="text" name="hist_emp" value="<?= htmlspecialchars($hist_emp) ?>"
                                   class="form-control bg-dark text-light border-secondary"
                                   placeholder="Employee name...">
                        </div>

                        <div class="col-12 col-md-1 d-grid">
                            <button class="btn btn-outline-light">Go</button>
                        </div>
                    </form>

                    <?php if (!$historyRows): ?>
                        <div class="alert alert-info mb-0">No matching results for that date/filter.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Employee</th>
                                    <th>Checked Out</th>
                                    <th>Checked In</th>
                                    <th>Notes</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($historyRows as $r): ?>
                                    <tr>
                                        <td class="fw-semibold text-light"><?= htmlspecialchars(equipment_display($r)) ?></td>
                                        <td><?= htmlspecialchars($r['employee_name']) ?></td>
                                        <td>
                                            <div><?= date('m/d/Y', strtotime($r['checked_out_at'])) ?></div>
                                            <small class="text-light"><?= date('g:i A', strtotime($r['checked_out_at'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($r['checked_in_at']): ?>
                                                <div><?= date('m/d/Y', strtotime($r['checked_in_at'])) ?></div>
                                                <small class="text-light"><?= date('g:i A', strtotime($r['checked_in_at'])) ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Still Out</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($r['notes'] ?? '') ?: '<span class="text-light">—</span>' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .list-group-item.bg-dark:hover {
        background-color: #1f1f1f !important;
    }
    .table > :not(caption) > * > * {
        background-color: transparent !important;
        color: #f8f9fa !important;
        border-color: #495057 !important;
    }
</style>

<script>
async function typeahead(inputEl, resultsEl, endpoint, onPick) {
    let last = "";

    inputEl.addEventListener("input", async () => {
        const q = inputEl.value.trim();
        if (q === last) return;
        last = q;

        resultsEl.innerHTML = "";
        if (q.length < 1) return;

        const res = await fetch(`key_system.php?action=${endpoint}&q=` + encodeURIComponent(q));
        const items = await res.json();

        resultsEl.innerHTML = "";
        items.forEach(item => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "list-group-item list-group-item-action bg-dark text-light border-secondary";
            btn.textContent = item.label ?? item.name ?? "Select";

            btn.addEventListener("click", () => {
                onPick(item);
                resultsEl.innerHTML = "";
            });

            resultsEl.appendChild(btn);
        });
    });

    inputEl.addEventListener("blur", () => setTimeout(() => resultsEl.innerHTML = "", 150));
}

typeahead(
    document.getElementById("eqSearch"),
    document.getElementById("eqResults"),
    "search_equipment",
    (item) => {
        document.getElementById("eqSearch").value = item.label;
        document.getElementById("equipment_id").value = item.id;
    }
);

typeahead(
    document.getElementById("empSearch"),
    document.getElementById("empResults"),
    "search_employees",
    (item) => {
        document.getElementById("empSearch").value = item.name;
        document.getElementById("employee_id").value = item.id;
    }
);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>