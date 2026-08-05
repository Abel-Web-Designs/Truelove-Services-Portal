<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/attendance_db.php';
require_once __DIR__ . '/../includes/auth.php';

if (getUserRole() !== 'admin') {
    header('Location: ../dashboard.php');
    exit();
}

$employeeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($employeeId <= 0) {
    die("Invalid employee.");
}

/*
|--------------------------------------------------------------------------
| Load Employee
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name
    FROM employees
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$employeeId]);

$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee not found.");
}

/*
|--------------------------------------------------------------------------
| Delete Attendance Record
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {

    $deleteId = (int) $_GET['delete'];

    $stmt = $pdo->prepare("
        DELETE FROM attendance_points
        WHERE id = ?
        AND employee_id = ?
    ");

    $stmt->execute([$deleteId, $employeeId]);

    header("Location: employee.php?id={$employeeId}");
    exit();
}

/*
|--------------------------------------------------------------------------
| Save Attendance
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $attendanceDate = $_POST['attendance_date'];
    $type = $_POST['type'];
    $notes = trim($_POST['notes']);

    switch ($type) {

        case 'tardy':
            $points = 0.5;
            break;

        case 'absent_excused':
            $points = 3;
            break;

        case 'absent_unexcused':
            $points = 4;
            break;

        default:
            $points = 0;
    }

    $stmt = $pdo->prepare("
        INSERT INTO attendance_points
        (
            employee_id,
            attendance_date,
            type,
            points,
            notes,
            created_by
        )
        VALUES
        (
            ?,?,?,?,?,?
        )
    ");

    $stmt->execute([
        $employeeId,
        $attendanceDate,
        $type,
        $points,
        $notes,
        $_SESSION['employee_id'] ?? null
    ]);

    header("Location: employee.php?id={$employeeId}");
    exit();
}

/*
|--------------------------------------------------------------------------
| Employee Totals
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS incidents,
        COALESCE(SUM(points),0) AS total_points,
        SUM(type='tardy') AS tardies,
        SUM(type='absent_excused') AS excused,
        SUM(type='absent_unexcused') AS unexcused
    FROM attendance_points
    WHERE employee_id = ?
      AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");

$stmt->execute([$employeeId]);

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$totalPoints = (float) $stats['total_points'];

if ($totalPoints >= 7.5) {
    $badge = "danger";
    $status = "Final Warning";
} elseif ($totalPoints >= 5) {
    $badge = "warning text-dark";
    $status = "Written Warning";
} elseif ($totalPoints >= 2.5) {
    $badge = "info text-dark";
    $status = "Verbal Warning";
} else {
    $badge = "success";
    $status = "Good Standing";
}

/*
|--------------------------------------------------------------------------
| Attendance History
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT *

FROM attendance_points

WHERE employee_id = ?

ORDER BY attendance_date DESC,
id DESC
");

$stmt->execute([$employeeId]);

$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedType = $_GET['type'] ?? 'tardy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4 text-light" data-bs-theme="dark">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                <?= htmlspecialchars($employee['name']) ?>
            </h2>

            <h5>

                Current Points

                <span class="badge bg-<?= $badge ?>">
                    <?= number_format($totalPoints, 1) ?>
                </span>

            </h5>

            <small class="text-secondary">
                <?= $status ?>
            </small>

        </div>

        <a href="index.php" class="btn btn-outline-light">
            ← Dashboard
        </a>

    </div>
    <div class="row">

        <!-- Employee Summary -->
        <div class="col-lg-4 mb-4">

            <div class="card bg-dark border-secondary shadow">

                <div class="card-header">
                    Employee Summary
                </div>

                <div class="card-body text-center">

                    <h1 class="display-4 fw-bold">
                        <?= number_format($totalPoints, 1) ?>
                    </h1>

                    <p class="mb-3">
                        Total Attendance Points
                    </p>

                    <span class="badge bg-<?= $badge ?> fs-6 px-3 py-2">
                        <?= $status ?>
                    </span>

                    <hr>

                    <div class="row text-center">

                        <div class="col-4">
                            <h4>
                                <?= (int) $stats['tardies'] ?>
                            </h4>
                            <small class="text-secondary">
                                Tardies
                            </small>
                        </div>

                        <div class="col-4">
                            <h4>
                                <?= (int) $stats['excused'] ?>
                            </h4>
                            <small class="text-secondary">
                                Excused
                            </small>
                        </div>

                        <div class="col-4">
                            <h4>
                                <?= (int) $stats['unexcused'] ?>
                            </h4>
                            <small class="text-secondary">
                                Unexcused
                            </small>
                        </div>

                    </div>

                    <hr>

                    <h5>
                        Total Incidents
                    </h5>

                    <h2>
                        <?= (int) $stats['incidents'] ?>
                    </h2>

                </div>

            </div>

        </div>

        <!-- Add Attendance -->
        <div class="col-lg-8 mb-4">

            <div class="card bg-dark border-secondary shadow">

                <div class="card-header">
                    Add Attendance Incident
                </div>

                <div class="card-body">

                    <form method="post">

                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Date
                                </label>

                                <input type="date" name="attendance_date" class="form-control"
                                    value="<?= date('Y-m-d') ?>" required>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Type
                                </label>

                                <select class="form-select" name="type">

                                    <option value="tardy" <?= $selectedType == 'tardy' ? 'selected' : '' ?>>
                                        Tardy (0.5 Points)
                                    </option>

                                    <option value="absent_excused" <?= $selectedType == 'excused' ? 'selected' : '' ?>>
                                        Excused Absence (3 Points)
                                    </option>

                                    <option value="absent_unexcused" <?= $selectedType == 'unexcused' ? 'selected' : '' ?>>
                                        Unexcused Absence (4 Points)
                                    </option>

                                </select>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Notes
                                </label>

                                <input type="text" class="form-control" name="notes" placeholder="Optional notes">

                            </div>

                        </div>

                        <div class="d-grid">

                            <button class="btn btn-success btn-lg">

                                Save Attendance Record

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

    <div class="card bg-dark border-secondary shadow">

        <div class="card-header d-flex justify-content-between align-items-center">

            <span>
                Attendance History
            </span>

            <span class="badge bg-primary">
                <?= count($history) ?> Records
            </span>

        </div>

        <div class="table-responsive">

            <table class="table table-dark table-hover align-middle mb-0">

                <thead>

                    <tr>

                        <th width="120">
                            Date
                        </th>

                        <th>
                            Type
                        </th>

                        <th width="100">
                            Points
                        </th>

                        <th>
                            Notes
                        </th>

                        <th width="120">

                        </th>

                    </tr>

                </thead>

                <tbody>
                    <?php if (empty($history)): ?>

                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <span class="text-secondary">
                                    No attendance records found.
                                </span>
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($history as $row): ?>

                            <?php

                            switch ($row['type']) {

                                case 'tardy':
                                    $typeText = 'Tardy';
                                    $typeBadge = 'warning text-dark';
                                    break;

                                case 'absent_excused':
                                    $typeText = 'Excused Absence';
                                    $typeBadge = 'info text-dark';
                                    break;

                                case 'absent_unexcused':
                                    $typeText = 'Unexcused Absence';
                                    $typeBadge = 'danger';
                                    break;

                                default:
                                    $typeText = ucfirst($row['type']);
                                    $typeBadge = 'secondary';
                            }

                            ?>

                            <tr>

                                <td>
                                    <?= date('M j, Y', strtotime($row['attendance_date'])) ?>
                                </td>

                                <td>

                                    <span class="badge bg-<?= $typeBadge ?>">
                                        <?= $typeText ?>
                                    </span>

                                </td>

                                <td>

                                    <strong>
                                        <?= number_format($row['points'], 1) ?>
                                    </strong>

                                </td>

                                <td>

                                    <?= !empty($row['notes'])
                                        ? nl2br(htmlspecialchars($row['notes']))
                                        : '<span class="text-secondary">No notes</span>'; ?>

                                </td>

                                <td>

                                    <a href="employee.php?id=<?= $employeeId ?>&delete=<?= $row['id'] ?>"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete this attendance record?');">

                                        Delete

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <div class="mt-4 text-end">

        <a href="index.php" class="btn btn-outline-light">
            ← Back to Dashboard
        </a>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>