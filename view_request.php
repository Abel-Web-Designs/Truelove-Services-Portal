<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

// Only mechanics/admin should access
if (getUserRole() !== 'mechanic' && getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Get request ID from URL
$requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($requestId <= 0) {
    header('Location: mechanic_panel.php');
    exit();
}

// Handle add maintenance log + photos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
    $description  = trim($_POST['description'] ?? '');
    $date         = $_POST['date'] ?? date('Y-m-d');

    if ($equipment_id > 0 && $description !== '') {

        // 1) Insert log
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_logs (equipment_id, description, log_date)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$equipment_id, $description, $date]);

        $logId = (int)$pdo->lastInsertId(); // ✅ use this for photos

        // 2) Save uploaded photos (optional)
        if (!empty($_FILES['log_photos']['name'][0])) {
            $uploadDir = 'uploads/maintenance_logs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // NOTE: HEIC often fails getimagesize() unless your server supports it
            $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $maxBytes   = 8 * 1024 * 1024; // 8MB each

            foreach ($_FILES['log_photos']['tmp_name'] as $i => $tmpName) {
                if (!is_uploaded_file($tmpName)) continue;

                $origName = $_FILES['log_photos']['name'][$i] ?? 'photo';
                $size     = (int)($_FILES['log_photos']['size'][$i] ?? 0);

                if ($size <= 0 || $size > $maxBytes) continue;

                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) continue;

                // Extra safety: ensure it's an image
                if (@getimagesize($tmpName) === false) continue;

                $safeBase   = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($origName));
                $filename   = time() . '_' . $logId . '_' . $i . '_' . $safeBase;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO maintenance_log_photos (log_id, file_path)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$logId, $targetPath]);
                }
            }
        }

        // Mark the request completed
        $pdo->prepare("UPDATE maintenance_requests SET status = 'completed' WHERE id = ?")
            ->execute([$requestId]);

        header("Location: view_request.php?id=$requestId&log_success=1");
        exit;
    }

    header("Location: view_request.php?id=$requestId&log_error=1");
    exit;
}

// Get Equipment list (for the add-log form)
$equipment = $pdo->query("
    SELECT id, name, type_new, serial_number, purchase_date
    FROM equipment
    ORDER BY type_new, name
")->fetchAll();

// Fetch photos for this request
$stmt = $pdo->prepare("SELECT * FROM maintenance_photos WHERE request_id = ?");
$stmt->execute([$requestId]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the request + employee + equipment name
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        emp.name AS employee_name,
        eq.name AS equipment_name
    FROM maintenance_requests r
    JOIN employees emp ON r.employee_id = emp.id
    LEFT JOIN equipment eq ON r.equipment = eq.id
    WHERE r.id = ?
");
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    require 'includes/header.php';
    echo "<div class='container mt-5'><div class='alert alert-danger'>Maintenance request not found.</div></div>";
    include 'includes/footer.php';
    exit();
}

require 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request #<?= (int)$request['id'] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="text-light mb-0">Maintenance Request #<?= (int)$request['id'] ?></h3>
        <a href="mechanic_panel.php" class="btn btn-secondary">← Back</a>
    </div>

    <?php if (isset($_GET['log_success'])): ?>
        <div class="alert alert-success">Maintenance log entry added. Request marked completed.</div>
    <?php elseif (isset($_GET['log_error'])): ?>
        <div class="alert alert-danger">Please select equipment and enter a description.</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <p><strong>Submitted By:</strong> <?= htmlspecialchars($request['employee_name'] ?? 'Unknown') ?></p>

            <p><strong>Equipment:</strong>
                <?php if (!empty($request['equipment'])): ?>
                    <a href="equipment_view.php?id=<?= (int)$request['equipment'] ?>">
                        <?= htmlspecialchars($request['equipment_name'] ?? 'Other / Not Set') ?>
                    </a>
                <?php else: ?>
                    <?= htmlspecialchars($request['equipment_name'] ?? 'Other / Not Set') ?>
                <?php endif; ?>

                <?php if (!empty($request['equipment']) && empty($request['equipment_name'])): ?>
                    <span class="text-muted">(ID: <?= (int)$request['equipment'] ?>)</span>
                <?php endif; ?>
            </p>

            <p><strong>Status:</strong>
                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $request['status'] ?? 'pending'))) ?>
            </p>

            <p><strong>Submitted At:</strong>
                <?= date('F j, Y \a\t g:i A', strtotime($request['timestamp'])) ?>
            </p>

            <hr>

            <p><strong>Description:</strong></p>
            <p><?= nl2br(htmlspecialchars($request['description'])) ?></p>

            <?php if (!empty($photos)): ?>
                <hr>
                <p><strong>Attached Photos:</strong></p>
                <div class="row g-2">
                    <?php foreach ($photos as $photo): ?>
                        <div class="col-6 col-md-3">
                            <a href="<?= htmlspecialchars($photo['file_path']) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($photo['file_path']) ?>"
                                     class="img-fluid rounded border"
                                     alt="Maintenance Photo">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr>

            <h5 class="mb-3">Add Maintenance Entry</h5>

            <!-- ✅ Fixed: enctype + class -->
            <form id="logForm" method="POST" enctype="multipart/form-data" class="card p-3 mb-0">

                <div class="mb-3">
                    <label class="form-label">Equipment*</label>
                    <select name="equipment_id" class="form-select" required>
                        <option value="">-- Select Equipment --</option>

                        <?php foreach ($equipment as $equipmentitems): ?>
                            <?php
                            $selected = (!empty($request['equipment']) && (int)$request['equipment'] === (int)$equipmentitems['id'])
                                ? 'selected'
                                : '';
                            ?>
                            <option value="<?= (int)$equipmentitems['id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($equipmentitems['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description*</label>
                    <textarea name="description" class="form-control" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Attach Photos (optional)</label>
                    <input type="file" name="log_photos[]" class="form-control" multiple accept="image/*">
                </div>

                <button type="submit" class="btn btn-primary" id="logSubmitBtn">
                    <span class="btn-text">Add Maintenance Entry</span>
                    <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true" id="logSpinner"></span>
                </button>

            </form>

        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('logForm');
    const btn = document.getElementById('logSubmitBtn');
    const spinner = document.getElementById('logSpinner');
    const btnText = btn.querySelector('.btn-text');

    if (!form || !btn || !spinner) return;

    form.addEventListener('submit', function () {
        // Prevent double-submit
        btn.disabled = true;

        // Visual feedback
        spinner.classList.remove('d-none');
        if (btnText) btnText.textContent = 'Submitting...';
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'includes/footer.php'; ?>
</body>
</html>