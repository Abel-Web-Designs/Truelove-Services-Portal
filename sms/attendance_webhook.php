<?php
require_once __DIR__ . '/../includes/db.php';

date_default_timezone_set('America/New_York');
$timezone = new DateTimeZone('America/New_York');
$createdAt = (new DateTime('now', $timezone))->format('Y-m-d H:i:s');
$attendanceDate = (new DateTime('now', $timezone))->format('Y-m-d');

header('Content-Type: application/json');

$expectedToken = 'truelove_services_attendance_webhook'; // Change this to a secure, random token in production
$providedToken = $_REQUEST['token'] ?? '';

if (!hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

$phone = trim($_REQUEST['phone'] ?? '');
$name = trim($_REQUEST['name'] ?? '');
$message = trim($_REQUEST['message'] ?? '');

if ($phone === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing phone or message']);
    exit;
}

$cleanPhone = preg_replace('/\D+/', '', $phone);
if (strlen($cleanPhone) === 11 && str_starts_with($cleanPhone, '1')) {
    $cleanPhone = substr($cleanPhone, 1);
}

$lowerMessage = strtolower($message);
$status = 'unknown';

$outKeywords = ['out', 'off', 'sick', 'calling off', 'call off', 'not coming', 'absent', 'doctor', 'appointment', 'family emergency'];
$inKeywords = ['in', 'coming in', 'on my way', 'present', 'arriving', 'running late'];

foreach ($outKeywords as $keyword) {
    if (str_contains($lowerMessage, $keyword)) {
        $status = 'out';
        break;
    }
}

if ($status === 'unknown') {
    foreach ($inKeywords as $keyword) {
        if (str_contains($lowerMessage, $keyword)) {
            $status = 'out';
            //$status = 'in';
            break;
        }
    }
}

$note = preg_replace('/^(out|off|in|sick)\s*[-:]?\s*/i', '', $message);
$note = trim($note);

if ($note === '') {
    $note = $status === 'out' ? 'Out today' : ($status === 'in' ? 'Present / coming in' : $message);
}

$employeeId = null;

try {
    $stmt = $pdo->prepare("
        SELECT id
        FROM employees
        WHERE 
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), '(', ''), ')', ''), ' ', ''), '.', '') = ?
        LIMIT 1
    ");
    $stmt->execute([$cleanPhone]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        $employeeId = (int)$employee['id'];
    }
} catch (Throwable $e) {
    $employeeId = null;
}

$stmt = $pdo->prepare("
    INSERT INTO attendance_text_logs
        (employee_id, ghl_contact_name, phone, message, status, note, attendance_date, created_at, raw_payload)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $employeeId,
    $name !== '' ? $name : null,
    $cleanPhone,
    $message,
    $status,
    $note,
    $attendanceDate,
    $createdAt,
    json_encode($_REQUEST)
]);

echo json_encode([
    'success' => true,
    'status' => $status,
    'employee_id' => $employeeId,
    'phone' => $cleanPhone,
    'message' => $message
]);