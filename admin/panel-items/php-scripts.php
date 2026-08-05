<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Use your local business timezone everywhere on this page
date_default_timezone_set('America/Indiana/Indianapolis');

/* -------------------- QUICK ENABLE/DISABLE EMPLOYEE -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_employee_active') {
    $empId     = (int)($_POST['employee_id'] ?? 0);
    $setActive = (int)($_POST['set_active'] ?? 0);
    $setActive = $setActive ? 1 : 0;

    if ($empId > 0) {
        $stmt = $pdo->prepare("UPDATE employees SET is_active = ? WHERE id = ?");
        $stmt->execute([$setActive, $empId]);
    }

    // PRG redirect back to Employees tab
    header('Location: admin_panel.php?employee_toggled=1#employees');
    exit;
}

/* -------------------- PAYROLL NOTES (SAVE + LOAD) -------------------- */
$payrollNoteError   = '';
$payrollNoteSuccess = '';

function currentEmployeeId(): int {
    if (isset($_SESSION['employee_id'])) return (int)$_SESSION['employee_id'];
    if (isset($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    return 0;
}

$action = $_POST['action'] ?? ''; // 'save' or 'load'

// Determine selected payroll week for UI
// Priority: POST (when clicking Load/Save) -> GET (after redirect)
$uiStart = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // your date inputs are in the form; they need names or you can use hidden period_start
    $uiStart = $_POST['period_start'] ?? ($_POST['start_date'] ?? '');
} else {
    $uiStart = $_GET['start_date'] ?? '';
}

$uiStartObj = null;
$uiEndObj   = null;
$uiEnd      = '';

if ($uiStart) {
    $uiStartObj = DateTime::createFromFormat('Y-m-d', $uiStart);
    if ($uiStartObj) {
        $uiEndObj = (clone $uiStartObj)->modify('+6 day');
        $uiEnd = $uiEndObj->format('Y-m-d');
    }
}

// SAVE NOTE (only when Save button clicked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $periodStart = $_POST['period_start'] ?? '';
    $periodEnd   = $_POST['period_end'] ?? '';
    $note        = trim($_POST['note'] ?? '');
    $createdBy   = currentEmployeeId();

    $startObj = DateTime::createFromFormat('Y-m-d', $periodStart);
    $endObj   = DateTime::createFromFormat('Y-m-d', $periodEnd);

    if (!$startObj || !$endObj) {
        $payrollNoteError = 'Invalid start/end date.';
    } elseif ($endObj < $startObj) {
        $payrollNoteError = 'End date must be after start date.';
    } elseif ($note === '') {
        $payrollNoteError = 'Note cannot be empty.';
    } elseif ($createdBy <= 0) {
        $payrollNoteError = 'Unable to determine who is saving this note (missing session employee_id/user_id).';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO payroll_notes (period_start, period_end, note, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$periodStart, $periodEnd, $note, $createdBy]);

            header('Location: admin_panel.php?start_date=' . urlencode($periodStart) . '&note_saved=1#payroll');
            exit;
        } catch (Throwable $e) {
            $payrollNoteError = 'Failed to save payroll note: ' . $e->getMessage();
        }
    }
}

// LOAD NOTES (only when Load button clicked OR after redirect with start_date)
$payrollNotesForWeek = [];

$shouldLoad =
    ($action === 'load') ||                       // user clicked Load
    (!empty($_GET['start_date']));                // page loaded/redirected with start_date

