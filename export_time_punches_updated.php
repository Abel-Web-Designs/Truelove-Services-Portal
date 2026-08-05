<?php
require 'includes/db.php';
require 'includes/auth.php';

requireLogin();

if (getUserRole() !== 'admin') {
    die('Access denied.');
}

date_default_timezone_set('America/Indiana/Indianapolis');

require_once 'includes/fpdf/fpdf.php'; // Make sure FPDF is installed

$startDate = $_GET['start_date'] ?? null;

if (!$startDate) {
    die("Start date is required.");
}

$startDateObj = DateTime::createFromFormat('Y-m-d', $startDate);
if (!$startDateObj) {
    die("Invalid start date format.");
}

// Build exactly 7 days (start + 6)
$startDateObj->setTime(0, 0, 0);
$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = (clone $startDateObj)->modify("+$i day");
    $days[] = $d;
}
$rangeStart = (clone $startDateObj);
$rangeEnd   = (clone $startDateObj)->modify("+6 day")->setTime(23, 59, 59);

// Expand fetch range so overnight OUT punches can be paired
$fetchStart = (clone $rangeStart)->modify("-1 day");
$fetchEnd   = (clone $rangeEnd)->modify("+1 day");

// Employees (show everyone except kiosks)
$employees = $pdo->query("
    SELECT id, name, role
    FROM employees
    WHERE role <> 'time_station'
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all punches in expanded window (pairing needs full sequence)
$stmt = $pdo->prepare("
    SELECT
        t.employee_id,
        t.timestamp,
        LOWER(TRIM(t.clock_type)) AS clock_type
    FROM time_logs t
    WHERE t.timestamp BETWEEN ? AND ?
    ORDER BY t.employee_id ASC, t.timestamp ASC
");
$stmt->execute([
    $fetchStart->format('Y-m-d H:i:s'),
    $fetchEnd->format('Y-m-d H:i:s')
]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a lookup set of valid IN-dates we want to count (the 7 days)
$dayKeys = [];
foreach ($days as $d) {
    $dayKeys[$d->format('Y-m-d')] = true;
}

// Accumulators
$secondsByEmpByDay = []; // [employee_id][Y-m-d] => seconds
$secondsByEmpWeek  = []; // [employee_id] => seconds
$lastInByEmp = [];       // [employee_id] => DateTime

foreach ($logs as $log) {
    $empId = (int)$log['employee_id'];
    $type  = $log['clock_type'];
    $ts    = new DateTime($log['timestamp']);

    if ($type === 'in') {
        // Keep the earliest open IN (ignore double IN without OUT)
        if (!isset($lastInByEmp[$empId])) {
            $lastInByEmp[$empId] = $ts;
        }
        continue;
    }

    if ($type === 'out' && isset($lastInByEmp[$empId])) {
        $in  = $lastInByEmp[$empId];
        $out = $ts;

        $diff = $out->getTimestamp() - $in->getTimestamp();
        if ($diff > 0) {
            // Assign hours to the IN date (prevents midnight split)
            $inDateKey = $in->format('Y-m-d');

            // Only count shifts whose IN date falls inside our 7-day export window
            if (isset($dayKeys[$inDateKey])) {
                if (!isset($secondsByEmpByDay[$empId])) $secondsByEmpByDay[$empId] = [];
                if (!isset($secondsByEmpByDay[$empId][$inDateKey])) $secondsByEmpByDay[$empId][$inDateKey] = 0;
                $secondsByEmpByDay[$empId][$inDateKey] += $diff;

                if (!isset($secondsByEmpWeek[$empId])) $secondsByEmpWeek[$empId] = 0;
                $secondsByEmpWeek[$empId] += $diff;
            }
        }

        unset($lastInByEmp[$empId]);
    }
}

// Attendance map for the 7-day window (so we can show "X" when absent)
$attendance = []; // [employee_id][Y-m-d] => present(1/0)
try {
    $stmt = $pdo->prepare("
        SELECT employee_id, attendance_date, present
        FROM daily_attendance
        WHERE attendance_date BETWEEN ? AND ?
    ");
    $stmt->execute([
        $rangeStart->format('Y-m-d'),
        $rangeEnd->format('Y-m-d')
    ]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $eid = (int)$row['employee_id'];
        $d   = (string)$row['attendance_date'];
        $attendance[$eid][$d] = (int)$row['present'];
    }
} catch (Throwable $e) {
    // If attendance table doesn't exist yet, just skip.
    $attendance = [];
}

// Header date labels
$startDateFormatted = $rangeStart->format('F j, Y');
$endDateFormatted   = $rangeEnd->format('F j, Y');

// -------------------- FETCH ALL PAYROLL NOTES FOR THIS PERIOD --------------------
$periodStartKey = $rangeStart->format('Y-m-d');
$periodEndKey   = (clone $rangeStart)->modify('+6 day')->format('Y-m-d');

$notes = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            pn.note,
            pn.created_at,
            pn.updated_at,
            e.name AS created_by_name
        FROM payroll_notes pn
        LEFT JOIN employees e ON e.id = pn.created_by
        WHERE pn.period_start = ? AND pn.period_end = ?
        ORDER BY pn.created_at ASC, pn.id ASC
    ");
    $stmt->execute([$periodStartKey, $periodEndKey]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $notes = [];
}

// FPDF helper: convert UTF-8 to ISO-8859-1 safely (prevents "?" / blank output)
function pdfText($s): string {
    $s = (string)$s;
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    return $converted !== false ? $converted : $s;
}

// PDF
class PDF extends FPDF {
    public $startDateFormatted;
    public $endDateFormatted;
    public $dayLabels = [];

    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, 'Employee Weekly Hours Report', 0, 1, 'C');

        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 7, 'From ' . $this->startDateFormatted . ' to ' . $this->endDateFormatted, 0, 1, 'C');
        $this->Ln(3);

        // Table header
        $this->SetFont('Arial', 'B', 9);

        // Column widths (landscape)
        $wEmp = 50;
        $wDay = 28; // 7 days
        $wTot = 28; // week total

        $this->Cell($wEmp, 9, 'Employee', 1, 0, 'L');
        foreach ($this->dayLabels as $lbl) {
            $this->Cell($wDay, 9, $lbl, 1, 0, 'C');
        }
        $this->Cell($wTot, 9, 'Week Total', 1, 0, 'C');
        $this->Ln();
    }

    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->startDateFormatted = $startDateFormatted;
$pdf->endDateFormatted   = $endDateFormatted;

// Short day labels like "Mon 1/3"
$labels = [];
foreach ($days as $d) {
    $labels[] = $d->format('D n/j');
}
$pdf->dayLabels = $labels;

$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

$wEmp = 50;
$wDay = 28;
$wTot = 28;

// Always print all employees (even if no hours)
// If they were marked absent in attendance, show "X" for that day.
foreach ($employees as $emp) {
    $empId = (int)$emp['id'];
    $name  = (string)$emp['name'];

    $pdf->Cell($wEmp, 8, pdfText($name), 1, 0, 'L');

    $weekSeconds = (int)($secondsByEmpWeek[$empId] ?? 0);

    foreach ($days as $d) {
        $key = $d->format('Y-m-d');
        $sec = (int)($secondsByEmpByDay[$empId][$key] ?? 0);
        $hrs = $sec / 3600;

        $presentFlag = $attendance[$empId][$key] ?? null; // 1, 0, or null (not recorded)

        if ($presentFlag === 0) {
            $pdf->Cell($wDay, 8, 'X', 1, 0, 'C');
        } else {
            $pdf->Cell($wDay, 8, $sec > 0 ? number_format($hrs, 2) : '', 1, 0, 'R');
        }
    }

    $pdf->Cell($wTot, 8, $weekSeconds > 0 ? number_format($weekSeconds / 3600, 2) : '0.00', 1, 0, 'R');
    $pdf->Ln();
}

// -------------------- NOTES SECTION (MULTIPLE NOTES) --------------------
$pdf->Ln(6);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'Payroll Notes', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);

// If you want the notes header to say the exact period:
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 6, pdfText("Period: {$periodStartKey} to {$periodEndKey}"), 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);

if (empty($notes)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->MultiCell(0, 6, pdfText('No notes were saved for this payroll period.'), 1);
    $pdf->SetFont('Arial', '', 10);
} else {
    foreach ($notes as $i => $n) {
        $by = $n['created_by_name'] ?? 'Unknown';
        $createdAt = $n['created_at'] ?? '';
        $text = trim((string)($n['note'] ?? ''));

        // Note header line
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->MultiCell(0, 5, pdfText(($i + 1) . ") {$by} on {$createdAt}"), 1);

        // Note body
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6, pdfText($text !== '' ? $text : '(blank note)'), 1);

        $pdf->Ln(2);
    }
}

$filename = 'employee_weekly_hours_' . $rangeStart->format('Y-m-d') . '_to_' . $rangeEnd->format('Y-m-d') . '.pdf';
$pdf->Output('D', $filename);
exit;
