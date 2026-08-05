<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/snow_db.php';
require 'includes/snow_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

if (getUserRole() !== 'admin') {
    header('Location: ../dashboard.php');
    exit();
}

/* -------------------- LOAD DEDUCTION TOGGLE STATE -------------------- */
try {
    $stmt = $pdo->prepare("SELECT value FROM app_settings WHERE `key` = ?");
    $stmt->execute(['snow_deduct_enabled']);
    $snowDeductEnabled = ((string)($stmt->fetchColumn() ?? '1') === '1'); // default ON
} catch (PDOException $e) {
    // If settings table doesn't exist yet, default to enabled
    $snowDeductEnabled = true;
}
/* -------------------------------------------------------------------- */

try {
    $stmt = $pdo->query("
        SELECT 
            e.id AS employee_id,
            e.name,
            sb.days_ahead
        FROM snow_balances sb
        JOIN employees e ON e.id = sb.employee_id
        WHERE e.is_active = 1
        ORDER BY e.name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='alert alert-danger m-4'>
            <strong>Database Error:</strong><br>
            " . htmlspecialchars($e->getMessage()) . "
          </div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<div class="container mt-5 text-light" data-bs-theme="dark">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h3 class="mb-0">❄ Snow Route Dashboard</h3>

        <!-- Desktop -->
        <div class="d-none d-md-flex gap-2">
            <a href="create_storm.php" class="btn btn-success">+ New Storm</a>
            <a href="log_route.php" class="btn btn-success">+ Log Route</a>
            <a href="routes.php" class="btn btn-outline-light">View All Routes</a>
            <a href="truck.php" class="btn btn-outline-light">Truck View</a>
            <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#faqModal">FAQ</button>
        </div>

        <!-- Mobile -->
        <div class="dropdown d-md-none w-100">
            <button class="btn btn-outline-light dropdown-toggle w-100" data-bs-toggle="dropdown">
                Actions
            </button>
            <ul class="dropdown-menu w-100">
                <li><a class="dropdown-item" href="create_storm.php">+ New Storm</a></li>
                <li><a class="dropdown-item" href="log_route.php">+ Log Route</a></li>
                <li><a class="dropdown-item" href="routes.php">View All Routes</a></li>
                <li><a class="dropdown-item" href="truck.php">Truck View</a></li>
                <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#faqModal">FAQ</button></li>
            </ul>
        </div>
    </div>

    <!-- -------------------- DEDUCTION TOGGLE CARD -------------------- -->
    <div class="card bg-dark text-light border-secondary mb-4" data-bs-theme="dark">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">Automatic Weekday Deduction</h5>
                    <div class="text-secondary small">
                        Turn OFF during an active storm so the cron won’t deduct days while crews are still working.
                    </div>
                </div>

                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="deductToggle" <?= $snowDeductEnabled ? 'checked' : '' ?>>
                    <label class="form-check-label ms-2" for="deductToggle" id="deductToggleLabel">
                        <?= $snowDeductEnabled ? 'Enabled' : 'Paused' ?>
                    </label>
                </div>
            </div>

            <?php if (!$snowDeductEnabled): ?>
                <div class="alert alert-warning mt-3 mb-0">
                    <strong>Paused:</strong> No deductions will occur until you turn this back on.
                </div>
            <?php endif; ?>

            <div id="deductToggleMsg" class="small mt-2"></div>
        </div>
    </div>
    <!-- -------------------------------------------------------------- -->

    <?php if (empty($employees)): ?>
        <div class="alert alert-info">No snow data yet.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($employees as $emp): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <h5 class="mb-2"><?= htmlspecialchars($emp['name']) ?></h5>

                            <p class="mb-3">
                                Days Ahead:
                                <span class="badge bg-success fs-6">
                                    <?= (int)$emp['days_ahead'] ?>
                                </span>
                            </p>

                            <a href="employee.php?id=<?= (int)$emp['employee_id'] ?>"
                               class="btn btn-outline-primary btn-sm w-100">
                                View Routes
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <hr class="my-4">

    <a href="../index.php" class="btn btn-light">← Back to Main Menu</a>

    <!-- FAQ Modal -->
    <div class="modal fade" id="faqModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalLabel">FAQ</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <font class="text-primary fw-bold">Days Ahead</font>
                    <br>
                    <p>
                        Days ahead are counted for each route. The way that days ahead are added is included below. A day ahead will be deducted on every weekday at
                        <b><font class="text-info">10:00 AM EST</font></b>
                        <ul>
                            <li>Plow Route &gt; 1 shift</li>
                            <li>Salt Route &gt; 1 shift</li>
                            <li>Combo Route &gt; 2 shifts</li>
                        </ul>
                    </p>

                    <hr>

                    <b><font class="text-primary fw-bold">Storms / Create Storm</font></b>
                    <br>
                    <p>
                        Our system allows for specific weather events, or storms, to be created. When creating a storm, you can mass add all employees for their first round/route of the weather event.
                        If additional routes needed to be added in the system, they will have to be added with the '+ Log Route' button. Future plans are to be able to add multiple employees when using
                        the '+ Log Route' button; however, <font class="text-danger-emphasis">it is not available at this time.</font>
                    </p>

                    <hr>

                    <b><font class="text-primary fw-bold">Pause / Enable Day Deductions</font></b>
                    <br>
                    <p>
                        Our system allows for deduction to be paused during an active storm. This will disable days from automatically being deducted every morning during the active storm. It needs to be
                        reenabled at the end of the storm to continue with day deductions as normal.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-dark text-white text-center py-3 mt-5">
    <small>&copy; <?= date('Y') ?> Truelove Lawn Care. All rights reserved.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.getElementById('deductToggle')?.addEventListener('change', async function () {
    const enabled = this.checked ? 1 : 0;
    const msg = document.getElementById('deductToggleMsg');
    const label = document.getElementById('deductToggleLabel');

    msg.textContent = 'Saving...';
    msg.className = 'small mt-2 text-secondary';

    try {
        const res = await fetch('ajax/toggle_deduct.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'enabled=' + encodeURIComponent(enabled)
        });

        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Save failed');

        label.textContent = enabled ? 'Enabled' : 'Paused';
        msg.textContent = enabled
            ? 'Enabled. Deductions will run as scheduled.'
            : 'Paused. No deductions will occur until re-enabled.';
        msg.className = 'small mt-2 text-success';

        // Optional: refresh so the warning banner appears/disappears
        // location.reload();

    } catch (e) {
        this.checked = !this.checked; // revert UI
        msg.textContent = 'Error: ' + e.message;
        msg.className = 'small mt-2 text-danger';
    }
});
</script>

</body>
</html>
