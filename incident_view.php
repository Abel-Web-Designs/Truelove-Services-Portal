<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    die("Invalid report.");
}

$stmt = $pdo->prepare("
SELECT ir.*, e.name AS created_by_name
FROM incident_reports ir
LEFT JOIN employees e ON e.id = ir.created_by
WHERE ir.id = ?
");
$stmt->execute([$id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    die("Report not found.");
}

/* Employee map */
$employees = $pdo->query("SELECT id, name FROM employees")->fetchAll(PDO::FETCH_KEY_PAIR);

function safeJsonArray($s){
    $a = json_decode((string)$s,true);
    return is_array($a) ? $a : [];
}

function h($s){
    return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
}

$employeeIds = safeJsonArray($report['employee_ids_json'] ?? '[]');
$employeeNames = [];

foreach ($employeeIds as $eid) {
    $employeeNames[] = $employees[$eid] ?? "Employee #$eid";
}

$photos = safeJsonArray($report['photos_json'] ?? '[]');

require 'includes/header.php';
?>

<!DOCTYPE html>
<html>
<head>

<title>Incident Report #<?= $id ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body {
    background: #0f172a;
    color: #e2e8f0;
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.report-wrapper {
    background: #111827;
    border: 1px solid #334155;
    padding: 30px;
    border-radius: 18px;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
}

.report-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 24px;
    align-items: flex-start;
    margin-bottom: 28px;
}

.report-header h1 {
    font-size: 2.1rem;
    margin: 0;
    letter-spacing: -0.02em;
}

.report-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    width: 100%;
}

.meta-item {
    background: rgba(148, 163, 184, 0.08);
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 14px;
    padding: 14px 16px;
}

.meta-item strong {
    display: block;
    color: #94a3b8;
    font-size: 0.85rem;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.meta-item span {
    display: block;
    color: #f8fafc;
    font-size: 1rem;
    line-height: 1.6;
}

.report-box {
    background: #111827;
    border: 1px solid #334155;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    min-height: 50px;
    page-break-inside: avoid;
}

.report-box strong {
    display: block;
    font-size: 0.95rem;
    margin-bottom: 10px;
    color: #cbd5e1;
}

.report-text {
    color: #f8fafc;
    line-height: 1.8;
    white-space: pre-wrap;
}

.report-images {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.report-images img {
    width: 100%;
    height: 160px;
    object-fit: cover;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.18);
}

@media (max-width: 767px) {
    .report-header {
        flex-direction: column;
    }
}

@media print {
    body, .container, .report-wrapper, .report-box, .meta-item {
        background: #fff !important;
        color: #000 !important;
        box-shadow: none !important;
    }

    .bg-dark, .text-light, .border-secondary, .btn, .btn-outline-light, .btn-outline-primary, .btn-outline-secondary {
        background: transparent !important;
        color: #000 !important;
    }

    .report-wrapper {
        border-color: #000 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        padding: 0 !important;
    }

    .report-box {
        border-color: #000 !important;
        background: #fff !important;
        padding: 18px !important;
    }

    .meta-item {
        background: transparent !important;
        border-color: #000 !important;
        color: #000 !important;
    }

    .meta-item strong,
    .report-box strong,
    .section-title {
        color: #000 !important;
    }

    .report-text {
        color: #000 !important;
    }

    .no-print {
        display: none !important;
    }

    .report-header, .report-box {
        page-break-inside: avoid;
    }

    .report-images {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .report-images img {
        height: auto !important;
    }
}

</style>

</head>

<body class="bg-dark">

<div class="container py-4 text-light">

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h2>Incident Report #<?= $id ?></h2>
            <p class="text-muted small mb-0">Preview and export a cleaner PDF version using the dedicated export tool.</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-light btn-sm">
                Print
            </button>
            <a href="incident_report_pdf.php?id=<?= $id ?>" class="btn btn-primary btn-sm">
                Export PDF
            </a>
    </div>
</div>

<div class="report-wrapper">
    <div class="report-header">
        <div>
            <h1>Incident Report</h1>
            <div class="text-muted small">Report ID: #<?= $id ?></div>
        </div>
        <div class="report-meta">
            <div class="meta-item">
                <strong>Date</strong>
                <span><?= h($report['incident_date']) ?></span>
            </div>
            <div class="meta-item">
                <strong>Time</strong>
                <span><?= h(substr($report['incident_time'], 0, 5)) ?></span>
            </div>
            <div class="meta-item">
                <strong>Submitted By</strong>
                <span><?= h($report['created_by_name'] ?: 'Unknown') ?></span>
            </div>
            <div class="meta-item">
                <strong>Created On</strong>
                <span><?= h($report['created_at']) ?></span>
            </div>
        </div>
    </div>

    <div class="report-box mb-3">

<strong>Employees Involved</strong>

<div class="mt-2 report-text">
<?= h(implode(', ',$employeeNames)) ?>
</div>

</div>


<div class="report-box mb-3">

<strong>Submitted By</strong>

<div class="mt-2">
<?= h($report['created_by_name']) ?>  
<span class="text-light small">(<?= h($report['created_at']) ?>)</span>
</div>

</div>


<div class="report-box mb-3">

<strong>Equipment Involved</strong>

<div class="mt-2 report-text">
<?= nl2br(h($report['equipment_involved'])) ?>
</div>

</div>


<div class="report-box mb-3">

<strong>Incident Details</strong>

<div class="mt-2 report-text">
<?= nl2br(h($report['incident_details'])) ?>
</div>

</div>


<div class="report-box mb-3">

<strong>Reason Incident Occurred</strong>

<div class="mt-2 report-text">
<?= nl2br(h($report['incident_reason'])) ?>
</div>

</div>


<?php if (!empty($photos)): ?>

<div class="report-box">

<strong>Photos</strong>

<div class="report-images mt-3">

<?php foreach ($photos as $ph): ?>

<?php
$ph = basename($ph);
$url = "uploads/incidents/".$ph;
?>

<div>
    <a href="<?= h($url) ?>" target="_blank">
        <img src="<?= h($url) ?>" alt="Incident photo" />
    </a>
</div>

<?php endforeach; ?>

</div>

</div>

<?php endif; ?>


</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>