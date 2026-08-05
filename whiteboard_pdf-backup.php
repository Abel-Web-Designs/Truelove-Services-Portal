<?php
require 'includes/db.php';
require 'includes/auth.php';

/*
  Recommended for your use-case:
  - Anyone can VIEW (TV doesn't need to log in)
  - Only admin can UPLOAD
*/
$isLoggedIn = function_exists('isLoggedIn') ? isLoggedIn() : false;
$isAdmin    = $isLoggedIn && function_exists('getUserRole') && getUserRole() === 'admin';

$uploadDir = __DIR__ . '/uploads/whiteboard/';
$uploadUrl = 'uploads/whiteboard/';

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$message = "";

/* -------------------- HANDLE UPLOAD -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    if (!$isAdmin) {
        $message = "<div class='alert alert-danger'>Upload not allowed.</div>";
    } elseif ($_FILES['pdf_file']['error'] !== 0) {
        $message = "<div class='alert alert-danger'>Upload failed. Code: " . (int)$_FILES['pdf_file']['error'] . "</div>";
    } else {
        $tmp = $_FILES['pdf_file']['tmp_name'];
        $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : '';

        if ($mime !== 'application/pdf') {
            $message = "<div class='alert alert-danger'>Only PDF files are allowed.</div>";
        } else {
            // remove old record + file (keep only 1)
            $oldPath = $pdo->query("SELECT file_path FROM whiteboard_pdf ORDER BY id DESC LIMIT 1")->fetchColumn();
            if ($oldPath) {
                $oldFs = __DIR__ . '/' . ltrim($oldPath, '/');
                if (is_file($oldFs)) @unlink($oldFs);
            }
            $pdo->query("TRUNCATE TABLE whiteboard_pdf");

            $safeName = 'whiteboard_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
            $destFs   = $uploadDir . $safeName;
            $destWeb  = $uploadUrl . $safeName;

            if (!move_uploaded_file($tmp, $destFs)) {
                $message = "<div class='alert alert-danger'>Could not save file on server.</div>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO whiteboard_pdf (file_path) VALUES (?)");
                $stmt->execute([$destWeb]);
                $message = "<div class='alert alert-success'>PDF uploaded successfully.</div>";
            }
        }
    }
}

/* -------------------- GET CURRENT PDF -------------------- */
$currentPdf = $pdo->query("SELECT file_path FROM whiteboard_pdf ORDER BY id DESC LIMIT 1")->fetchColumn();

// Cache-buster so TVs don’t keep showing the old one
$pdfForJs = $currentPdf ? ($currentPdf . '?v=' . time()) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>TV PDF Board</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body { background:#111; color:#fff; }
    #pdfWrap { height: calc(100vh - 120px); overflow:auto; background:#1c1c1c; border-radius:12px; padding:16px; }
    .pdf-page { margin: 0 auto 18px auto; width: fit-content; }
    canvas { max-width: 100%; height: auto; border-radius: 8px; background: #fff; }
  </style>
</head>
<body>

<div class="container-fluid p-3">
  <div class="d-flex align-items-center gap-2 mb-3">
    <h4 class="m-0">TV PDF Board</h4>

    <div class="ms-auto d-flex gap-2">
      <button class="btn btn-outline-light btn-sm" onclick="location.reload()">Refresh</button>
      <button class="btn btn-outline-light btn-sm" onclick="toggleFullscreen()">Fullscreen</button>
    </div>
  </div>

  <?= $message ?>

  <?php if ($isAdmin): ?>
    <form method="POST" enctype="multipart/form-data" class="mb-3">
      <div class="input-group">
        <input type="file" name="pdf_file" accept="application/pdf" class="form-control" required>
        <button class="btn btn-primary">Upload PDF</button>
      </div>
      <div class="form-text text-light opacity-75">Upload a PDF from your computer. The TV will display the newest one.</div>
    </form>
  <?php endif; ?>

  <div id="pdfWrap">
    <?php if (!$currentPdf): ?>
      <div class="alert alert-info">No PDF uploaded yet.</div>
    <?php else: ?>
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="badge bg-success">Loaded</span>
        <span class="text-light opacity-75 small"><?= htmlspecialchars($currentPdf) ?></span>
      </div>

      <div id="pdfPages"></div>

      <div id="pdfError" class="alert alert-danger d-none mt-3"></div>
    <?php endif; ?>
  </div>
</div>

<!-- PDF.js (render PDFs in browsers that would otherwise download) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.js"></script>

<script>
const PDF_URL = <?= json_encode($pdfForJs) ?>;

function toggleFullscreen() {
  const el = document.documentElement;
  if (!document.fullscreenElement) el.requestFullscreen?.();
  else document.exitFullscreen?.();
}

async function renderPdf(url) {
  const pagesDiv = document.getElementById('pdfPages');
  const errBox = document.getElementById('pdfError');

  try {
    // worker
    pdfjsLib.GlobalWorkerOptions.workerSrc =
      "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.js";

    const loadingTask = pdfjsLib.getDocument({ url, withCredentials: false });
    const pdf = await loadingTask.promise;

    pagesDiv.innerHTML = "";

    // Scale tuned for TV readability; adjust if needed
    const scale = 1.6;

    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
      const page = await pdf.getPage(pageNum);
      const viewport = page.getViewport({ scale });

      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');

      canvas.width = viewport.width;
      canvas.height = viewport.height;

      const pageWrap = document.createElement('div');
      pageWrap.className = "pdf-page";
      pageWrap.appendChild(canvas);
      pagesDiv.appendChild(pageWrap);

      await page.render({ canvasContext: ctx, viewport }).promise;
    }

  } catch (e) {
    console.error(e);
    if (errBox) {
      errBox.classList.remove('d-none');
      errBox.textContent = "Could not display the PDF in this browser. " +
        "If you still see downloads, PDF.js may be blocked or the file isn't reachable.";
    }
  }
}

if (PDF_URL) renderPdf(PDF_URL);
</script>

</body>
</html>