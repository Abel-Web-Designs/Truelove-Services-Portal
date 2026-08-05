<?php
session_start();
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: equipment_list.php');
    exit;
}

// Handle add maintenance log + photos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment_id = $id; // ✅ tie log to this equipment page
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');

    if ($description !== '') {

        // 1) Insert log
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_logs (equipment_id, description, log_date)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$equipment_id, $description, $date]);

        $logId = (int) $pdo->lastInsertId();

        // 2) Save uploaded photos (optional)
        if (!empty($_FILES['log_photos']['name'][0])) {
            $uploadDir = 'uploads/maintenance_logs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp']; // keep simple for getimagesize compatibility
            $maxBytes = 8 * 1024 * 1024; // 8MB each

            foreach ($_FILES['log_photos']['tmp_name'] as $i => $tmpName) {
                if (!is_uploaded_file($tmpName))
                    continue;

                $origName = $_FILES['log_photos']['name'][$i] ?? 'photo';
                $size = (int) ($_FILES['log_photos']['size'][$i] ?? 0);

                if ($size <= 0 || $size > $maxBytes)
                    continue;

                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true))
                    continue;

                // Verify it's an image
                if (@getimagesize($tmpName) === false)
                    continue;

                $safeBase = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($origName));
                $filename = time() . '_' . $logId . '_' . $i . '_' . $safeBase;
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

        header("Location: equipment_view.php?id=$id&success=1");
        exit;
    }

    header("Location: equipment_view.php?id=$id&error=1");
    exit;
}

require 'includes/header.php';

// Fetch equipment details
$stmt = $pdo->prepare("SELECT * FROM equipment WHERE id = ?");
$stmt->execute([$id]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Equipment not found.</div></div>";
    include 'includes/footer.php';
    exit;
}

// Decode VIN information using NHTSA vPIC API
$vinInfo = null;
$vinError = null;

$vin = strtoupper(trim($equipment['serial_number'] ?? ''));
$vinClean = preg_replace('/[^A-HJ-NPR-Z0-9]/', '', $vin); // VIN excludes I, O, Q

if (strlen($vinClean) === 17) {
    $apiUrl = "https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValuesExtended/" . urlencode($vinClean) . "?format=json";

    $json = @file_get_contents($apiUrl);

    if ($json !== false) {
        $data = json_decode($json, true);
        $result = $data['Results'][0] ?? null;

        if ($result) {
            $vinInfo = [
                'year' => $result['ModelYear'] ?? '',
                'make' => $result['Make'] ?? '',
                'model' => $result['Model'] ?? '',
                'trim' => $result['Trim'] ?? '',
                'body_class' => $result['BodyClass'] ?? '',
                'vehicle_type' => $result['VehicleType'] ?? '',
                'engine' => trim(($result['EngineCylinders'] ?? '') . ' Cyl ' . ($result['DisplacementL'] ?? '') . 'L'),
                'fuel' => $result['FuelTypePrimary'] ?? '',
                'drive_type' => $result['DriveType'] ?? '',
                'plant' => trim(($result['PlantCity'] ?? '') . ', ' . ($result['PlantState'] ?? '') . ' ' . ($result['PlantCountry'] ?? '')),
            ];
        }
    } else {
        $vinError = 'VIN decoder unavailable.';
    }
}

