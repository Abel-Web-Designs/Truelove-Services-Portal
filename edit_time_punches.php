<?php
require 'includes/db.php';
require 'includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$successMsg = '';
$errorMsg   = '';

/**
 * Preserve current GET filters on redirect (PRG)
 */
function redirectWithFilters(array $removeKeys = []): void {
    $qs = $_GET;
    foreach ($removeKeys as $k) unset($qs[$k]);
    $redirect = 'edit_time_punches.php' . (!empty($qs) ? ('?' . http_build_query($qs)) : '');
    header("Location: $redirect");
    exit();
}

/* -------------------- UPDATE (single punch) -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_single'])) {
    $id         = (int)($_POST['id'] ?? 0);
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $clockType  = strtolower(trim($_POST['clock_type'] ?? ''));
    $timestamp  = $_POST['timestamp'] ?? '';

    if ($id && $employeeId && in_array($clockType, ['in', 'out'], true) && $timestamp) {
        $stmt = $pdo->prepare("UPDATE time_logs SET employee_id = ?, clock_type = ?, timestamp = ? WHERE id = ?");
        $stmt->execute([$employeeId, $clockType, $timestamp, $id]);
        redirectWithFilters();
    } else {
        $errorMsg = "Invalid update input.";
    }
}

/* -------------------- ADD (missing IN/OUT) -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_single'])) {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $clockType  = strtolower(trim($_POST['clock_type'] ?? ''));
    $timestamp  = $_POST['timestamp'] ?? '';

    if ($employeeId && in_array($clockType, ['in', 'out'], true) && $timestamp) {
        $stmt = $pdo->prepare("INSERT INTO time_logs (employee_id, clock_type, timestamp) VALUES (?, ?, ?)");
        $stmt->execute([$employeeId, $clockType, $timestamp]);
        redirectWithFilters();
    } else {
        $errorMsg = "Invalid add input.";
    }
}

/* -------------------- DELETE -------------------- */
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId) {
        $pdo->prepare("DELETE FROM time_logs WHERE id = ?")->execute([$deleteId]);
    }
    redirectWithFilters(['delete']);
}

