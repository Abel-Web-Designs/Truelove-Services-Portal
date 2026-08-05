<?php
session_start();
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

$docId = (int)($_GET['doc_id'] ?? 0);
if (!$docId) {
    http_response_code(400);
    exit('Missing doc_id');
}

$download = isset($_GET['download']) ? 1 : 0;

// Load doc row
$stmt = $pdo->prepare("SELECT * FROM vehicle_documents WHERE id = ?");
$stmt->execute([$docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    http_response_code(404);
    exit('Document not found');
}

$path = __DIR__ . '/uploads/vehicle_docs/' . $doc['file_name'];
if (!is_file($path)) {
    http_response_code(404);
    exit('File missing on server');
}

// Prefer DB mime, but verify with finfo if possible
$mime = $doc['mime_type'] ?: 'application/octet-stream';
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detected = $finfo->file($path);
if ($detected) {
    $mime = $detected;
}

// Some servers report HEIC as octet-stream; infer from extension
$ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
if ($mime === 'application/octet-stream') {
    if ($ext === 'pdf')  $mime = 'application/pdf';
    if ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
    if ($ext === 'png')  $mime = 'image/png';
    if ($ext === 'webp') $mime = 'image/webp';
    if ($ext === 'heic') $mime = 'image/heic';
    if ($ext === 'heif') $mime = 'image/heif';
}

// Security headers (optional but good)
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));

// Inline for preview, attachment for download button
$original = $doc['original_name'] ?: ('document.' . $ext);
$disposition = $download ? 'attachment' : 'inline';
header("Content-Disposition: $disposition; filename=\"" . addslashes($original) . "\"");

// Output bytes
readfile($path);
exit;