// Get maintenance logs
$stmt = $pdo->prepare("SELECT * FROM maintenance_logs WHERE equipment_id = ? ORDER BY log_date DESC, id DESC");
$stmt->execute([$id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all photos for these logs and group by log_id
$photosByLogId = [];
if (!empty($logs)) {
    $logIds = array_column($logs, 'id');
    $placeholders = implode(',', array_fill(0, count($logIds), '?'));

    $stmt = $pdo->prepare("
        SELECT log_id, file_path
        FROM maintenance_log_photos
        WHERE log_id IN ($placeholders)
        ORDER BY id ASC
    ");
    $stmt->execute($logIds);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $photosByLogId[(int) $p['log_id']][] = $p['file_path'];
    }
}
?>

<div class="container mt-4">
    <h1 class="text-light mb-1"><?= htmlspecialchars($equipment['name']) ?></h1>

    <?php if ($vinInfo): ?>
        <div class="card bg-dark text-light border-secondary shadow-sm mb-3">
            <div class="card-body">
                <h5 class="card-title mb-2">
                    <?= htmlspecialchars(trim($vinInfo['year'] . ' ' . $vinInfo['make'] . ' ' . $vinInfo['model'] . ' ' . $vinInfo['trim'])) ?>
                </h5>

                <div class="row g-2 small">
                    <div class="col-md-4"><strong>Body:</strong> <?= htmlspecialchars($vinInfo['body_class'] ?: '-') ?>
                    </div>
                    <div class="col-md-4"><strong>Type:</strong> <?= htmlspecialchars($vinInfo['vehicle_type'] ?: '-') ?>
                    </div>
                    <div class="col-md-4"><strong>Engine:</strong> <?= htmlspecialchars(trim($vinInfo['engine']) ?: '-') ?>
                    </div>
                    <div class="col-md-4"><strong>Fuel:</strong> <?= htmlspecialchars($vinInfo['fuel'] ?: '-') ?></div>
                    <div class="col-md-4"><strong>Drive:</strong> <?= htmlspecialchars($vinInfo['drive_type'] ?: '-') ?>
                    </div>
                    <div class="col-md-4"><strong>Plant:</strong>
                        <?= htmlspecialchars(trim($vinInfo['plant'], ', ') ?: '-') ?></div>
                </div>
            </div>
        </div>
    <?php elseif (strlen($vinClean) > 0 && strlen($vinClean) !== 17): ?>
        <div class="alert alert-warning">
            VIN information could not be decoded because this VIN/serial number is not 17 characters.
        </div>
    <?php elseif ($vinError): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($vinError) ?></div>
    <?php endif; ?>

    <p class="text-light"><?= htmlspecialchars($equipment['type_new']) ?></p>

    <ul class="list-group mb-4">
        <li class="list-group-item"><strong>Serial / VIN:</strong>
            <?= htmlspecialchars($equipment['serial_number'] ?: '-') ?></li>
        <li class="list-group-item"><strong>Purchase Date:</strong>
            <?= $equipment['purchase_date'] ? date('m-d-Y', strtotime($equipment['purchase_date'])) : '-' ?>
        </li>
    </ul>

    <a href="equipment_list.php" class="btn btn-secondary mb-3">← Back to Equipment</a>

    <h3 class="text-light">Maintenance Log</h3>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Maintenance log added successfully!</div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-danger">Please enter a description.</div>
    <?php endif; ?>

    <form id="logForm" method="POST" enctype="multipart/form-data" class="card p-3 mb-3">
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
            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"
                id="logSpinner"></span>
        </button>

    </form>

    <?php if (!empty($logs)): ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 140px;">Date</th>
                        <th>Description</th>
                        <th style="width: 320px;">Photos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php $logPhotos = $photosByLogId[(int) $log['id']] ?? []; ?>
                        <tr>
                            <td><?= $log['log_date'] ? htmlspecialchars(date('m-d-Y', strtotime($log['log_date']))) : '-' ?>
                            </td>
                            <td><?= nl2br(htmlspecialchars($log['description'])) ?></td>
                            <td>
                                <?php if (!empty($logPhotos)): ?>
                                    <div class="row g-2">
                                        <?php foreach ($logPhotos as $path): ?>
                                            <div class="col-6">
                                                <a href="<?= htmlspecialchars($path) ?>" target="_blank">
                                                    <img src="<?= htmlspecialchars($path) ?>" class="img-fluid rounded border"
                                                        alt="Log Photo">
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">No photos</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No maintenance entries found.</div>
    <?php endif; ?>
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

<?php include 'includes/footer.php'; ?>