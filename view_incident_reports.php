<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$errors = [];

/* -------------------- FILTERS -------------------- */
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';
$q     = trim($_GET['q'] ?? '');
$emp   = (int)($_GET['emp'] ?? 0);

$startObj = $start ? DateTime::createFromFormat('Y-m-d', $start) : null;
$endObj   = $end   ? DateTime::createFromFormat('Y-m-d', $end)   : null;

if ($start && !$startObj) $errors[] = "Invalid start date.";
if ($end && !$endObj)     $errors[] = "Invalid end date.";
if ($startObj && $endObj && $endObj < $startObj) $errors[] = "End date must be after start date.";

/* -------------------- EMPLOYEE LIST FOR FILTER -------------------- */
$employees = $pdo->query("SELECT id, name FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- BUILD QUERY -------------------- */
$where = [];
$params = [];

if ($startObj) {
    $where[] = "ir.incident_date >= ?";
    $params[] = $startObj->format('Y-m-d');
}
if ($endObj) {
    $where[] = "ir.incident_date <= ?";
    $params[] = $endObj->format('Y-m-d');
}
if ($q !== '') {
    $where[] = "(ir.equipment_involved LIKE ? OR ir.incident_details LIKE ? OR ir.incident_reason LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$sql = "
    SELECT
        ir.*,
        e.name AS created_by_name
    FROM incident_reports ir
    LEFT JOIN employees e ON e.id = ir.created_by
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY ir.incident_date DESC, ir.incident_time DESC, ir.id DESC";

$reports = [];
if (empty($errors)) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errors[] = "Failed to load incident reports.";
        // TEMP DEBUG:
        // $errors[] = $e->getMessage();
    }
}

/* -------------------- OPTIONAL: FILTER BY EMPLOYEE INVOLVED --------------------
   Since employee_ids_json is JSON stored in TEXT, we filter in PHP (portable).
   If you want MySQL JSON functions, tell me your MySQL/MariaDB version.
------------------------------------------------------------------------------- */
if ($emp > 0 && !empty($reports)) {
    $reports = array_values(array_filter($reports, function($r) use ($emp) {
        $ids = json_decode($r['employee_ids_json'] ?? '[]', true);
        if (!is_array($ids)) return false;
        return in_array($emp, array_map('intval', $ids), true);
    }));
}

/* -------------------- MAP EMPLOYEE IDS -> NAMES (for display) -------------------- */
$empNameById = [];
foreach ($employees as $e) {
    $empNameById[(int)$e['id']] = $e['name'];
}

