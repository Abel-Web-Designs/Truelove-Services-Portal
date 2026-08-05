<?php
require 'includes/header.php';
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars($_POST['name']);
    $date = htmlspecialchars($_POST['date']);
    $description = htmlspecialchars($_POST['description']);
    $attachments = $_FILES['screenshots'];

    $to = "support@truelove-lawn-care.abelwebdesigns.com";
    $subject = "New Technical Support Request";
    $body = "Name: $name\nDate of Issue: $date\n\nDescription:\n$description";
    $headers = "From: no-reply@truelove-lawn-care.abelwebdesigns.com";

    // Handle attachments
    $boundary = md5(time());
    $headers .= "\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$boundary\"";

    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $body . "\r\n";

    // Attach files
    for ($i = 0; $i < count($attachments['name']); $i++) {
        if ($attachments['error'][$i] === UPLOAD_ERR_OK) {
            $fileTmpPath = $attachments['tmp_name'][$i];
            $fileName = basename($attachments['name'][$i]);
            $fileType = mime_content_type($fileTmpPath);
            $fileContent = chunk_split(base64_encode(file_get_contents($fileTmpPath)));

            $message .= "--$boundary\r\n";
            $message .= "Content-Type: $fileType; name=\"$fileName\"\r\n";
            $message .= "Content-Disposition: attachment; filename=\"$fileName\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= $fileContent . "\r\n";
        }
    }

    $message .= "--$boundary--";

    if (mail($to, $subject, $message, $headers)) {
        $status = "✅ Support request submitted successfully.";
    } else {
        $status = "❌ Failed to send support request.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Technical Support</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card p-4">
        <h3 class="mb-3">📋 Submit a Technical Support Request</h3>
        <p>Please note this is support for the employee portal app <b>only!</b></p>
        <?php if (isset($status)): ?>
            <div class="alert alert-info"><?= $status ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Your Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Date of Issue</label>
                <input type="date" name="date" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Describe the Issue</label>
                <textarea name="description" class="form-control" rows="4" required></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Attach Screenshot(s)</label>
                <input type="file" name="screenshots[]" class="form-control" accept="image/*" multiple>
                <small class="text-muted">You can upload multiple files.</small>
            </div>

            <button type="submit" class="btn btn-primary">Submit Ticket</button>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
