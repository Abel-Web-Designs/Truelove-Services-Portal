<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

if (getUserRole() !== 'admin') {
    die('Access denied.');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die('Invalid report ID.');
}

$stmt = $pdo->prepare("SELECT ir.*, e.name AS created_by_name FROM incident_reports ir LEFT JOIN employees e ON e.id = ir.created_by WHERE ir.id = ?");
$stmt->execute([$id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    die('Report not found.');
}

$employees = $pdo->query("SELECT id, name FROM employees")->fetchAll(PDO::FETCH_KEY_PAIR);

function safeJsonArray($s) {
    $a = json_decode((string)$s, true);
    return is_array($a) ? $a : [];
}

function pdfText($s) {
    $s = (string)$s;
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    return $converted !== false ? $converted : $s;
}

$employeeIds = safeJsonArray($report['employee_ids_json'] ?? '[]');
$employeeNames = [];
foreach ($employeeIds as $eid) {
    $eid = (int)$eid;
    $employeeNames[] = $employees[$eid] ?? "Employee #{$eid}";
}

$photos = safeJsonArray($report['photos_json'] ?? '[]');
$photos = array_values(array_filter($photos, function ($ph) {
    $ph = basename((string)$ph);
    return $ph !== '' && is_file(__DIR__ . '/uploads/incidents/' . $ph);
}));

require_once 'includes/fpdf/fpdf.php';

class IncidentPDF extends FPDF {
    public $reportId = '';
    public $reportDate = '';
    public $reportTime = '';
    public $createdBy = '';
    public $createdAt = '';

    function Header() {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 8, pdfText('Incident Report #' . $this->reportId), 0, 1, 'C');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 7, pdfText('Date: ' . $this->reportDate . '   Time: ' . $this->reportTime), 0, 1, 'C');
        $this->Cell(0, 7, pdfText('Submitted by: ' . $this->createdBy . '   Created at: ' . $this->createdAt), 0, 1, 'C');
        $this->Ln(4);

        $this->SetDrawColor(77, 91, 115);
        $this->SetLineWidth(0.4);
        $this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
        $this->Ln(4);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, pdfText('Page ' . $this->PageNo()), 0, 0, 'C');
    }

    function sectionTitle($title) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(34, 34, 34);
        $this->Cell(0, 7, pdfText($title), 0, 1, 'L');
        $this->Ln(2);
    }

    function sectionText($text) {
        $this->SetFont('Arial', '', 11);
        $this->SetTextColor(10, 10, 10);
        $this->MultiCell(0, 6, pdfText($text), 0, 'L');
        $this->Ln(4);
    }
}

$pdf = new IncidentPDF('P', 'mm', 'A4');
$pdf->reportId = $id;
$pdf->reportDate = $report['incident_date'] ?? '';
$pdf->reportTime = substr((string)($report['incident_time'] ?? ''), 0, 5);
$pdf->createdBy = $report['created_by_name'] ?? 'Unknown';
$pdf->createdAt = $report['created_at'] ?? '';

$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

$pdf->sectionTitle('Employees Involved');
$pdf->sectionText(!empty($employeeNames) ? implode(', ', $employeeNames) : 'None listed.');

$pdf->sectionTitle('Equipment Involved');
$pdf->sectionText(trim((string)$report['equipment_involved']) ?: 'None reported.');

$pdf->sectionTitle('Incident Details');
$pdf->sectionText(trim((string)$report['incident_details']) ?: 'No details provided.');

$pdf->sectionTitle('Reason Incident Occurred');
$pdf->sectionText(trim((string)$report['incident_reason']) ?: 'No reason provided.');

if (!empty($photos)) {
    $pdf->sectionTitle('Photos');
    $photoCount = 0;
    $imageWidth = 90;
    $imageHeight = 60;
    foreach ($photos as $index => $photo) {
        $path = __DIR__ . '/uploads/incidents/' . basename((string)$photo);
        if (!is_file($path)) {
            continue;
        }
        if ($photoCount > 0 && $photoCount % 2 === 0) {
            $pdf->Ln($imageHeight + 8);
        }
        if ($photoCount % 2 === 1) {
            $pdf->SetX($pdf->GetX() + $imageWidth + 10);
        } else {
            $pdf->SetX($pdf->lMargin);
        }
        $pdf->Image($path, $pdf->GetX(), $pdf->GetY(), $imageWidth, $imageHeight);
        if ($photoCount % 2 === 1) {
            $pdf->Ln($imageHeight + 8);
        }
        $photoCount++;
    }
    if ($photoCount % 2 === 1) {
        $pdf->Ln($imageHeight + 8);
    }
}

$fileName = 'Incident_Report_' . $id . '.pdf';
$pdf->Output('I', $fileName);

