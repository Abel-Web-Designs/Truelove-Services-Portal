<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/attendance_db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

if (getUserRole() !== 'admin') {
    header('Location: ../dashboard.php');
    exit();
}

try {
    $stmt = $pdo->query("
    SELECT
        e.id AS employee_id,
        e.name,
        COALESCE(SUM(ap.points), 0) AS total_points
    FROM employees e
    LEFT JOIN attendance_points ap
        ON ap.employee_id = e.id
        AND ap.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    WHERE e.is_active = 1
    GROUP BY e.id, e.name
    ORDER BY total_points DESC, e.name ASC
");

    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $employeesWithPoints = [];
    $employeesWithoutPoints = [];

    foreach ($employees as $emp) {
        if ((float) $emp['total_points'] > 0) {
            $employeesWithPoints[] = $emp;
        } else {
            $employeesWithoutPoints[] = $emp;
        }
    }

} catch (PDOException $e) {
    echo "<div class='alert alert-danger m-4'>
            <strong>Database Error:</strong><br>"
        . htmlspecialchars($e->getMessage()) .
        "</div>";

    include __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<div class="container mt-5 text-light" data-bs-theme="dark">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h3 class="mb-0">Attendance Dashboard</h3>

        <!-- Desktop -->
        <div class="d-none d-md-flex gap-2">
            <a href="" class="btn btn-success"></a>
        </div>
    </div>

    <?php if (empty($employees)): ?>

        <div class="alert alert-info">No attendance data yet.</div>

    <?php else: ?>

        <?php if (!empty($employeesWithPoints)): ?>

            <h4 class="mb-3 text-danger"> Employees With Attendance Points </h4>

            <div class="row g-3">
                <?php foreach ($employeesWithPoints as $emp): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card bg-dark border-secondary shadow h-100">
                            <div class="card-body text-center text-light">

                                <h5 class="card-title mb-3">
                                    <?= htmlspecialchars($emp['name']) ?>
                                </h5>

                                <?php
                                $points = (float) $emp['total_points'];

                                if ($points >= 7.5) {
                                    $badge = 'bg-danger';
                                    $status = 'Final Warning';
                                } elseif ($points >= 5) {
                                    $badge = 'bg-warning text-dark';
                                    $status = 'Written Warning';
                                } elseif ($points >= 2.5) {
                                    $badge = 'bg-info text-dark';
                                    $status = 'Verbal Warning';
                                } else {
                                    $badge = 'bg-success';
                                    $status = 'Good Standing';
                                }
                                ?>

                                <h3 class="mb-1">
                                    <span class="badge <?= $badge ?>">
                                        <?= number_format($points, 1) ?>
                                    </span>
                                </h3>

                                <small class="text-secondary d-block mb-3">
                                    <?= $status ?>
                                </small>

                                <div class="dropdown mb-2">
                                    <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">

                                        Add Attendance
                                    </button>

                                    <ul class="dropdown-menu w-100">

                                        <li>
                                            <a class="dropdown-item" href="employee.php?id=<?= $emp['employee_id'] ?>&type=tardy">
                                                🕒 Tardy (0.5 Points)
                                            </a>
                                        </li>

                                        <li>
                                            <a class="dropdown-item" href="employee.php?id=<?= $emp['employee_id'] ?>&type=excused">
                                                ✔ Excused Absence (2 Points)
                                            </a>
                                        </li>

                                        <li>
                                            <a class="dropdown-item text-danger"
                                                href="employee.php?id=<?= $emp['employee_id'] ?>&type=unexcused">
                                                ✖ Unexcused Absence (3 Points)
                                            </a>
                                        </li>

                                    </ul>
                                </div>

                                <a href="employee.php?id=<?= $emp['employee_id'] ?>" class="btn btn-outline-light btn-sm w-100">
                                    View Employee History
                                </a>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <hr class="my-5">

        <h4 class="mb-3 text-success"> Employees In Good Standing </h4>
        <div class="row g-3">
            <?php foreach ($employeesWithoutPoints as $emp): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card bg-dark border-secondary shadow h-100">
                        <div class="card-body text-center text-light">

                            <h5 class="card-title mb-3">
                                <?= htmlspecialchars($emp['name']) ?>
                            </h5>

                            <?php
                            $points = (float) $emp['total_points'];

                            if ($points >= 7.5) {
                                $badge = 'bg-danger';
                                $status = 'Final Warning';
                            } elseif ($points >= 5) {
                                $badge = 'bg-warning text-dark';
                                $status = 'Written Warning';
                            } elseif ($points >= 2.5) {
                                $badge = 'bg-info text-dark';
                                $status = 'Verbal Warning';
                            } else {
                                $badge = 'bg-success';
                                $status = 'Good Standing';
                            }
                            ?>

                            <h3 class="mb-1">
                                <span class="badge <?= $badge ?>">
                                    <?= number_format($points, 1) ?>
                                </span>
                            </h3>

                            <small class="text-secondary d-block mb-3">
                                <?= $status ?>
                            </small>

                            <div class="dropdown mb-2">
                                <button class="btn btn-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">

                                    Add Attendance
                                </button>

                                <ul class="dropdown-menu w-100">

                                    <li>
                                        <a class="dropdown-item" href="employee.php?id=<?= $emp['employee_id'] ?>&type=tardy">
                                            🕒 Tardy (0.5 Points)
                                        </a>
                                    </li>

                                    <li>
                                        <a class="dropdown-item" href="employee.php?id=<?= $emp['employee_id'] ?>&type=excused">
                                            ✔ Excused Absence (3 Points)
                                        </a>
                                    </li>

                                    <li>
                                        <a class="dropdown-item text-danger"
                                            href="employee.php?id=<?= $emp['employee_id'] ?>&type=unexcused">
                                            ✖ Unexcused Absence (4 Points)
                                        </a>
                                    </li>

                                </ul>
                            </div>

                            <a href="employee.php?id=<?= $emp['employee_id'] ?>" class="btn btn-outline-light btn-sm w-100">
                                View Employee History
                            </a>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <hr class="my-4">

    <a href="../index.php" class="btn btn-light">← Back to Main Menu</a>
</div>

<footer class="bg-dark text-white text-center py-3 mt-5">
    <small>&copy; <?= date('Y') ?> Truelove Services. All rights reserved.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>