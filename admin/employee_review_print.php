<?php
require '../includes/db.php';
require '../includes/auth.php';
requireLogin();

if (getUserRole() !== 'admin') {
    die('Access denied.');
}

date_default_timezone_set('America/Indiana/Indianapolis');

require_once '../includes/fpdf/fpdf.php';

$reviewId = (int) ($_GET['id'] ?? 0);
if ($reviewId <= 0) {
    die('Invalid review ID.');
}

$stmt = $pdo->prepare("
    SELECT
        r.*,
        e.name AS employee_name,
        cb.name AS created_by_name
    FROM employee_reviews r
    JOIN employees e ON e.id = r.employee_id
    LEFT JOIN employees cb ON cb.id = r.created_by
    WHERE r.id = ?
    LIMIT 1
");
$stmt->execute([$reviewId]);
$review = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$review) {
    die('Review not found.');
}

class PDF extends FPDF
{
    function Header()
    {
        $this->Image(
            $_SERVER['DOCUMENT_ROOT'] . '/img/truelove-services.jpeg',
            65,
            8,
            70
        );

        // Move divider lower
        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, 52, 195, 52);

        // Start content lower (2–3 extra lines)
        $this->SetY(56);

        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'Employee Review', 0, 1, 'C');
        $this->Ln(2);
    }

    function SectionTitle($title)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, $title, 0, 1);
    }

    function SectionBody($text)
    {
        $this->SetFont('Arial', '', 11);
        $this->MultiCell(0, 6, $text !== '' ? $text : '-');
        $this->Ln(2);
    }
}

function cleanText($text)
{
    $text = (string) $text;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
}

$pdf = new PDF();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(45, 8, 'Employee:', 0, 0);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, cleanText($review['employee_name']), 0, 1);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(45, 8, 'Review Date:', 0, 0);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, cleanText(date('F j, Y', strtotime($review['review_date']))), 0, 1);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(45, 8, 'Overall Rating:', 0, 0);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, cleanText($review['overall_rating'] ?: '-'), 0, 1);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(45, 8, 'Created By:', 0, 0);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, cleanText($review['created_by_name'] ?: '-'), 0, 1);

$pdf->Ln(4);

$pdf->SectionTitle('Areas Employee Is Exceeding In');
$pdf->SectionBody(cleanText($review['exceeds_areas'] ?: '-'));

$pdf->SectionTitle('Areas Where Employee Could Improve');
$pdf->SectionBody(cleanText($review['improvement_areas'] ?: '-'));

$pdf->SectionTitle('Action Plan / Goals');
$pdf->SectionBody(cleanText($review['action_plan'] ?: '-'));

$pdf->SectionTitle('Additional Notes');
$pdf->SectionBody(cleanText($review['additional_notes'] ?: '-'));

$pdf->Ln(8);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(90, 8, 'Employee Signature: _____________________', 0, 0);
$pdf->Cell(90, 8, 'Date: __________________', 0, 1);

$pdf->Ln(8);

$pdf->Cell(90, 8, 'Reviewer Signature: _____________________', 0, 0);
$pdf->Cell(0, 8, 'Date: __________________', 0, 1);

$pdf->Output('I', 'employee_review_' . $reviewId . '.pdf');
exit;