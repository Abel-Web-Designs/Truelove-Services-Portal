<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require __DIR__ . '/../includes/snow_db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : -1;
if (!in_array($enabled, [0, 1], true)) {
    echo json_encode(['success' => false, 'error' => 'Bad value']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO app_settings (`key`, `value`)
    VALUES ('snow_deduct_enabled', ?)
    ON DUPLICATE KEY UPDATE value = VALUES(value)
");
$stmt->execute([(string)$enabled]);

echo json_encode(['success' => true, 'enabled' => $enabled]);
