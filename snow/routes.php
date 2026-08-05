<?php
require 'includes/snow_db.php';
require 'includes/snow_functions.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';

// Fetch all routes with employee names
$routes = $pdo->query("
    SELECT sr.id, sr.route_name, sr.worked_at, e.id AS employee_id, e.name AS employee_name
    FROM snow_routes sr
    JOIN employees e ON e.id = sr.employee_id
    ORDER BY sr.worked_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5 text-light" data-bs-theme="dark">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>❄ All Snow Routes</h3>

        <div class="d-grid gap-2 d-md-flex">
            <a href="log_route.php" class="btn btn-success">+ Log Route</a>
            <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">

            <?php if (empty($routes)): ?>
                <p class="text-muted">No routes logged yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Date / Time</th>
                                <th>Employee</th>
                                <th>Route</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($routes as $r): ?>
                                <tr>
                                    <td><?= date('M j, Y g:i A', strtotime($r['worked_at'])) ?></td>
                                    <td>
                                        <a href="employee.php?id=<?= (int)$r['employee_id'] ?>"
                                           class="link-light text-decoration-none">
                                            <?= htmlspecialchars($r['employee_name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?= htmlspecialchars($r['route_name']) ?>
                                        </span>
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

<?php include __DIR__ . '/includes/footer.php'; ?>