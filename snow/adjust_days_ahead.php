<?php
require __DIR__ . '/includes/snow_db.php';
require_once __DIR__ . '/../includes/auth.php';

// Admin only
if (!isLoggedIn() || getUserRole() !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request');
}

$employeeId = intval($_POST['employee_id'] ?? 0);
$action     = $_POST['action'] ?? '';

if (!$employeeId || !in_array($action, ['add', 'subtract', 'reset'])) {
    exit('Invalid input');
}

// Use the correct table and column
switch ($action) {
    case 'add':
        $stmt = $pdo->prepare("
            UPDATE snow_balances
            SET days_ahead = days_ahead + 1
            WHERE employee_id = ?
        ");
        break;

    case 'subtract':
        $stmt = $pdo->prepare("
            UPDATE snow_balances
            SET days_ahead = days_ahead - 1
            WHERE employee_id = ?
        ");
        break;

    case 'reset':
        $stmt = $pdo->prepare("
            UPDATE snow_balances
            SET days_ahead = 0
            WHERE employee_id = ?
        ");
        break;
}

$stmt->execute([$employeeId]);

header("Location: employee.php?id={$employeeId}");
exit();
