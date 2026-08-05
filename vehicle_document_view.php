<?php
session_start();
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();
$role = getUserRole();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: vehicle_documents.php');
    exit;
}

require 'includes/header.php';

// Fetch vehicle
$stmt = $pdo->prepare("SELECT * FROM equipment WHERE id = ?");
$stmt->execute([$id]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Vehicle not found.</div></div>";
    require 'includes/footer.php';
    exit;
}

$success = '';
$error = '';

// -------------------- UPLOAD HANDLER (PDF + Images) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_vehicle_doc'])) {
    $docType = $_POST['doc_type'] ?? 'other';
    $title   = trim($_POST['title'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');

    if ($title === '') {
        $error = "Title is required.";
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please choose a file to upload.";
    } else {
        $file = $_FILES['file'];

        // Basic validations
        $maxBytes = 15 * 1024 * 1024; // 15MB
        if ((int)$file['size'] > $maxBytes) {
            $error = "File is too large. Max 15MB.";
        } else {
            // Allowed MIME types (PDF + common phone camera formats)
            $allowedMimeToExt = [
                'application/pdf' => 'pdf',

                'image/jpeg'      => 'jpg',
                'image/png'       => 'png',
                'image/webp'      => 'webp',

                // HEIC/HEIF (common on iPhone)
                'image/heic'      => 'heic',
                'image/heif'      => 'heif',
                'image/heif-sequence' => 'heif',
                'image/heic-sequence' => 'heic',
            ];

            // Detect MIME from tmp file (more trustworthy than client-provided)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']) ?: '';

            // Some servers may return octet-stream for HEIC; fallback to extension check
            $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            $isAllowed = isset($allowedMimeToExt[$mime]);
            if (!$isAllowed) {
                // Fallback: if MIME unknown but extension is in our allowed set
                $allowedExts = array_values($allowedMimeToExt);
                if (in_array($origExt, $allowedExts, true)) {
                    // If the extension is allowed, infer ext; keep mime as octet-stream if unknown
                    $isAllowed = true;
                }
            }

            if (!$isAllowed) {
                $error = "Only PDF or image files (JPG, PNG, WebP, HEIC) are allowed.";
            } else {
                // Choose stored extension:
                // 1) Prefer mapping from detected MIME
                // 2) Else fallback to original extension (already checked)
                $storedExt = $allowedMimeToExt[$mime] ?? $origExt;

                $uploadDir = __DIR__ . '/uploads/vehicle_docs';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Safe stored filename (keep ext)
                $storedName = 'veh_' . $id . '_' . bin2hex(random_bytes(8)) . '.' . $storedExt;
                $destPath   = $uploadDir . '/' . $storedName;

                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    $error = "Upload failed. Could not save the file.";
                } else {
                    // Save row
                    $stmt = $pdo->prepare("
                        INSERT INTO vehicle_documents
                            (equipment_id, doc_type, title, file_name, original_name, mime_type, file_size, notes, uploaded_by)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $uploadedBy = $_SESSION['user_id'] ?? null;

                    $safeDocType = in_array($docType, ['registration','insurance','other'], true) ? $docType : 'other';

                    // If finfo couldn't determine mime (octet-stream), store something reasonable
                    // based on stored ext for images; leave pdf as pdf.
                    if ($mime === 'application/octet-stream' || $mime === '') {
                        $mime = match ($storedExt) {
                            'pdf'  => 'application/pdf',
                            'jpg', 'jpeg' => 'image/jpeg',
                            'png'  => 'image/png',
                            'webp' => 'image/webp',
                            'heic' => 'image/heic',
                            'heif' => 'image/heif',
                            default => 'application/octet-stream',
                        };
                    }

                    $stmt->execute([
                        $id,
                        $safeDocType,
                        $title,
                        $storedName,
                        $file['name'],
                        $mime,
                        (int)$file['size'],
                        $notes ?: null,
                        $uploadedBy
                    ]);

                    $success = "Document uploaded.";
                }
            }
        }
    }
}

// -------------------- DELETE HANDLER (optional) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_vehicle_doc'])) {
    $docId = (int)($_POST['doc_id'] ?? 0);
    if ($docId) {
        // Get filename
        $stmt = $pdo->prepare("SELECT file_name FROM vehicle_documents WHERE id = ? AND equipment_id = ?");
        $stmt->execute([$docId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $path = __DIR__ . '/uploads/vehicle_docs/' . $row['file_name'];
            if (is_file($path)) {
                @unlink($path);
            }
            $pdo->prepare("DELETE FROM vehicle_documents WHERE id = ?")->execute([$docId]);
            $success = "Document deleted.";
        }
    }
}

// Fetch documents (ALL)
$stmt = $pdo->prepare("
    SELECT *
    FROM vehicle_documents
    WHERE equipment_id = ?
    ORDER BY uploaded_at DESC
");
$stmt->execute([$id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For preview: pick first document or selected doc
$previewId = (int)($_GET['doc'] ?? 0);
$previewDoc = null;

if ($previewId) {
    foreach ($documents as $d) {
        if ((int)$d['id'] === $previewId) {
            $previewDoc = $d;
            break;
        }
    }
} elseif (!empty($documents)) {
    $previewDoc = $documents[0];
}
?>

<div class="container mt-4 text-light" data-bs-theme="dark">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h2 class="mb-1"><?= htmlspecialchars($equipment['name']) ?></h2>
            <div class="text-muted small">
                <strong>Serial / VIN:</strong> <?= htmlspecialchars($equipment['serial_number'] ?: '-') ?>
            </div>
        </div>
        <a href="vehicle_documents.php" class="btn btn-outline-secondary btn-sm">← Back</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success mt-3"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <hr class="border-secondary">

    <!-- Upload form -->
     <?php if ($role === 'admin'): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Upload Document</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="upload_vehicle_doc" value="1">

                <div class="col-12 col-md-3">
                    <label class="form-label">Type</label>
                    <select name="doc_type" class="form-select" required>
                        <option value="registration">Registration</option>
                        <option value="insurance">Insurance</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="col-12 col-md-5">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g., 2026 Insurance Card" required>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">File (PDF or Photo)</label>
                    <input
                        type="file"
                        name="file"
                        class="form-control"
                        accept="application/pdf,image/*"
                        capture="environment"
                        required
                    >
                </div>

                <div class="col-12">
                    <label class="form-label">Notes (optional)</label>
                    <input type="text" name="notes" class="form-control" placeholder="Anything helpful...">
                </div>

                <div class="col-12 d-grid d-md-flex justify-content-md-end">
                    <button class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Left: list -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Documents</div>
                <div class="list-group list-group-flush">
                    <?php if (empty($documents)): ?>
                        <div class="p-3 text-muted">No documents uploaded yet.</div>
                    <?php else: ?>
                        <?php foreach ($documents as $d): ?>
                            <?php
                            $isActive = $previewDoc && ((int)$previewDoc['id'] === (int)$d['id']);
                            ?>
                            <a class="list-group-item list-group-item-action <?= $isActive ? 'active' : '' ?>"
                               href="?id=<?= (int)$id ?>&doc=<?= (int)$d['id'] ?>">
                                <div class="d-flex justify-content-between gap-2">
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($d['title']) ?></div>
                                        <div class="small opacity-75">
                                            <?= htmlspecialchars(ucfirst($d['doc_type'])) ?>
                                            • <?= date('M j, Y', strtotime($d['uploaded_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="small opacity-75 text-end">
                                        <?= number_format(((int)$d['file_size']) / 1024, 0) ?> KB
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: preview -->
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="fw-semibold">
                        <?= $previewDoc ? htmlspecialchars($previewDoc['title']) : 'Preview' ?>
                    </div>

                    <?php if ($previewDoc): ?>
                        <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-outline-secondary btn-sm"
                               href="vehicle_doc_open.php?doc_id=<?= (int)$previewDoc['id'] ?>"
                               target="_blank" rel="noopener">
                                Open Full Screen
                            </a>

                            <a class="btn btn-outline-secondary btn-sm"
                               href="vehicle_doc_open.php?doc_id=<?= (int)$previewDoc['id'] ?>&download=1">
                                Download
                            </a>

                            <?php if ($role === 'admin'): ?>
                            <form method="POST" class="m-0" onsubmit="return confirm('Delete this document?')">
                                <input type="hidden" name="delete_vehicle_doc" value="1">
                                <input type="hidden" name="doc_id" value="<?= (int)$previewDoc['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm">Delete</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-body p-0" style="height: 70vh;">
                    <?php if (!$previewDoc): ?>
                        <div class="p-3 text-muted">Select a document to preview.</div>
                    <?php else: ?>
                        <?php
$mime = strtolower($previewDoc['mime_type'] ?? '');
$isPdf = ($mime === 'application/pdf');
$isImage = str_starts_with($mime, 'image/');
?>

<?php if ($isImage): ?>
    <div class="p-2" style="height: 70vh; overflow:auto;">
        <img
            src="vehicle_doc_open.php?doc_id=<?= (int)$previewDoc['id'] ?>"
            alt="Document image"
            style="max-width: 100%; height: auto; display:block; margin: 0 auto;"
        >
    </div>
<?php else: ?>
    <iframe
        src="vehicle_doc_open.php?doc_id=<?= (int)$previewDoc['id'] ?>"
        style="width: 100%; height: 100%; border: 0;"
    ></iframe>
<?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require 'includes/footer.php'; ?>