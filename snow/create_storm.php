<?php
require 'includes/snow_db.php';
require 'includes/snow_functions.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';

$message = '';

// Fetch employees
$employees = $pdo->query("SELECT id, name FROM employees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stormName   = trim($_POST['storm_name']);
    $routeType   = $_POST['route_type'];
    $workedAt    = $_POST['worked_at'];
    $employeeIds = $_POST['employee_ids'] ?? [];

    if ($stormName && $routeType && $workedAt) {

        // 1️⃣ Create storm
        $stmt = $pdo->prepare("
            INSERT INTO snow_storms (storm_name, storm_date)
            VALUES (?, ?)
        ");
        $stmt->execute([$stormName, date('Y-m-d', strtotime($workedAt))]);
        $stormId = $pdo->lastInsertId();

        // Days to add
        $daysToAdd = ($routeType === 'combo') ? 2 : 1;

        // 2️⃣ Loop employees
        foreach ($employeeIds as $employeeId) {

            // Insert first route
            $pdo->prepare("
                INSERT INTO snow_routes
                (employee_id, employee_identifier, route_name, route_type, storm_id, worked_at)
                SELECT id, name, 'FIRST', ?, ?, ?
                FROM employees
                WHERE id = ?
            ")->execute([
                $routeType,
                $stormId,
                $workedAt,
                $employeeId
            ]);

            // Update snow balance
            $pdo->prepare("
                INSERT INTO snow_balances (employee_id, days_ahead)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    days_ahead = days_ahead + VALUES(days_ahead)
            ")->execute([
                $employeeId,
                $daysToAdd
            ]);
        }

        $message = "Storm created and routes added successfully.";

    } else {
        $message = "Please fill out all fields and select at least one employee.";
    }
}
?>

<div class="container mt-5 text-light" data-bs-theme="dark">

    <div class="card">
        <div class="card-body">
            <h4 class="mb-3">Create New Storm</h4>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST">

                <div class="mb-3">
                    <label class="form-label">Storm Name</label>
                    <input class="form-control" name="storm_name" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">First Route Type</label>
                    <select class="form-select" name="route_type" required>
                        <option value="plow">Plow</option>
                        <option value="salt">Salt</option>
                        <option value="combo">Combo</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Add Employees</label>
                    <?php foreach ($employees as $e): ?>
                        <div class="form-check form-switch">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="employee_ids[]"
                                value="<?= $e['id'] ?>"
                                id="emp<?= $e['id'] ?>"
                                switch
                            >
                            <label class="form-check-label" for="emp<?= $e['id'] ?>">
                                <?= htmlspecialchars($e['name']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Date & Time</label>
                    <input
                        type="datetime-local"
                        class="form-control"
                        name="worked_at"
                        value="<?= date('Y-m-d\TH:i') ?>"
                        required
                    >
                </div>

                <button class="btn btn-success w-100">Create Storm</button>
            </form>
        </div>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>