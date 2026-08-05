<?php
require 'includes/snow_db.php';
require_once __DIR__ . '/../includes/fpdf/fpdf.php';

$stormId = $_GET['storm_id'] ?? null;

if (!$stormId) {
    die('Storm ID missing.');
}

/* -------------------- FETCH STORM -------------------- */
$stmt = $pdo->prepare("
    SELECT storm_name, storm_date
    FROM snow_storms
    WHERE id = ?
");
$stmt->execute([$stormId]);
$storm = $stmt->fetch();

if (!$storm) {
    die('Storm not found.');
}

/* -------------------- FETCH ROUTES -------------------- */
$stmt = $pdo->prepare("
    SELECT employee_identifier, route_name, route_type, worked_at
    FROM snow_routes
    WHERE storm_id = ?
    ORDER BY employee_identifier, worked_at
");
$stmt->execute([$stormId]);
$routes = $stmt->fetchAll();

/* -------------------- GROUP BY EMPLOYEE -------------------- */
$grouped = [];
foreach ($routes as $r) {
    $grouped[$r['employee_identifier']][] = $r;
}

/* -------------------- BUILD PDF -------------------- */
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

/* Header */
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Snow Storm Report',0,1,'C');

$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,$storm['storm_name'],0,1,'C');
$pdf->Cell(0,8,'Storm Date: ' . date('F j, Y', strtotime($storm['storm_date'])),0,1,'C');

$pdf->Ln(6);

/* Body */
$totalRoutes = 0;

foreach ($grouped as $employee => $items) {
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,8,$employee,0,1);

    $pdf->SetFont('Arial','',10);

    foreach ($items as $r) {
        $line =
            date('g:i A', strtotime($r['worked_at'])) .
            " | {$r['route_name']} | " .
            strtoupper($r['route_type']);

        $pdf->Cell(0,6,$line,0,1);
        $totalRoutes++;
    }

    $pdf->Ln(3);
}

/* Summary */
$pdf->Ln(4);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8,"Total Routes: {$totalRoutes}",0,1);

/* Output */
$fileName = 'Storm_' . preg_replace('/\s+/', '_', $storm['storm_name']) . '.pdf';
$pdf->Output('I', $fileName);
