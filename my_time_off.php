<?php
require 'includes/db.php';
require 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT start_date, end_date, reason, status, created_at
    FROM time_off_requests
    WHERE employee_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container mt-4">
    <h3 class="mb-4 text-light">My Time Off Requests</h3>

    <?php if (count($requests) === 0): ?>
        <div class="alert alert-info">You haven't submitted any time off requests yet.</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($requests as $row): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-1">
                                <?= htmlspecialchars($row['start_date']) ?> → <?= htmlspecialchars($row['end_date']) ?>
                            </h5>
                            <p class="mb-1"><strong>Reason:</strong><br><?= nl2br(htmlspecialchars($row['reason'])) ?></p>
                            <p class="mb-1">
                                <strong>Status:</strong>
                                <?php if ($row['status'] === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($row['status'] === 'denied'): ?>
                                    <span class="badge bg-danger">Denied</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php endif; ?>
                            </p>
                            <small class="text-muted">Submitted: <?= htmlspecialchars(date('M j, Y', strtotime($row['created_at']))) ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