if ($shouldLoad && $uiStartObj && $uiEnd) {
    $stmt = $pdo->prepare("
        SELECT pn.id, pn.note, pn.created_at, e.name AS created_by_name
        FROM payroll_notes pn
        LEFT JOIN employees e ON e.id = pn.created_by
        WHERE pn.period_start = ? AND pn.period_end = ?
        ORDER BY pn.created_at DESC, pn.id DESC
    ");
    $stmt->execute([$uiStart, $uiEnd]);
    $payrollNotesForWeek = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -------------------- ADD TIME PUNCH ---------------------- */
$addPunchSuccess = '';
$addPunchError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_time_punch'])) {
    $employeeId = (int)($_POST['employee_id'] ?? 0);

    $inDate  = $_POST['time_in_date'] ?? '';
    $inTime  = $_POST['time_in_time'] ?? '';

    $outDate = $_POST['time_out_date'] ?? '';
    $outTime = $_POST['time_out_time'] ?? '';

    if ($employeeId <= 0 || !$inDate || !$inTime || !$outDate || !$outTime) {
        $addPunchError = 'Please select an employee and enter both Time In and Time Out.';
    } else {
        // Combine into SQL datetimes
        $timeIn  = $inDate  . ' ' . $inTime  . ':00';
        $timeOut = $outDate . ' ' . $outTime . ':00';

        $inTs  = strtotime($timeIn);
        $outTs = strtotime($timeOut);

        if (!$inTs || !$outTs) {
            $addPunchError = 'Invalid date/time values.';
        } elseif ($outTs <= $inTs) {
            $addPunchError = 'Time Out must be after Time In.';
        } else {
            try {
                $pdo->beginTransaction();

                // Insert IN
                $stmt = $pdo->prepare("INSERT INTO time_logs (employee_id, clock_type, timestamp) VALUES (?, 'in', ?)");
                $stmt->execute([$employeeId, $timeIn]);

                // Insert OUT
                $stmt = $pdo->prepare("INSERT INTO time_logs (employee_id, clock_type, timestamp) VALUES (?, 'out', ?)");
                $stmt->execute([$employeeId, $timeOut]);

                $pdo->commit();

                // PRG redirect back to Time tab
                header('Location: admin_panel.php#time');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $addPunchError = 'Failed to add time punch.';
            }
        }
    }
}

// Employee list for dropdown
$employeesForPunch = $pdo->query("SELECT id, name FROM employees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Load employees for dropdown (put this where you load other admin_panel data)
$employeesForPunch = $pdo->query("SELECT id, name FROM employees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// If redirected with success
if (isset($_GET['punch_added'])) {
    $addPunchSuccess = 'Time punch added successfully.';
}

/* -------------------- TEXTBELT HELPERS -------------------- */
function getTextbeltQuota($key)
{
    $json = @file_get_contents("https://textbelt.com/quota/" . urlencode($key));
    if (!$json) return null;
    $data = json_decode($json, true);
    return $data['quotaRemaining'] ?? null;
}

$TEXTBELT_KEY = getenv('TEXTBELT_API_KEY') ?: '089fcbbf8e498369811562800ba94222306a5f1fWQuAT5KRh6gNW5Y7jbaRjZCVH';
$quotaRemaining = getTextbeltQuota($TEXTBELT_KEY);

/* -------------------- ADD EMPLOYEE -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $name = trim($_POST['name'] ?? '');
    $pin  = trim($_POST['pin'] ?? '');
    $role = trim($_POST['role'] ?? 'employee');

    if ($name !== '' && $pin !== '' && $role !== '') {
        $stmt = $pdo->prepare("INSERT INTO employees (name, pin, role) VALUES (?, ?, ?)");
        $stmt->execute([$name, $pin, $role]);
        header("Location: admin_panel.php?employee_added=1");
        exit;
    } else {
        header("Location: admin_panel.php?employee_added=0");
        exit;
    }
}

/* -------------------- ANNOUNCEMENTS -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement'])) {
    $message = trim($_POST['announcement'] ?? '');

    if ($message !== '') {
        $pdo->prepare("INSERT INTO announcements (message) VALUES (?)")->execute([$message]);

        // Determine recipients
        $selected = $_POST['recipients'] ?? []; // array of employee IDs
        if (empty($selected)) {
            // No selection = send to everyone
            $phones = $pdo->query("SELECT phone FROM employees WHERE is_active = 1 AND phone IS NOT NULL AND phone != ''")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Send only to selected employees
            $in = str_repeat('?,', count($selected) - 1) . '?';
            $stmt = $pdo->prepare("SELECT phone FROM employees WHERE is_active = 1 AND id IN ($in) AND phone IS NOT NULL AND phone != ''");
            $stmt->execute($selected);
            $phones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($phones as $row) {
            $phone = preg_replace('/\D/', '', $row['phone'] ?? '');
            if ($phone === '') continue;

            $payload = http_build_query([
                'phone' => $phone,
                'message' => "New Announcement: " . $message,
                'key' => $TEXTBELT_KEY
            ]);

            @file_get_contents(
                'https://textbelt.com/text',
                false,
                stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-type: application/x-www-form-urlencoded',
                        'content' => $payload,
                        'timeout' => 10
                    ]
                ])
            );
        }

        $quotaRemaining = getTextbeltQuota($TEXTBELT_KEY);
        header("Location: admin_panel.php?announcement_sent=1");
        exit;
    } else {
        header("Location: admin_panel.php?announcement_sent=0");
        exit;
    }
}

/* -------------------- DAILY ATTENDANCE (SAVE + LOAD) -------------------- */
$attendanceError   = '';
$attendanceSuccess = '';

// Selected date for attendance UI (GET wins for navigation; POST used on save)
$attendanceDate = $_GET['attendance_date'] ?? ($_POST['attendance_date'] ?? date('Y-m-d'));
$attendanceDateObj = DateTime::createFromFormat('Y-m-d', $attendanceDate);
if (!$attendanceDateObj) {
    $attendanceDate = date('Y-m-d');
    $attendanceDateObj = DateTime::createFromFormat('Y-m-d', $attendanceDate);
}

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $attendanceDate = $_POST['attendance_date'] ?? date('Y-m-d');
    $attendanceDateObj = DateTime::createFromFormat('Y-m-d', $attendanceDate);

    $presentIds = $_POST['present'] ?? []; // array of employee IDs that are present
    if (!is_array($presentIds)) $presentIds = [];
    $presentSet = [];
    foreach ($presentIds as $pid) {
        $presentSet[(int)$pid] = true;
    }

    $dayNote = trim($_POST['attendance_note'] ?? '');
    $createdBy = currentEmployeeId();

    if (!$attendanceDateObj) {
        $attendanceError = 'Invalid attendance date.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1) Save day-level note
            // Table: attendance_days (attendance_date PK)
            $stmt = $pdo->prepare("
                INSERT INTO attendance_days (attendance_date, note, created_by, created_at, updated_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    note = VALUES(note),
                    created_by = VALUES(created_by),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $attendanceDateObj->format('Y-m-d'),
                ($dayNote === '' ? null : $dayNote),
                $createdBy > 0 ? $createdBy : null
            ]);

            // 2) Save per-employee present/absent flags
            // Table: daily_attendance (attendance_date + employee_id UNIQUE)
            $emps = $pdo->query("SELECT id, role FROM employees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                INSERT INTO daily_attendance (attendance_date, employee_id, present, updated_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    present = VALUES(present),
                    updated_at = CURRENT_TIMESTAMP
            ");

            foreach ($emps as $e) {
                $eid = (int)$e['id'];

                // Skip kiosks/time stations (optional)
                if (($e['role'] ?? '') === 'time_station') continue;

                $present = isset($presentSet[$eid]) ? 1 : 0;
                $stmt->execute([$attendanceDateObj->format('Y-m-d'), $eid, $present]);
            }

            $pdo->commit();

            header('Location: admin_panel.php?attendance_date=' . urlencode($attendanceDateObj->format('Y-m-d')) . '&attendance_saved=1#attendance');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $attendanceError = 'Failed to save attendance: ' . $e->getMessage();
        }
    }
}

// Load attendance + note for the selected date
$attendanceNote = '';
$attendancePresentById = []; // [employee_id] => 1/0

try {
    // Note
    $stmt = $pdo->prepare("SELECT note FROM attendance_days WHERE attendance_date = ? LIMIT 1");
    $stmt->execute([$attendanceDateObj->format('Y-m-d')]);
    $attendanceNote = (string)($stmt->fetchColumn() ?? '');

    // Per-employee present
    $stmt = $pdo->prepare("SELECT employee_id, present FROM daily_attendance WHERE attendance_date = ?");
    $stmt->execute([$attendanceDateObj->format('Y-m-d')]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attendancePresentById[(int)$row['employee_id']] = (int)$row['present'];
    }
} catch (Throwable $e) {
    // If tables don't exist yet, just show empty UI.
    $attendanceNote = '';
    $attendancePresentById = [];
}

if (isset($_GET['attendance_saved']) && $_GET['attendance_saved'] == 1) {
    $attendanceSuccess = 'Attendance saved.';
}

/* -------------------- DATA -------------------- */
$employees = $pdo->query("
    SELECT * 
    FROM employees 
    ORDER BY 
        is_active DESC,
        FIELD(role,'admin','employee','mechanic','truck_driver','work_phone','time_station'),
        name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Active employees (used for attendance + exports dropdowns)
$employeesActive = $pdo->query("SELECT * FROM employees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

/* ---------- FETCH RAW TIME LOGS ---------- */
$rawLogs = $pdo->query("
    SELECT
        e.name,
        t.timestamp,
        t.clock_type
    FROM time_logs t
    JOIN employees e ON e.id = t.employee_id
    ORDER BY e.name, t.timestamp
")->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- EQUIPMENT PASSCODES CRUD -------------------- */
// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_passcode'])) {
    $equipmentId = (int)($_POST['equipment_id'] ?? 0);
    $label       = trim($_POST['label'] ?? '');
    $passcode    = trim($_POST['passcode'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if ($equipmentId > 0 && $passcode !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO equipment_passcodes (equipment_id, label, passcode, notes, is_active)
            VALUES (?,?,?,?,?)
        ");
        $stmt->execute([$equipmentId, $label ?: null, $passcode, $notes ?: null, $isActive]);
        header("Location: admin_panel.php?pc_added=1");
        exit;
    } else {
        header("Location: admin_panel.php?pc_added=0");
        exit;
    }
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_passcode'])) {
    $id          = (int)($_POST['id'] ?? 0);
    $equipmentId = (int)($_POST['equipment_id'] ?? 0);
    $label       = trim($_POST['label'] ?? '');
    $passcode    = trim($_POST['passcode'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if ($id > 0 && $equipmentId > 0 && $passcode !== '') {
        $stmt = $pdo->prepare("
            UPDATE equipment_passcodes
            SET equipment_id=?, label=?, passcode=?, notes=?, is_active=?
            WHERE id=?
        ");
        $stmt->execute([$equipmentId, $label ?: null, $passcode, $notes ?: null, $isActive, $id]);
        header("Location: admin_panel.php?pc_updated=1");
        exit;
    } else {
        header("Location: admin_panel.php?pc_updated=0");
        exit;
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_passcode'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM equipment_passcodes WHERE id=?")->execute([$id]);
        header("Location: admin_panel.php?pc_deleted=1");
        exit;
    }
}

/* -------------------- APP LOGINS CRUD (FIXED) -------------------- */
/*
Expected table: app_passcodes
Columns assumed: id, app_name, app_username, app_password, created_at (optional)
If you also have notes/is_active, you can add them later.
*/

// Add Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_login'])) {
    $app_name     = trim($_POST['app_name'] ?? '');
    $app_username = trim($_POST['app_username'] ?? '');
    $app_password = trim($_POST['app_password'] ?? '');

    if ($app_name !== '' && $app_username !== '' && $app_password !== '') {
        $stmt = $pdo->prepare("INSERT INTO app_passcodes (app_name, app_username, app_password) VALUES (?, ?, ?)");
        $stmt->execute([$app_name, $app_username, $app_password]);
        header("Location: admin_panel.php?login_added=1");
        exit;
    } else {
        header("Location: admin_panel.php?login_added=0");
        exit;
    }
}

// Update Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_login'])) {
    $id           = (int)($_POST['id'] ?? 0);
    $app_name     = trim($_POST['app_name'] ?? '');
    $app_username = trim($_POST['app_username'] ?? '');
    $app_password = trim($_POST['app_password'] ?? '');

    if ($id > 0 && $app_name !== '' && $app_username !== '' && $app_password !== '') {
        $stmt = $pdo->prepare("
            UPDATE app_passcodes
            SET app_name=?, app_username=?, app_password=?
            WHERE id=?
        ");
        $stmt->execute([$app_name, $app_username, $app_password, $id]);
        header("Location: admin_panel.php?login_updated=1");
        exit;
    } else {
        header("Location: admin_panel.php?login_updated=0");
        exit;
    }
}

// Delete Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_login'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM app_passcodes WHERE id=?")->execute([$id]);
        header("Location: admin_panel.php?login_deleted=1");
        exit;
    }
}

require 'includes/header.php';
?>