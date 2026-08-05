<?php
require 'includes/snow_db.php';
require 'includes/snow_functions.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo "<div class='alert alert-danger'>Storm ID not provided.</div>";
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Get storm info
$stmt = $pdo->prepare("
    SELECT storm_name, storm_date 
    FROM snow_storms 
    WHERE id = ?
");
$stmt->execute([$id]);
$storm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$storm) {
    echo "<div class='alert alert-danger'>Storm not found.</div>";
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Get routes for this storm, joined with employees
$stmt = $pdo->prepare("
    SELECT sr.route_name, sr.route_type, sr.worked_at, e.id AS employee_id, e.name AS employee_name
    FROM snow_routes sr
    JOIN employees e ON e.id = sr.employee_id
    WHERE sr.storm_id = ?
    ORDER BY sr.worked_at
");
$stmt->execute([$id]);
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5 text-light" data-bs-theme="dark">

    <h3><?= htmlspecialchars($storm['storm_name']) ?></h3>
    <p class="text-muted"><?= htmlspecialchars($storm['storm_date']) ?></p>

    <div class="card">
        <div class="card-body">
            <?php if (empty($routes)): ?>
                <p class="text-muted">No routes logged for this storm.</p>
            <?php else: ?>
                <?php foreach ($routes as $r): ?>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <div>
                            <a href="employee.php?id=<?= (int)$r['employee_id'] ?>" class="text-light text-decoration-none">
                                <strong><?= htmlspecialchars($r['employee_name']) ?></strong>
                            </a>
                            — <?= htmlspecialchars($r['route_name']) ?>
                            <span class="badge bg-<?= $r['route_type'] === 'plow' ? 'info' : 'warning' ?>">
                                <?= strtoupper($r['route_type']) ?>
                            </span>
                        </div>
                        <small><?= date('g:i A', strtotime($r['worked_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>