<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

$message = '';
$errors = [];

// Get All Employees
$employees = $pdo->query("SELECT id, name FROM employees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get Current User Employee (created_by)
$created_by = (int)($_SESSION['user_id'] ?? 0);

// Helpers
function normalizeEmployeeIds($arr): array {
    $ids = array_map('intval', (array)$arr);
    $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));
    return $ids;
}

function ensureUploadDir(string $dir): bool {
    if (!is_dir($dir)) return @mkdir($dir, 0755, true);
    return is_writable($dir);
}

function safeExt(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    return in_array($ext, $allowed, true) ? $ext : '';
}

function newFileName(string $ext): string {
    return date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employee_ids = normalizeEmployeeIds($_POST['employee_ids'] ?? []);
    $equipment_involved = trim($_POST['equipment_involved'] ?? '');

    $incident_date = $_POST['incident_date'] ?? '';
    $incident_time = $_POST['incident_time'] ?? '';

    $incident_details = trim($_POST['incident_details'] ?? '');
    $incident_reason  = trim($_POST['incident_reason'] ?? '');

    // Validation
    if ($created_by <= 0) {
        $errors[] = "You are not properly logged in (missing user_id in session).";
    }

    if (count($employee_ids) === 0) {
        $errors[] = "Please select at least one employee.";
    }

    if ($equipment_involved === '') {
        $errors[] = "Equipment involved is required.";
    }

    $dObj = DateTime::createFromFormat('Y-m-d', $incident_date);
    if (!$dObj) {
        $errors[] = "Incident date is invalid.";
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $incident_time)) {
        $errors[] = "Incident time is invalid.";
    }

    if ($incident_details === '') {
        $errors[] = "Incident details are required.";
    }

    if ($incident_reason === '') {
        $errors[] = "Incident reason is required.";
    }

    // Handle uploads (optional)
    $photoNames = [];
    $uploadDir = __DIR__ . '/uploads/incidents';

    if (!empty($_FILES['photos']) && isset($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {

        if (!ensureUploadDir($uploadDir)) {
            $errors[] = "Upload folder is not writable: uploads/incidents";
        } else {
            $maxBytes = 8 * 1024 * 1024; // 8MB each
            $count = count($_FILES['photos']['name']);

            for ($i = 0; $i < $count; $i++) {
                $err = $_FILES['photos']['error'][$i];
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                if ($err !== UPLOAD_ERR_OK) {
                    $errors[] = "One of the photos failed to upload (error code: $err).";
                    continue;
                }

                $tmp  = $_FILES['photos']['tmp_name'][$i];
                $name = $_FILES['photos']['name'][$i];
                $size = (int)$_FILES['photos']['size'][$i];

                if ($size > $maxBytes) {
                    $errors[] = "Photo '{$name}' is larger than 8MB.";
                    continue;
                }

                $ext = safeExt($name);
                if ($ext === '') {
                    $errors[] = "Photo '{$name}' must be JPG/PNG/GIF/WEBP.";
                    continue;
                }

                // Basic image check
                $imgInfo = @getimagesize($tmp);
                if ($imgInfo === false) {
                    $errors[] = "Photo '{$name}' is not a valid image.";
                    continue;
                }

                $newName = newFileName($ext);
                $dest = $uploadDir . '/' . $newName;

                if (!@move_uploaded_file($tmp, $dest)) {
                    $errors[] = "Failed to save uploaded photo '{$name}'.";
                    continue;
                }

                $photoNames[] = $newName;
            }
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO incident_reports
                    (employee_ids_json, equipment_involved, incident_date, incident_time, incident_details, incident_reason, photos_json, created_by)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                json_encode($employee_ids, JSON_UNESCAPED_SLASHES),
                $equipment_involved,
                $incident_date,
                $incident_time . ':00',
                $incident_details,
                $incident_reason,
                !empty($photoNames) ? json_encode($photoNames, JSON_UNESCAPED_SLASHES) : null,
                $created_by
            ]);

            $message = "Incident report submitted successfully.";

            // Clear form values
            $_POST = [];
        } catch (Throwable $e) {
            $errors[] = "Database error while saving incident report.";
            // TEMP DEBUG if needed:
            // $errors[] = $e->getMessage();
        }
    }
}

require 'includes/header.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Incident Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container mt-5">
    <div class="card p-4 shadow-sm">
        <h3 class="mb-3">New Incident Report</h3>
        <p>Please include as much detail as possible in the description and attach relevant photos.</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="logForm" method="post" enctype="multipart/form-data">

            <div class="mb-3">
                <label class="form-label">Employee(s) Involved</label>
                <select name="employee_ids[]" class="form-select" multiple size="8" required>
                    <?php
                        $selected = array_map('intval', (array)($_POST['employee_ids'] ?? []));
                    ?>
                    <?php foreach ($employees as $item): ?>
                        <option value="<?= (int)$item['id'] ?>"
                            <?= in_array((int)$item['id'], $selected, true) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($item['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Hold Ctrl (Windows) / Cmd (Mac) to select multiple.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Equipment Involved</label>
                <textarea name="equipment_involved" class="form-control" rows="2" required><?= htmlspecialchars($_POST['equipment_involved'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Incident Date / Time</label>
                <div class="row g-2">
                    <div class="col-7">
                        <input type="date" name="incident_date" class="form-control"
                               value="<?= htmlspecialchars($_POST['incident_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-5">
                        <input type="time" name="incident_time" class="form-control" step="60"
                               value="<?= htmlspecialchars($_POST['incident_time'] ?? '') ?>" required>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Details of the Incident</label>
                <textarea name="incident_details" class="form-control" rows="4" required><?= htmlspecialchars($_POST['incident_details'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Reason the Incident Occurred</label>
                <textarea name="incident_reason" class="form-control" rows="3" required><?= htmlspecialchars($_POST['incident_reason'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Attach Photos (optional)</label>
                <input type="file" name="photos[]" class="form-control" multiple accept="image/*">
                <div class="form-text">Max 8MB per photo. JPG/PNG/GIF/WEBP</div>
            </div>

            <button type="submit" class="btn btn-primary" id="logSubmitBtn">
                <span class="btn-text">Submit Report</span>
                <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true" id="logSpinner"></span>
            </button>

        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('logForm');
    const btn = document.getElementById('logSubmitBtn');
    const spinner = document.getElementById('logSpinner');
    const btnText = btn ? btn.querySelector('.btn-text') : null;

    if (!form || !btn || !spinner) return;

    form.addEventListener('submit', function () {
        btn.disabled = true;
        spinner.classList.remove('d-none');
        if (btnText) btnText.textContent = 'Submitting...';
    });
});
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>