/* -------------------- FILTERS -------------------- */
$employees = $pdo->query("SELECT id, name FROM employees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$filterEmployee = (int)($_GET['employee_id'] ?? 0);
$startDate      = $_GET['start_date'] ?? date('Y-m-d', strtotime('-14 days'));
$endDate        = $_GET['end_date'] ?? date('Y-m-d');

// Expand fetch window so overnight OUTs (next day) and INs (prev day) are available to pair
$fetchStart = date('Y-m-d', strtotime($startDate . ' -1 day'));
$fetchEnd   = date('Y-m-d', strtotime($endDate   . ' +1 day'));

$where  = [];
$params = [];

$where[]  = "DATE(t.timestamp) BETWEEN ? AND ?";
$params[] = $fetchStart;
$params[] = $fetchEnd;

if ($filterEmployee) {
    $where[]  = "t.employee_id = ?";
    $params[] = $filterEmployee;
}

$whereSql = "WHERE " . implode(" AND ", $where);

/* -------------------- FETCH LOGS (filtered) -------------------- */
$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.employee_id,
        e.name,
        LOWER(TRIM(t.clock_type)) AS clock_type,
        t.timestamp
    FROM time_logs t
    JOIN employees e ON e.id = t.employee_id
    $whereSql
    ORDER BY t.employee_id ASC, t.timestamp ASC
    LIMIT 3000
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- PAIR ACROSS MIDNIGHT, GROUP BY IN DATE -------------------- */
$grouped = []; // [date][employee_id] => ['name'=>..., 'pairs'=>[]]

if (!empty($logs)) {
    // Group all punches by employee
    $byEmployee = []; // [employee_id] => ['name'=>..., 'punches'=>[]]
    foreach ($logs as $log) {
        $eid = (int)$log['employee_id'];
        if (!isset($byEmployee[$eid])) {
            $byEmployee[$eid] = ['name' => $log['name'], 'punches' => []];
        }
        $byEmployee[$eid]['punches'][] = $log;
    }

    foreach ($byEmployee as $eid => $emp) {
        $punches = $emp['punches']; // already sorted ASC

        // Build IN->OUT pairs across the entire fetched range (handles midnight crossover)
        $pairs  = [];
        $lastIn = null;

        foreach ($punches as $p) {
            $type = $p['clock_type'];

            if ($type === 'in') {
                if ($lastIn) {
                    $pairs[] = ['in' => $lastIn, 'out' => null];
                }
                $lastIn = $p;
            } elseif ($type === 'out') {
                if ($lastIn) {
                    $pairs[] = ['in' => $lastIn, 'out' => $p];
                    $lastIn = null;
                } else {
                    $pairs[] = ['in' => null, 'out' => $p];
                }
            }
        }
        if ($lastIn) {
            $pairs[] = ['in' => $lastIn, 'out' => null];
        }

        foreach ($pairs as $pair) {
            $in  = $pair['in'];
            $out = $pair['out'];

            if ($in) {
                $displayDate = date('Y-m-d', strtotime($in['timestamp']));
                if ($displayDate < $startDate || $displayDate > $endDate) continue;
            } elseif ($out) {
                $displayDate = date('Y-m-d', strtotime($out['timestamp']));
                if ($displayDate < $startDate || $displayDate > $endDate) continue;
            } else {
                continue;
            }

            if (!isset($grouped[$displayDate][$eid])) {
                $grouped[$displayDate][$eid] = [
                    'name'  => $emp['name'],
                    'pairs' => []
                ];
            }
            $grouped[$displayDate][$eid]['pairs'][] = $pair;
        }
    }

    krsort($grouped);

    // Sort employees A-Z by name within each date
    foreach ($grouped as $d => $emps) {
        uasort($grouped[$d], fn($a, $b) => strcmp($a['name'], $b['name']));
    }
}

require 'includes/header.php';
?>

<div class="container mt-5 text-light" data-bs-theme="dark">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-0">Edit Time Punches</h3>
            <div class="text-muted small">Filter first, then edit punches by day. Overnight shifts stay together under the IN date.</div>
        </div>
        <a href="admin_panel.php#time" class="btn btn-outline-secondary btn-sm">← Back to Admin</a>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <!-- FILTERS -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label">Employee</label>
                    <select name="employee_id" class="form-select">
                        <option value="0">All Employees</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= (int)$e['id'] ?>" <?= $filterEmployee === (int)$e['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>" required>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>" required>
                </div>

                <div class="col-12 col-md-2 d-grid">
                    <button class="btn btn-primary">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($grouped)): ?>
        <div class="alert alert-info">No punches found for the selected filters.</div>
    <?php else: ?>

        <?php foreach ($grouped as $date => $emps): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="fw-semibold">
                        <?= date('F j, Y', strtotime($date)) ?>
                    </div>
                    <div class="text-muted small">
                        <?= count($emps) ?> employee(s)
                    </div>
                </div>

                <div class="card-body">
                    <?php foreach ($emps as $eid => $empData): ?>
                        <?php
                        $empName = $empData['name'];
                        $pairs   = $empData['pairs'];
                        ?>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                <div class="fw-semibold"><?= htmlspecialchars($empName) ?></div>
                                <div class="text-muted small"><?= count($pairs) ?> shift(s)</div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-dark table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 120px;">Shift</th>
                                            <th>Clock In</th>
                                            <th>Clock Out</th>
                                            <th class="text-end" style="width: 240px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pairs as $i => $pair): ?>
                                            <?php
                                            $in  = $pair['in'];
                                            $out = $pair['out'];

                                            $shiftLabel = 'Shift ' . ($i + 1);

                                            $duration = '';
                                            if ($in && $out) {
                                                $sec = strtotime($out['timestamp']) - strtotime($in['timestamp']);
                                                if ($sec > 0) {
                                                    $h = floor($sec / 3600);
                                                    $m = floor(($sec % 3600) / 60);
                                                    $duration = sprintf('%d:%02d', $h, $m);
                                                }
                                            }

                                            // Collapse IDs (safe)
                                            $inCollapseId  = $in  ? ('edit_in_'  . $date . '_' . (int)$in['id'])  : '';
                                            $outCollapseId = $out ? ('edit_out_' . $date . '_' . (int)$out['id']) : '';

                                            // "Add" collapse IDs (need stable unique key even when punch missing)
                                            $addInCollapseId  = 'add_in_'  . $date . '_' . (int)$eid . '_' . $i;
                                            $addOutCollapseId = 'add_out_' . $date . '_' . (int)$eid . '_' . $i;

                                            // Suggested timestamps for missing punches:
                                            // - missing IN: use OUT time minus 1 hour if available, else 8:00 AM that date
                                            // - missing OUT: use IN time plus 1 hour if available, else 5:00 PM that date
                                            $suggestInTs = $out
                                                ? date('Y-m-d\TH:i', strtotime($out['timestamp'] . ' -1 hour'))
                                                : ($date . 'T08:00');

                                            $suggestOutTs = $in
                                                ? date('Y-m-d\TH:i', strtotime($in['timestamp'] . ' +1 hour'))
                                                : ($date . 'T17:00');
                                            ?>

                                            <tr>
                                                <td class="fw-semibold">
                                                    <?= $shiftLabel ?>
                                                    <?php if ($duration): ?>
                                                        <div class="text-muted small">Total: <?= $duration ?></div>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php if ($in): ?>
                                                        <span class="badge bg-success">IN</span>
                                                        <?= date('g:i A', strtotime($in['timestamp'])) ?>
                                                        <div class="text-muted small"><?= date('M j, Y', strtotime($in['timestamp'])) ?></div>
                                                    <?php else: ?>
                                                        <span class="text-warning">
                                                            Missing IN
                                                            <button class="btn btn-outline-secondary btn-sm ms-2 text-light"
                                                                    type="button"
                                                                    data-bs-toggle="collapse"
                                                                    data-bs-target="#<?= htmlspecialchars($addInCollapseId) ?>">
                                                                Add IN
                                                            </button>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php if ($out): ?>
                                                        <span class="badge bg-danger">OUT</span>
                                                        <?= date('g:i A', strtotime($out['timestamp'])) ?>
                                                        <div class="text-muted small"><?= date('M j, Y', strtotime($out['timestamp'])) ?></div>
                                                    <?php else: ?>
                                                        <span class="text-warning">
                                                            Missing OUT
                                                            <button class="btn btn-outline-secondary btn-sm ms-2 text-light"
                                                                    type="button"
                                                                    data-bs-toggle="collapse"
                                                                    data-bs-target="#<?= htmlspecialchars($addOutCollapseId) ?>">
                                                                Add OUT
                                                            </button>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="text-end">
                                                    <div class="d-inline-flex gap-2 flex-wrap justify-content-end">

                                                        <?php if ($in): ?>
                                                            <button class="btn btn-outline-success btn-sm"
                                                                    type="button"
                                                                    data-bs-toggle="collapse"
                                                                    data-bs-target="#<?= htmlspecialchars($inCollapseId) ?>">
                                                                Edit IN
                                                            </button>
                                                            <a class="btn btn-outline-danger btn-sm"
                                                               href="?<?= http_build_query(array_merge($_GET, ['delete' => (int)$in['id']])) ?>"
                                                               onclick="return confirm('Delete this IN punch?')">
                                                                Delete IN
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php if ($out): ?>
                                                            <button class="btn btn-outline-warning btn-sm text-light"
                                                                    type="button"
                                                                    data-bs-toggle="collapse"
                                                                    data-bs-target="#<?= htmlspecialchars($outCollapseId) ?>">
                                                                Edit OUT
                                                            </button>
                                                            <a class="btn btn-outline-danger btn-sm"
                                                               href="?<?= http_build_query(array_merge($_GET, ['delete' => (int)$out['id']])) ?>"
                                                               onclick="return confirm('Delete this OUT punch?')">
                                                                Delete OUT
                                                            </a>
                                                        <?php endif; ?>

                                                    </div>
                                                </td>
                                            </tr>

                                            <?php if ($in): ?>
                                                <tr class="collapse" id="<?= htmlspecialchars($inCollapseId) ?>">
                                                    <td colspan="4">
                                                        <form method="POST" class="row g-2 align-items-end">
                                                            <input type="hidden" name="update_single" value="1">
                                                            <input type="hidden" name="id" value="<?= (int)$in['id'] ?>">

                                                            <div class="col-12 col-md-4">
                                                                <label class="form-label">Employee</label>
                                                                <select name="employee_id" class="form-select form-select-sm" required>
                                                                    <?php foreach ($employees as $e): ?>
                                                                        <option value="<?= (int)$e['id'] ?>" <?= (int)$e['id'] === (int)$in['employee_id'] ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($e['name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>

                                                            <div class="col-6 col-md-3">
                                                                <label class="form-label">Type</label>
                                                                <select name="clock_type" class="form-select form-select-sm" required>
                                                                    <option value="in" selected>Clock In</option>
                                                                    <option value="out">Clock Out</option>
                                                                </select>
                                                            </div>

                                                            <div class="col-6 col-md-3">
                                                                <label class="form-label">Timestamp</label>
                                                                <input type="datetime-local" name="timestamp"
                                                                       class="form-control form-control-sm"
                                                                       value="<?= date('Y-m-d\TH:i', strtotime($in['timestamp'])) ?>" required>
                                                            </div>

                                                            <div class="col-12 col-md-2 d-grid">
                                                                <button class="btn btn-primary btn-sm" type="submit">Save</button>
                                                            </div>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>

                                            <?php if ($out): ?>
                                                <tr class="collapse" id="<?= htmlspecialchars($outCollapseId) ?>">
                                                    <td colspan="4">
                                                        <form method="POST" class="row g-2 align-items-end">
                                                            <input type="hidden" name="update_single" value="1">
                                                            <input type="hidden" name="id" value="<?= (int)$out['id'] ?>">

                                                            <div class="col-12 col-md-4">
                                                                <label class="form-label">Employee</label>
                                                                <select name="employee_id" class="form-select form-select-sm" required>
                                                                    <?php foreach ($employees as $e): ?>
                                                                        <option value="<?= (int)$e['id'] ?>" <?= (int)$e['id'] === (int)$out['employee_id'] ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($e['name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>

                                                            <div class="col-6 col-md-3">
                                                                <label class="form-label">Type</label>
                                                                <select name="clock_type" class="form-select form-select-sm" required>
                                                                    <option value="in">Clock In</option>
                                                                    <option value="out" selected>Clock Out</option>
                                                                </select>
                                                            </div>

                                                            <div class="col-6 col-md-3">
                                                                <label class="form-label">Timestamp</label>
                                                                <input type="datetime-local" name="timestamp"
                                                                       class="form-control form-control-sm"
                                                                       value="<?= date('Y-m-d\TH:i', strtotime($out['timestamp'])) ?>" required>
                                                            </div>

                                                            <div class="col-12 col-md-2 d-grid">
                                                                <button class="btn btn-primary btn-sm" type="submit">Save</button>
                                                            </div>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>

                                            <!-- ADD IN (when missing) -->
                                            <?php if (!$in): ?>
                                                <tr class="collapse" id="<?= htmlspecialchars($addInCollapseId) ?>">
                                                    <td colspan="4">
                                                        <form method="POST" class="row g-2 align-items-end">
                                                            <input type="hidden" name="add_single" value="1">
                                                            <input type="hidden" name="employee_id" value="<?= (int)$eid ?>">
                                                            <input type="hidden" name="clock_type" value="in">

                                                            <div class="col-12 col-md-6">
                                                                <label class="form-label">Timestamp (Clock In)</label>
                                                                <input type="datetime-local" name="timestamp"
                                                                       class="form-control form-control-sm"
                                                                       value="<?= htmlspecialchars($suggestInTs) ?>" required>
                                                            </div>

                                                            <div class="col-12 col-md-2 d-grid">
                                                                <button class="btn btn-success btn-sm" type="submit">Add IN</button>
                                                            </div>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>

                                            <!-- ADD OUT (when missing) -->
                                            <?php if (!$out): ?>
                                                <tr class="collapse" id="<?= htmlspecialchars($addOutCollapseId) ?>">
                                                    <td colspan="4">
                                                        <form method="POST" class="row g-2 align-items-end">
                                                            <input type="hidden" name="add_single" value="1">
                                                            <input type="hidden" name="employee_id" value="<?= (int)$eid ?>">
                                                            <input type="hidden" name="clock_type" value="out">

                                                            <div class="col-12 col-md-6">
                                                                <label class="form-label">Timestamp (Clock Out)</label>
                                                                <input type="datetime-local" name="timestamp"
                                                                       class="form-control form-control-sm"
                                                                       value="<?= htmlspecialchars($suggestOutTs) ?>" required>
                                                            </div>

                                                            <div class="col-12 col-md-2 d-grid">
                                                                <button class="btn btn-warning btn-sm text-dark" type="submit">Add OUT</button>
                                                            </div>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>

                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php endforeach; ?>
                </div>
            </div>
            <br>
            <hr>
            <br>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
