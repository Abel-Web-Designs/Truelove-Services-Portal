<?php

require '../includes/db.php';
require '../includes/auth.php';

if(!isLoggedIn() || getUserRole() !== 'admin'){
    http_response_code(403);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$key = $data['key'] ?? '';
$value = $data['value'] ?? '';

if(!$key) exit;

$stmt = $pdo->prepare("
INSERT INTO whiteboard_data (`key`,value)
VALUES (?,?)
ON DUPLICATE KEY UPDATE value=VALUES(value)
");

$stmt->execute([$key,$value]);

// update timestamp so viewers refresh
file_put_contents('whiteboard_last_update.txt', time());

echo json_encode(['success'=>true]);