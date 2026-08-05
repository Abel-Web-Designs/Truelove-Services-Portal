<?php
require 'includes/snow_db.php';
require 'includes/snow_functions.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';

$message = '';

// Fetch employees for dropdown
$employees = $pdo->query("SELECT id, name FROM employees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch storms
$storms = $pdo->query("
    SELECT id, storm_name, storm_date
    FROM snow_storms
    ORDER BY storm_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = intval($_POST['employee_id'] ?? 0);
    $route      = trim($_POST['route']);
    $type       = $_POST['route_type'];
    $storm      = $_POST['storm_id'] ?: null;
    $worked_at  = $_POST['worked_at'];

    if ($employeeId && $route && $type && $worked_at) {

        // Insert into snow_routes
        $pdo->prepare("
            INSERT INTO snow_routes
            (employee_id, employee_identifier, route_name, route_type, storm_id, worked_at)
            SELECT ?, name, ?, ?, ?, ?
            FROM employees
            WHERE id = ?
        ")->execute([$employeeId, $route, $type, $storm, $worked_at, $employeeId]);

        // Determine how many days to add based on route type
        $daysToAdd = ($type === 'combo') ? 2 : 1;

        // Update snow_balances
        $pdo->prepare("
            INSERT INTO snow_balances (employee_id, days_ahead)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE days_ahead = days_ahead + ?
        ")->execute([$employeeId, $daysToAdd, $daysToAdd]);

        $message = "Route logged successfully.";
    } else {
        $message = "Please fill out all required fields.";
    }
}
?>

<div class="container mt-5 text-light" data-bs-theme="dark">

    <div class="card">
        <div class="card-body">
            <h4 class="mb-3">Log Snow Route</h4>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST">

                <div class="mb-3">
                    <label class="form-label">Employee</label>
                    <select class="form-select" name="employee_id" required>
                        <option value="">Select employee</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Route Name</label>
                    <input class="form-control" name="route" placeholder="FC1, MC2" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Route Type</label>
                    <select class="form-select" name="route_type" required>
                        <option value="plow">Plow</option>
                        <option value="salt">Salt</option>
                        <option value="combo">Combo</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Storm</label>
                    <select class="form-select" name="storm_id">
                        <option value="">No storm</option>
                        <?php foreach ($storms as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= htmlspecialchars($s['storm_name']) ?> (<?= $s['storm_date'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Date & Time</label>
                    <input type="datetime-local" class="form-control"
                           name="worked_at"
                           value="<?= date('Y-m-d\TH:i') ?>"
                           required>
                </div>

                <button class="btn btn-success w-100">Save Route</button>
            </form>
        </div>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>