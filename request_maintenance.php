<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

$message = '';
$errors = [];
// Textbelt notification settings
$TEXTBELT_KEY = '089fcbbf8e498369811562800ba94222306a5f1fWQuAT5KRh6gNW5Y7jbaRjZCVH';
$TEXTBELT_TO = '4632695248';
// Get Equipment list
$equipmentList = $pdo->query("
    SELECT id, name, type_new, serial_number, purchase_date
    FROM equipment
    ORDER BY type_new, name
")->fetchAll(PDO::FETCH_ASSOC);

$employee_id = (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read inputs safely
    $equipment_raw = $_POST['equipment_id'] ?? '';
    $description = trim($_POST['description'] ?? '');

    // Convert equipment to INT or NULL
    $equipment_id = null;
    if ($equipment_raw !== '' && $equipment_raw !== 'NULL') {
        $equipment_id = (int) $equipment_raw;
        if ($equipment_id <= 0)
            $equipment_id = null;
    }

    if ($description === '') {
        $errors[] = "Description is required.";
    }

    // Insert maintenance request first (if valid)
    if (!$errors) {
        date_default_timezone_set('America/Indiana/Indianapolis');
        $now = date('Y-m-d H:i:s');

        // NOTE: equipment can be NULL; this works if your column allows NULL
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_requests (employee_id, equipment, description, timestamp)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$employee_id, $equipment_id, $description, $now]);

        // ✅ critical: get the inserted request id
        $requestId = (int) $pdo->lastInsertId();

        // Handle uploaded photos
        if (!empty($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {

            $uploadDir = 'uploads/maintenance/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $maxBytes = 8 * 1024 * 1024; // 8MB each (adjust if you want)

            foreach ($_FILES['photos']['tmp_name'] as $i => $tmpName) {
                $error = $_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE;

                // Skip empty slots
                if ($error === UPLOAD_ERR_NO_FILE)
                    continue;

                // If upload error, skip and record it
                if ($error !== UPLOAD_ERR_OK) {
                    $errors[] = "A photo failed to upload (error code: {$error}).";
                    continue;
                }

                if (!is_uploaded_file($tmpName)) {
                    $errors[] = "A photo upload was not valid.";
                    continue;
                }

                $origName = $_FILES['photos']['name'][$i] ?? 'photo';
                $size = (int) ($_FILES['photos']['size'][$i] ?? 0);

                if ($size <= 0 || $size > $maxBytes) {
                    $errors[] = "One of the photos is too large (max 8MB each).";
                    continue;
                }

                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) {
                    $errors[] = "One of the photos has an unsupported file type.";
                    continue;
                }

                // Ensure it's really an image
                if (@getimagesize($tmpName) === false) {
                    $errors[] = "One of the uploaded files was not a valid image.";
                    continue;
                }

                $safeBase = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($origName));

                // Unique filename per file (avoids collisions)
                $unique = bin2hex(random_bytes(8));
                $targetPath = $uploadDir . $requestId . '_' . $unique . '_' . $safeBase;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO maintenance_photos (request_id, file_path)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$requestId, $targetPath]);
                } else {
                    $errors[] = "Failed to move an uploaded photo.";
                }
            }
        }

        if (!$errors) {
            $message = "Maintenance request submitted successfully.";
        }
    }

    if (!$errors) {

        /* ================= EMAIL NOTIFICATION ================= */

        // config
        $to = "maintenance@trueloveservices.com"; // <-- CHANGE THIS
        $from = "noreply@trueloveservices.abelwebdesigns.com";

        // get employee name
        $empStmt = $pdo->prepare("SELECT name FROM employees WHERE id=?");
        $empStmt->execute([$employee_id]);
        $empName = $empStmt->fetchColumn() ?: "Unknown Employee";

        // get equipment name
        $equipName = "N/A";
        if ($equipment_id) {
            $eq = $pdo->prepare("SELECT name FROM equipment WHERE id=?");
            $eq->execute([$equipment_id]);
            $equipName = $eq->fetchColumn() ?: "Unknown Equipment";
        }

        // subject
        $subject = "New Maintenance Request #{$requestId}";

        // link to admin page (adjust URL if needed)
        $link = "https://truelove-lawn-care.abelwebdesigns.com/view_request.php?id={$requestId}";

        // body
        $body = "
New Maintenance Request Submitted

Request ID: {$requestId}
Employee: {$empName}
Equipment: {$equipName}
Date: {$now}

Description:
{$description}

View Request:
{$link}
";

        // headers
        $headers = "From: {$from}\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        // send
        @mail($to, $subject, $body, $headers);

        // send text notification to predefined number
        $smsMessage = "New Maintenance Request #{$requestId}\n"
            . "Employee: {$empName}\n"
            . "Equipment: {$equipName}\n"
            . "Date: {$now}\n"
            . "Description: {$description}\n"
            . "Link: {$link}";

        $payload = http_build_query([
            'phone' => $TEXTBELT_TO,
            'message' => $smsMessage,
            'key' => $TEXTBELT_KEY
        ]);

        @file_get_contents(
            'https://textbelt.com/text',
            false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $payload,
                    'timeout' => 10
                ]
            ])
        );

        /* ======================================================= */

        $message = "Maintenance request submitted successfully. Notification sent.";
    }
}

/* -------------------- TEXTBELT HELPERS -------------------- */
function getTextbeltQuota($key)
{
    $json = @file_get_contents("https://textbelt.com/quota/" . urlencode($key));
    if (!$json)
        return null;
    $data = json_decode($json, true);
    return $data['quotaRemaining'] ?? null;
}

$quotaRemaining = getTextbeltQuota($TEXTBELT_KEY);

require 'includes/header.php';
?>

<!DOCTYPE html>
<html>

<head>
    <title>Submit Maintenance Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="card p-4 shadow-sm">
            <h3 class="mb-3">Submit Maintenance Request</h3>
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
                    <label class="form-label">Equipment</label>
                    <select name="equipment_id" class="form-select" aria-label="Equipment" required>
                        <option value="">-- Select Equipment --</option>
                        <?php foreach ($equipmentList as $item): ?>
                            <option value="<?= (int) $item['id'] ?>"><?= htmlspecialchars($item['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description of Issue</label>
                    <textarea name="description" class="form-control" rows="4" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Attach Photos (optional)</label>
                    <input type="file" name="photos[]" class="form-control" multiple accept="image/*">
                    <div class="form-text">Max 8MB per photo. JPG/PNG/GIF/WEBP.</div>
                </div>

                <button type="submit" class="btn btn-primary" id="logSubmitBtn">
                    <span class="btn-text">Submit Request</span>
                    <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"
                        id="logSpinner"></span>
                </button>

            </form>
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
    <?php include 'includes/footer.php'; ?>
</body>

</html>