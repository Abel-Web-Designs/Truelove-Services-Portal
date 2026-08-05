<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

if (getUserRole() !== 'mechanic' && getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Handle status update / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update status
    if (isset($_POST['request_id'], $_POST['status'])) {
        $requestId = (int)$_POST['request_id'];
        $newStatus = $_POST['status'];

        $stmt = $pdo->prepare('UPDATE maintenance_requests SET status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $requestId]);

        // OPTIONAL: When a request is marked completed, auto-create a maintenance log entry
        if ($newStatus === 'completed') {
            $stmt = $pdo->prepare("SELECT equipment, description, timestamp FROM maintenance_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);

            // Only create a log if equipment is a valid ID
            if ($r && !empty($r['equipment'])) {
                $equipmentId = (int)$r['equipment'];
                $logDate = date('Y-m-d', strtotime($r['timestamp']));

                $stmt = $pdo->prepare("
                    INSERT INTO maintenance_logs (equipment_id, description, log_date)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$equipmentId, $r['description'], $logDate]);
            }
        }
    }

    // Delete request
    if (isset($_POST['delete_request_id'])) {
        $deleteId = (int)$_POST['delete_request_id'];
        $stmt = $pdo->prepare('DELETE FROM maintenance_requests WHERE id = ?');
        $stmt->execute([$deleteId]);
    }
}

// Get Equipment list (not required anymore for display, but keeping it if you use it elsewhere)
$equipment = $pdo->query("
    SELECT id, name, type_new, serial_number, purchase_date
    FROM equipment
    ORDER BY type_new, name
")->fetchAll();

// Fetch all requests (JOIN equipment for equipment name)
$requests = $pdo->query("
    SELECT 
        r.id,
        r.equipment AS equipment_id,
        r.description,
        r.status,
        r.timestamp,
        emp.name AS employee_name,
        eq.name AS equipment_name
    FROM maintenance_requests r
    JOIN employees emp ON r.employee_id = emp.id
    LEFT JOIN equipment eq ON r.equipment = eq.id
    ORDER BY r.timestamp DESC
")->fetchAll();

// Group by status
$pending = array_filter($requests, fn($r) => $r['status'] === 'pending');
$inProgress = array_filter($requests, fn($r) => $r['status'] === 'in_progress');
$completed = array_filter($requests, fn($r) => $r['status'] === 'completed');

require 'includes/header.php';
?>

<div class="container mt-4">
    <div class="card p-4 shadow-sm">
        <h3 class="mb-4">Maintenance Requests</h3>

        <?php
        function renderRequests($title, $badgeClass, $list) {
            if (empty($list)) {
                echo "<h4 class='mt-4'>{$title}</h4><div class='alert alert-info'>No {$title} requests found.</div>";
                return;
            }

            echo "<h4 class='mt-4'>{$title}</h4>";
            echo "<div class='row'>";

            foreach ($list as $row) { ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="view_request.php?id=<?= (int)$row['id'] ?>">
                                    #<?= (int)$row['id'] ?> - <?= htmlspecialchars($row['equipment_name'] ?? 'Other / Not Set') ?>
                                </a>
                            </h5>

                            <p class="mb-1"><strong>From:</strong> <?= htmlspecialchars($row['employee_name'] ?? 'Unknown') ?></p>
                            <p class="mb-1"><strong>Description:</strong><br><?= nl2br(htmlspecialchars($row['description'])) ?></p>

                            <p class="mb-1"><strong>Status:</strong>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                                </span>
                            </p>

                            <p class="text-muted">
                                <small>Submitted: <?= date('F j, Y, g:i A', strtotime($row['timestamp'])) ?></small>
                            </p>

                            <form method="post" class="mt-2">
                                <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                <select name="status" class="form-select mb-2" onchange="this.form.submit()">
                                    <option value="pending" <?= $row['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="in_progress" <?= $row['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $row['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                            </form>

                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this request?');">
                                <input type="hidden" name="delete_request_id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger w-100">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php }

            echo "</div>";
        }

        // Render each section
        renderRequests('Pending', 'bg-warning text-dark', $pending);
        renderRequests('In Progress', 'bg-primary', $inProgress);
        renderRequests('Completed', 'bg-success', $completed);
        ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>