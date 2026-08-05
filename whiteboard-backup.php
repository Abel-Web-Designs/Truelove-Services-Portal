<?php

$uploadDir = __DIR__ . '/uploads/whiteboard/';
$uploadUrl = 'uploads/whiteboard/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

/* -------------------- HANDLE UPLOAD -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {

    if ($_FILES['pdf_file']['error'] === 0) {

        $mime = mime_content_type($_FILES['pdf_file']['tmp_name']);

        if ($mime === 'application/pdf') {

            foreach (glob($uploadDir . '*.pdf') as $file) {
                unlink($file);
            }

            move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadDir . 'whiteboard.pdf');

            header("Location: whiteboard.php");
            exit;
        }
    }
}

$pdfPath = $uploadDir . 'whiteboard.pdf';
$pdfUrl  = $uploadUrl . 'whiteboard.pdf';
$uploadMode = isset($_GET['upload']);

?>
<!DOCTYPE html>
<html>
<head>
    <title>TV Whiteboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        
        html, body {
        margin:0;
        padding:0;
        height:100%;
        background:#000;
    }

    .viewer-wrapper {
        position:fixed;
        top:0;
        left:0;
        width:100vw;
        height:100vh;
        overflow:hidden;
    }

    iframe {
        width:100%;
        height:100%;
        border:none;
    }
        .upload-box { padding:50px; max-width:600px; margin:auto; }
        .top-btn {
            position:fixed;
            top:15px;
            right:15px;
            background:#007bff;
            padding:10px 15px;
            border-radius:6px;
            text-decoration:none;
            color:white;
            font-size:14px;
        }
    </style>
</head>
<body>

<?php if ($uploadMode || !file_exists($pdfPath)): ?>

<div class="upload-box">
    <h2>Upload Whiteboard PDF</h2>
    <p>To ensure best viewing practices on the TV, please ensure PDF's are in landscape view</p>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="pdf_file" accept="application/pdf" required>
        <button style="margin-top:15px;">Upload</button>
    </form>

    <?php if (file_exists($pdfPath)): ?>
        <p style="margin-top:20px;">
            <a href="whiteboard.php" style="color:#0dcaf0;">← Back to Display</a>
            <br>
            <a href="index.php" style="color:#0dcaf0;">← Back to Dashboard</a>
        </p>
    <?php endif; ?>
</div>

<?php else: ?>

<a href="whiteboard.php?upload=1" class="top-btn">Upload New PDF</a>

<?php
$viewer = "https://docs.google.com/gview?embedded=true&url=" . 
          urlencode("https://truelove-lawn-care.abelwebdesigns.com/" . $pdfUrl);
?>

<iframe src="<?= $viewer ?>"></iframe>

<script>
//setTimeout(() => location.reload(), 5 * 1000);
</script>

<?php endif; ?>

</body>
</html>