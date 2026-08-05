<?php
require 'includes/snow_db.php';
require 'includes/snow_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

$employeeId = intval($_GET['id'] ?? 0);

if (!$employeeId) {
    echo "<div class='alert alert-danger m-4'>Employee ID not provided.</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Get employee info
$stmt = $pdo->prepare("
    SELECT e.name, sb.days_ahead
    FROM snow_balances sb
    JOIN employees e ON e.id = sb.employee_id
    WHERE sb.employee_id = ?
");
$stmt->execute([$employeeId]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    echo "<div class='alert alert-danger m-4'>Employee not found.</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Get route history
$stmt = $pdo->prepare("
    SELECT route_name, worked_at
    FROM snow_routes
    WHERE employee_id = ?
    ORDER BY worked_at DESC
");
$stmt->execute([$employeeId]);
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Optional message after reset via POST
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_days'])) {
    $stmt = $pdo->prepare("UPDATE snow_balances SET days_ahead = 0 WHERE employee_id = ?");
    $stmt->execute([$employeeId]);
    $message = "Days ahead reset for {$emp['name']} successfully!";
    $emp['days_ahead'] = 0;
}
?>

<div class="container mt-5 text-light" data-bs-theme="dark">
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between mb-3">
        <h3><?= htmlspecialchars($emp['name']) ?></h3>
        <a href="index.php" class="btn btn-outline-secondary">← Back</a>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <h5 class="mb-3">Days Ahead Controls</h5>

            <div class="d-flex gap-2 flex-wrap">

                <!-- +1 -->
                <form method="POST" action="adjust_days_ahead.php">
                    <input type="hidden" name="employee_id" value="<?= (int)$employeeId ?>">
                    <input type="hidden" name="action" value="add">
                    <button class="btn btn-success">➕ Add Day</button>
                </form>

                <!-- -1 -->
                <form method="POST" action="adjust_days_ahead.php">
                    <input type="hidden" name="employee_id" value="<?= (int)$employeeId ?>">
                    <input type="hidden" name="action" value="subtract">
                    <button class="btn btn-warning">➖ Remove Day</button>
                </form>

                <!-- Reset -->
                <form method="POST" action="adjust_days_ahead.php" onsubmit="return confirm('Reset days ahead to 0?');">
                    <input type="hidden" name="employee_id" value="<?= (int)$employeeId ?>">
                    <input type="hidden" name="action" value="reset">
                    <button class="btn btn-danger">🔄 Reset</button>
                </form>
            </div>
        </div>
    </div>

    <div class="alert alert-success mt-3">
        Days Ahead: <strong><?= (int)$emp['days_ahead'] ?></strong>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <h5 class="mb-3">Route History</h5>

            <?php if (!$routes): ?>
                <p class="text-muted">No routes logged.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($routes as $r): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><?= htmlspecialchars($r['route_name']) ?></span>
                            <span class="text-muted"><?= date('M j, Y g:i A', strtotime($r['worked_at'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>