function safeJsonArray($s): array {
    $arr = json_decode((string)$s, true);
    return is_array($arr) ? $arr : [];
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

require 'includes/header.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Incident Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark">
<div class="container mt-5">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="text-light mb-0">Incident Reports</h2>
        <div class="d-flex gap-2">
            <a href="incident_report.php" class="btn btn-outline-primary btn-sm text-light">+ New Report</a>
            <a href="admin_panel.php" class="btn btn-outline-secondary btn-sm text-light">Back</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card bg-dark border-secondary mb-3">
        <div class="card-body">

            <form method="GET" class="row g-2 align-items-end">

                <div class="col-12 col-md-3">
                    <label class="form-label text-light mb-0">Start</label>
                    <input type="date" name="start" class="form-control" value="<?= h($start) ?>">
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label text-light mb-0">End</label>
                    <input type="date" name="end" class="form-control" value="<?= h($end) ?>">
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label text-light mb-0">Employee Involved</label>
                    <select name="emp" class="form-select">
                        <option value="0">All</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= (int)$e['id'] ?>" <?= ((int)$e['id'] === $emp) ? 'selected' : '' ?>>
                                <?= h($e['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label text-light mb-0">Search</label>
                    <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="equipment, details, reason...">
                </div>

                <div class="col-12 d-flex gap-2 flex-wrap mt-2">
                    <button class="btn btn-primary">Filter</button>
                    <a href="incident_reports.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>

        </div>
    </div>

    <?php if (empty($reports)): ?>
        <div class="alert alert-info">No incident reports found.</div>
    <?php else: ?>

        <div class="text-light small mb-2">
            Showing <strong><?= (int)count($reports) ?></strong> report(s)
        </div>

        <div class="accordion" id="incidentAccordion">
            <?php foreach ($reports as $i => $r): ?>
                <?php
                    $id = (int)$r['id'];

                    $employeeIds = safeJsonArray($r['employee_ids_json'] ?? '[]');
                    $employeeNames = [];
                    foreach ($employeeIds as $eid) {
                        $eid = (int)$eid;
                        if (isset($empNameById[$eid])) $employeeNames[] = $empNameById[$eid];
                        else $employeeNames[] = "Employee #{$eid}";
                    }

                    $photos = safeJsonArray($r['photos_json'] ?? '[]');
                    $when = trim(($r['incident_date'] ?? '') . ' ' . substr((string)($r['incident_time'] ?? ''), 0, 5));
                    $headerText = $when ?: ('Report #' . $id);

                    $collapseId = "inc_collapse_$id";
                    $headingId  = "inc_heading_$id";
                ?>
                <div class="accordion-item bg-dark border-secondary mb-2">
                    <h2 class="accordion-header" id="<?= h($headingId) ?>">
                        <button class="accordion-button collapsed bg-dark text-light border-secondary" type="button"
                                data-bs-toggle="collapse" data-bs-target="#<?= h($collapseId) ?>" aria-expanded="false"
                                aria-controls="<?= h($collapseId) ?>">
                            <div class="w-100 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="fw-semibold">
                                    <?= h($headerText) ?>
                                    <span class="text-muted small ms-2">(#<?= $id ?>)</span>
                                </div>
                                <div class="small text-muted">
                                    <?= h(implode(', ', $employeeNames) ?: 'No employees listed') ?>
                                </div>
                            </div>
                        </button>
                    </h2>

                    <div id="<?= h($collapseId) ?>" class="accordion-collapse collapse" aria-labelledby="<?= h($headingId) ?>"
                         data-bs-parent="#incidentAccordion">
                        <div class="accordion-body text-light">

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="text-light small">Employees Involved</div>
                                    <div><?= h(implode(', ', $employeeNames) ?: '—') ?></div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="text-light small">Submitted By</div>
                                    <div class="text-light"><?= h($r['created_by_name'] ?? 'Unknown') ?> <span class="text-muted small">(<?= h($r['created_at'] ?? '') ?>)</span></div>
                                </div>

                                <div class="col-12">
                                    <div class="text-light small">Equipment Involved</div>
                                    <div class="bg-black bg-opacity-25 p-2 rounded"><?= nl2br(h($r['equipment_involved'] ?? '')) ?></div>
                                </div>

                                <div class="col-12">
                                    <div class="text-light small">Incident Details</div>
                                    <div class="bg-black bg-opacity-25 p-2 rounded"><?= nl2br(h($r['incident_details'] ?? '')) ?></div>
                                </div>

                                <div class="col-12">
                                    <div class="text-light small">Reason the Incident Occurred</div>
                                    <div class="bg-black bg-opacity-25 p-2 rounded"><?= nl2br(h($r['incident_reason'] ?? '')) ?></div>
                                </div>

                                <?php if (!empty($photos)): ?>
                                    <div class="col-12">
                                        <div class="text-light small mb-2">Photos</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($photos as $ph): ?>
                                                <?php
                                                    $ph = basename((string)$ph); // basic safety
                                                    $url = 'uploads/incidents/' . $ph;
                                                ?>
                                                <a href="<?= h($url) ?>" target="_blank" class="text-decoration-none">
                                                    <img src="<?= h($url) ?>" alt="photo" class="rounded border border-secondary"
                                                         style="width: 140px; height: 140px; object-fit: cover;">
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-3">
                                    <a href="incident_view.php?id=<?= $id ?>" class="btn btn-outline-light btn-sm">
                                        View Full Report
                                    </a>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>