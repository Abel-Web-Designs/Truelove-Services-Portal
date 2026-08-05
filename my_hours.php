<?php
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/header.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$employeeId = (int)($_SESSION['user_id'] ?? 0);

if ($employeeId <= 0) {
    die('Invalid employee session.');
}

/*
|--------------------------------------------------------------------------
| Employee Info
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT id, name
    FROM employees
    WHERE id = ?
");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die('Employee not found.');
}

/*
|--------------------------------------------------------------------------
| Time Logs
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT timestamp, clock_type
    FROM time_logs
    WHERE employee_id = ?
    ORDER BY timestamp ASC
");
$stmt->execute([$employeeId]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Date Ranges
|--------------------------------------------------------------------------
*/
$currentYear = date('Y');

$today = new DateTime();

$payPeriodStart = clone $today;
$dayOfWeek = $payPeriodStart->format('w'); // 0 = Sunday

if ($dayOfWeek > 0) {
    $payPeriodStart->modify("-{$dayOfWeek} days");
}

$payPeriodStart->setTime(0,0,0);

/*
|--------------------------------------------------------------------------
| Stats
|--------------------------------------------------------------------------
*/
$lifetimeSeconds = 0;
$ytdSeconds = 0;
$payPeriodSeconds = 0;

$currentStatus = 'Clocked Out';
$openIn = null;

$recentShifts = [];

foreach ($logs as $log) {

    $type = strtolower(trim($log['clock_type']));
    $timestamp = new DateTime($log['timestamp']);

    if ($type === 'in') {

        $openIn = $timestamp;
        $currentStatus = 'Clocked In';

    } elseif ($type === 'out' && $openIn) {

        $seconds = $timestamp->getTimestamp() - $openIn->getTimestamp();

        if ($seconds > 0) {

            $lifetimeSeconds += $seconds;

            if ($openIn->format('Y') == $currentYear) {
                $ytdSeconds += $seconds;
            }

            if ($openIn >= $payPeriodStart) {
                $payPeriodSeconds += $seconds;
            }

            $recentShifts[] = [
                'date' => $openIn->format('Y-m-d'),
                'clock_in' => $openIn->format('g:i A'),
                'clock_out' => $timestamp->format('g:i A'),
                'hours' => round($seconds / 3600, 2)
            ];
        }

        $openIn = null;
        $currentStatus = 'Clocked Out';
    }
}

$recentShifts = array_reverse($recentShifts);
$recentShifts = array_slice($recentShifts, 0, 10);

function formatHours($seconds)
{
    return number_format($seconds / 3600, 2);
}

$lifetimeHours = formatHours($lifetimeSeconds);
$ytdHours = formatHours($ytdSeconds);
$payPeriodHours = formatHours($payPeriodSeconds);

$weeklyGoal = 50;
$progress = min(($payPeriodHours / $weeklyGoal) * 100, 100);
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-light mb-1">
                Welcome, <?= htmlspecialchars($employee['name']) ?>
            </h2>
            <p class="text-light mb-0">
                Employee Dashboard
            </p>
        </div>

        <span class="badge <?= $currentStatus === 'Clocked In' ? 'bg-success' : 'bg-secondary' ?> fs-6">
            <?= $currentStatus ?>
        </span>
    </div>

    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card bg-primary border-0 shadow">
                <div class="card-body text-center text-white">
                    <div class="small">Lifetime Hours</div>
                    <h2 class="mb-0"><?= $lifetimeHours ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-success border-0 shadow">
                <div class="card-body text-center text-white">
                    <div class="small">Year To Date</div>
                    <h2 class="mb-0"><?= $ytdHours ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-warning border-0 shadow">
                <div class="card-body text-center text-dark">
                    <div class="small">Current Pay Period</div>
                    <h2 class="mb-0"><?= $payPeriodHours ?></h2>
                </div>
            </div>
        </div>

    </div>

    <div class="card shadow border-0 mb-4">
        <div class="card-body">

            <div class="d-flex justify-content-between mb-2">
                <strong>This Week's Progress</strong>
                <span><?= $payPeriodHours ?> / 50 hrs</span>
            </div>

            <div class="progress" style="height:30px;">
                <div
                    class="progress-bar"
                    role="progressbar"
                    style="width: <?= $progress ?>%;">
                    <?= round($progress) ?>%
                </div>
            </div>

        </div>
    </div>

    <div class="card shadow border-0">

        <div class="card-header">
            <h5 class="mb-0">Recent Shifts</h5>
        </div>

        <div class="table-responsive">

            <table class="table table-striped table-hover mb-0">

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Hours</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($recentShifts)): ?>

                    <tr>
                        <td colspan="4" class="text-center">
                            No shifts found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($recentShifts as $shift): ?>

                    <tr>
                        <td><?= date('M j, Y', strtotime($shift['date'])) ?></td>
                        <td><?= $shift['clock_in'] ?></td>
                        <td><?= $shift['clock_out'] ?></td>
                        <td><?= $shift['hours'] ?></td>
                    </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require 'includes/footer.php'; ?>
