<?php
require 'includes/snow_db.php';
require 'includes/snow_functions.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';

// Fetch employees for dropdown
$employees = $pdo->query("SELECT id, name FROM employees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4 text-light" data-bs-theme="dark">

    <h3 class="text-center mb-4">🚚 Truck Route Entry</h3>

    <form method="POST" action="log_route.php">
        <input type="hidden" name="worked_at" value="<?= date('Y-m-d H:i:s') ?>">

        <div class="mb-3">
            <select class="form-select form-select-lg text-center" name="employee_id" required>
                <option value="">Select Employee</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= (int)$emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <input class="form-control form-control-lg text-center"
                   name="route"
                   placeholder="Route (FC1, MC2)"
                   required>
        </div>

        <div class="mb-3 d-grid gap-2">
            <button name="route_type" value="plow"
                    class="btn btn-info btn-lg">
                ❄️ PLOW
            </button>
            <button name="route_type" value="salt"
                    class="btn btn-warning btn-lg text-dark">
                🧂 SALT
            </button>
            <button name="route_type" value="salt"
                    class="btn btn-warning btn-lg text-dark">
                ❄️ + 🧂 COMBO
            </button>
        </div>

        <button class="btn btn-success btn-lg w-100 mt-3">
            Submit Route
        </button>

    </form>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
