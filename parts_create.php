<?php
session_start();
require 'includes/db.php';

header('Content-Type: application/json');

/**
 * Toggle this to false after you fix the issue.
 * When true, you'll see the real database error message.
 */
$DEBUG = true;

$types = ['Truck','Trailer','Skidsteer','Loader','Mower','Trimmer','Blower'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Make sure PDO throws exceptions (in case your db.php didn't set it)
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) { /* ignore */ }

// Read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

$type_new     = trim((string)($data['type_new'] ?? ''));
$name         = trim((string)($data['name'] ?? ''));
$part_number  = trim((string)($data['part_number'] ?? ''));

// Validation
if ($type_new === '' || !in_array($type_new, $types, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid type.']);
    exit;
}
if ($name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Part name is required.']);
    exit;
}

try {
    // Optional duplicate check
    $chk = $pdo->prepare("SELECT id FROM parts_list WHERE type_new = ? AND name = ? LIMIT 1");
    $chk->execute([$type_new, $name]);
    if ($chk->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'That part already exists for this type.']);
        exit;
    }

    /**
     * INSERT:
     * - If part_number column is NOT NULL, inserting NULL will fail.
     *   So we insert '' (empty string) instead of NULL.
     */
    $stmt = $pdo->prepare("
        INSERT INTO parts_list (name, type_new, part_number)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $name,
        $type_new,
        ($part_number !== '' ? $part_number : '') // <-- safer than NULL
    ]);

    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
} catch (PDOException $e) {
    http_response_code(500);

    // Return the real error while debugging
    if ($DEBUG) {
        echo json_encode([
            'ok' => false,
            'error' => 'DB ERROR: ' . $e->getMessage(),
            'code' => (int)$e->getCode(),
        ]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Server error creating part.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    if ($DEBUG) {
        echo json_encode(['ok' => false, 'error' => 'ERROR: ' . $e->getMessage()]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Server error creating part.']);
    }
}
