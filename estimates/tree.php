<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireLogin();

date_default_timezone_set('America/Indiana/Indianapolis');

if (!in_array(getUserRole(), ['admin'])) {
    header('Location: ../dashboard.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Default Pricing Options
|--------------------------------------------------------------------------
*/
$trunkOptions = [
    '' => ['label' => 'Select trunk size', 'price' => 0],
    'trunk_small' => ['label' => 'Small trunk (0" - 12")', 'price' => 100.00],
    'trunk_medium' => ['label' => 'Medium trunk (13" - 24")', 'price' => 200.00],
    'trunk_large' => ['label' => 'Large trunk (25" - 36")', 'price' => 300.00],
    'trunk_xl' => ['label' => 'Extra large trunk (37"+)', 'price' => 400.00],
];

$canopyOptions = [
    '' => ['label' => 'Select canopy size', 'price' => 0],
    'canopy_small' => ['label' => 'Small canopy', 'price' => 100.00],
    'canopy_medium' => ['label' => 'Medium canopy', 'price' => 200.00],
    'canopy_large' => ['label' => 'Large canopy', 'price' => 300.00],
    'canopy_xl' => ['label' => 'Extra large canopy', 'price' => 400.00],
];

$timeOptions = [
    '' => ['label' => 'Select estimated time', 'price' => 0],
    '1_day' => ['label' => '1 day', 'price' => 500.00],
    '2_day' => ['label' => '2 days', 'price' => 1000.00],
    '3_day' => ['label' => '3 days', 'price' => 1500.00],
    '4_day' => ['label' => '4 days', 'price' => 2000.00],
    '5_day' => ['label' => '5 days', 'price' => 2500.00],
];

$truckOptions = [
    '' => ['label' => 'Select truck access', 'price' => 0],
    'no_truck' => ['label' => 'No bucket truck needed', 'price' => 0.00],
    'small_bucket' => ['label' => 'Small bucket truck', 'price' => 400.00],
    'large_bucket' => ['label' => 'Large bucket truck', 'price' => 750.00],
];

$obstacleOptions = [
    '' => ['label' => 'Select obstacles', 'price' => 0],
    'none' => ['label' => 'No obstacles', 'price' => 0.00],
    'some' => ['label' => 'Some obstacles', 'price' => 400.00],
    'heavy' => ['label' => 'Heavy obstacles', 'price' => 750.00],
    'high_risk' => ['label' => 'High risk', 'price' => 1000.00],
];

$additionalServiceOptions = [
    'stump_grinding' => ['label' => 'Stump Grinding', 'price' => 250.00],
    'regrade_reseed' => ['label' => 'Regrade / Reseed', 'price' => 350.00],
    'yard_repair' => ['label' => 'Yard Repair', 'price' => 200.00],
    'material_yard_dirt' => ['label' => 'Yard of Dirt', 'price' => 50.00],
    'material_other' => ['label' => 'Other', 'price' => 1.00],
];

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function money($amount)
{
    return '$' . number_format((float) $amount, 2);
}

function getOptionData(array $options, string $key): array
{
    return $options[$key] ?? ['label' => 'Unknown', 'price' => 0];
}

function currentFileUrl(array $params = []): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = $_SERVER['PHP_SELF'];

    $query = http_build_query($params);
    return $scheme . '://' . $host . $path . ($query ? '?' . $query : '');
}

function estimatePdfUrl(int $estimateId): string
{
    return currentFileUrl(['pdf' => $estimateId]);
}

function buildPostedTrees(): array
{
    $trees = $_POST['trees'] ?? [];
    return is_array($trees) ? $trees : [];
}

function buildPostedServices(): array
{
    $services = $_POST['services'] ?? [];
    return is_array($services) ? $services : [];
}

function ensurePricingTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS estimate_pricing_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            estimate_type VARCHAR(100) NOT NULL DEFAULT 'Tree Work',
            category VARCHAR(100) NOT NULL,
            option_key VARCHAR(100) NOT NULL,
            label VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_pricing_option (estimate_type, category, option_key)
        )
    ");
}

function seedPricingOptions(PDO $pdo, string $category, array $options): void
{
    $sort = 0;

    foreach ($options as $key => $option) {
        if ($key === '') {
            continue;
        }

        $stmt = $pdo->prepare("
            INSERT INTO estimate_pricing_options 
                (estimate_type, category, option_key, label, price, sort_order)
            VALUES 
                ('Tree Work', ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                sort_order = VALUES(sort_order)
        ");

        $stmt->execute([
            $category,
            $key,
            $option['label'],
            $option['price'],
            $sort++
        ]);
    }
}

function loadPricingOptions(PDO $pdo, string $category, array $defaultOptions): array
{
    seedPricingOptions($pdo, $category, $defaultOptions);

    $stmt = $pdo->prepare("
        SELECT option_key, label, price
        FROM estimate_pricing_options
        WHERE estimate_type = 'Tree Work'
          AND category = ?
          AND is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$category]);

    $options = [];

    if (isset($defaultOptions[''])) {
        $options[''] = $defaultOptions[''];
    }

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $options[$row['option_key']] = [
            'label' => $row['label'],
            'price' => (float) $row['price']
        ];
    }

    return $options;
}

ensurePricingTable($pdo);

$trunkOptions = loadPricingOptions($pdo, 'trunk', $trunkOptions);
$canopyOptions = loadPricingOptions($pdo, 'canopy', $canopyOptions);
$timeOptions = loadPricingOptions($pdo, 'time', $timeOptions);
$truckOptions = loadPricingOptions($pdo, 'truck', $truckOptions);
$obstacleOptions = loadPricingOptions($pdo, 'obstacle', $obstacleOptions);
$additionalServiceOptions = loadPricingOptions($pdo, 'additional_service', $additionalServiceOptions);

$flash = '';
$errors = [];
$alert = '';
$success = '';

/*
|--------------------------------------------------------------------------
| Update Pricing
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_pricing') {
    $prices = $_POST['pricing'] ?? [];

    if (!is_array($prices)) {
        $errors[] = 'Invalid pricing data.';
    } else {
        foreach ($prices as $category => $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $optionKey => $price) {
                $cleanPrice = max(0, (float) $price);

                $stmt = $pdo->prepare("
                    UPDATE estimate_pricing_options
                    SET price = ?
                    WHERE estimate_type = 'Tree Work'
                      AND category = ?
                      AND option_key = ?
                ");

                $stmt->execute([$cleanPrice, $category, $optionKey]);
            }
        }

        header('Location: tree.php?pricing_updated=1');
        exit();
    }
}

if (isset($_GET['pricing_updated'])) {
    $flash = 'Pricing updated successfully.';
}

/*
|--------------------------------------------------------------------------
| PDF Output
|--------------------------------------------------------------------------
*/
if (isset($_GET['pdf'])) {
    $estimateId = (int) $_GET['pdf'];

    if ($estimateId <= 0) {
        die('Invalid estimate ID.');
    }

    $stmt = $pdo->prepare("SELECT * FROM estimates WHERE id = ? AND estimate_type = 'Tree Work'");
    $stmt->execute([$estimateId]);
    $estimate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estimate) {
        die('Estimate not found.');
    }

    $stmtTrees = $pdo->prepare("
        SELECT *
        FROM estimate_tree_items
        WHERE estimate_id = ?
        ORDER BY tree_number ASC, id ASC
    ");
    $stmtTrees->execute([$estimateId]);
    $treeItems = $stmtTrees->fetchAll(PDO::FETCH_ASSOC);

    $stmtServices = $pdo->prepare("
        SELECT *
        FROM estimate_additional_services
        WHERE estimate_id = ?
        ORDER BY id ASC
    ");
    $stmtServices->execute([$estimateId]);
    $serviceItems = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

    require_once '../includes/fpdf/fpdf.php';

    class EstimatePDF extends FPDF
    {
        function Header()
        {
            $imagePath = $_SERVER['DOCUMENT_ROOT'] . '/img/truelove-services.jpeg';

            if (file_exists($imagePath)) {
                $this->Image($imagePath, 10, 10, 40);
            }

            $this->SetFont('Arial', 'B', 18);
            $this->Cell(0, 10, 'Tree Work Estimate', 0, 1, 'R');

            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, 'Generated: ' . date('m/d/Y g:i A'), 0, 1, 'R');
            $this->Ln(10);

            $this->SetDrawColor(180, 180, 180);
            $this->Line(10, 34, 200, 34);
            $this->Ln(8);
        }

        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
        }
    }

    $pdf = new EstimatePDF();
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Customer Information', 0, 1);

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(45, 8, 'Name:', 0, 0);
    $pdf->Cell(0, 8, $estimate['customer_name'], 0, 1);

    $pdf->Cell(45, 8, 'Email:', 0, 0);
    $pdf->Cell(0, 8, $estimate['customer_email'] ?: '-', 0, 1);

    $pdf->Cell(45, 8, 'Phone:', 0, 0);
    $pdf->Cell(0, 8, $estimate['customer_phone'] ?: '-', 0, 1);

    $pdf->Cell(45, 8, 'Property Address:', 0, 0);
    $pdf->MultiCell(0, 8, $estimate['property_address'] ?: '-');
    $pdf->Ln(3);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Tree Breakdown', 0, 1);

    foreach ($treeItems as $i => $tree) {
        $treeTotal =
            (float) $tree['trunk_price'] +
            (float) $tree['canopy_price'] +
            (float) $tree['time_price'] +
            (float) $tree['truck_price'] +
            (float) $tree['obstacle_price'];

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, 'Tree #' . (int) ($tree['tree_number'] ?: ($i + 1)), 0, 1);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(120, 8, 'Description', 1, 0, 'L');
        $pdf->Cell(60, 8, 'Price', 1, 1, 'R');

        $rows = [
            ['Trunk Size - ' . $tree['trunk_label'], $tree['trunk_price']],
            ['Canopy Size - ' . $tree['canopy_label'], $tree['canopy_price']],
            ['Estimated Time - ' . $tree['time_label'], $tree['time_price']],
            ['Truck Access - ' . $tree['truck_label'], $tree['truck_price']],
            ['Obstacles - ' . $tree['obstacle_label'], $tree['obstacle_price']],
        ];

        $pdf->SetFont('Arial', '', 10);
        foreach ($rows as $row) {
            $pdf->Cell(120, 8, $row[0], 1, 0, 'L');
            $pdf->Cell(60, 8, money($row[1]), 1, 1, 'R');
        }

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(120, 8, 'Tree Total', 1, 0, 'L');
        $pdf->Cell(60, 8, money($treeTotal), 1, 1, 'R');
        $pdf->Ln(4);
    }

    if ($serviceItems) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'Additional Services', 0, 1);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(90, 8, 'Service', 1, 0, 'L');
        $pdf->Cell(30, 8, 'Qty', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Unit Price', 1, 0, 'R');
        $pdf->Cell(30, 8, 'Line Total', 1, 1, 'R');

        $pdf->SetFont('Arial', '', 10);
        foreach ($serviceItems as $service) {
            $pdf->Cell(90, 8, $service['service_label'], 1, 0, 'L');
            $pdf->Cell(30, 8, (int) $service['qty'], 1, 0, 'C');
            $pdf->Cell(30, 8, money($service['unit_price']), 1, 0, 'R');
            $pdf->Cell(30, 8, money($service['line_total']), 1, 1, 'R');
        }

        $pdf->Ln(4);
    }

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(120, 10, 'Total Estimate', 1, 0, 'L');
    $pdf->Cell(60, 10, money($estimate['subtotal']), 1, 1, 'R');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Notes', 0, 1);

    $pdf->SetFont('Arial', '', 10);
    $notes = trim((string) $estimate['notes']);
    if ($notes === '') {
        $notes = 'No additional notes.';
    }
    $pdf->MultiCell(0, 6, $notes);
    $pdf->Ln(8);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Acceptance', 0, 1);

    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, "This estimate is based on the selected tree sizes, canopy sizes, estimated labor time, equipment access, obstacles, and any additional services selected. Final pricing may change if site conditions differ from the original inspection.");

    $filename = 'tree_estimate_' . $estimate['id'] . '.pdf';
    $pdf->Output('I', $filename);
    exit();
}

/*
|--------------------------------------------------------------------------
| Email Estimate Link
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'email_estimate') {
    $estimateId = (int) ($_POST['estimate_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM estimates WHERE id = ? AND estimate_type = 'Tree Work'");
    $stmt->execute([$estimateId]);
    $estimate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estimate) {
        $errors[] = 'Estimate not found.';
    } elseif (empty($estimate['customer_email'])) {
        $errors[] = 'Customer email is missing on this estimate.';
    } else {
        $pdfLink = estimatePdfUrl($estimateId);

        $subject = 'Your Estimate from Truelove Services';
        $body = "Hello " . $estimate['customer_name'] . ",\n\n";
        $body .= "Thank you for the opportunity to provide an estimate.\n\n";
        $body .= "You can view your estimate here:\n" . $pdfLink . "\n\n";
        $body .= "Total Estimate: " . money($estimate['subtotal']) . "\n\n";
        $body .= "If you have any questions, please contact us.\n\n";
        $body .= "Truelove Services";

        $headers = "From: attendance@trueloveservices.abelwebdesigns.com\r\n";
        $headers .= "Reply-To: attendance@trueloveservices.abelwebdesigns.com\r\n";

        if (@mail($estimate['customer_email'], $subject, $body, $headers)) {
            $flash = 'Estimate email sent successfully.';
        } else {
            $errors[] = 'Email failed to send. Your server may not be configured for mail().';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Save Estimate
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_estimate') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerEmail = null;
    $customerPhone = null;
    $propertyAddress = null;
    $notes = trim($_POST['notes'] ?? '');

    $trees = buildPostedTrees();
    $services = buildPostedServices();

    if ($customerName === '') {
        $errors[] = 'Customer name is required.';
    }

    if ($customerEmail !== '' && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Customer email is not valid.';
    }

    if (empty($trees)) {
        $errors[] = 'At least one tree is required.';
    }

    $validatedTrees = [];
    $treeSubtotal = 0.00;

    foreach ($trees as $index => $tree) {
        $trunkKey = trim((string) ($tree['trunk_key'] ?? ''));
        $canopyKey = trim((string) ($tree['canopy_key'] ?? ''));
        $timeKey = trim((string) ($tree['time_key'] ?? ''));
        $truckKey = trim((string) ($tree['truck_key'] ?? ''));
        $obstacleKey = trim((string) ($tree['obstacle_key'] ?? ''));

        if (!isset($trunkOptions[$trunkKey]) || $trunkKey === '') {
            $errors[] = 'Please select a trunk size for Tree #' . ($index + 1) . '.';
        }
        if (!isset($canopyOptions[$canopyKey]) || $canopyKey === '') {
            $errors[] = 'Please select a canopy size for Tree #' . ($index + 1) . '.';
        }
        if (!isset($timeOptions[$timeKey]) || $timeKey === '') {
            $errors[] = 'Please select estimated time for Tree #' . ($index + 1) . '.';
        }
        if (!isset($truckOptions[$truckKey]) || $truckKey === '') {
            $errors[] = 'Please select truck access for Tree #' . ($index + 1) . '.';
        }
        if (!isset($obstacleOptions[$obstacleKey]) || $obstacleKey === '') {
            $errors[] = 'Please select obstacles for Tree #' . ($index + 1) . '.';
        }

        if (!$errors) {
            $trunkData = getOptionData($trunkOptions, $trunkKey);
            $canopyData = getOptionData($canopyOptions, $canopyKey);
            $timeData = getOptionData($timeOptions, $timeKey);
            $truckData = getOptionData($truckOptions, $truckKey);
            $obstacleData = getOptionData($obstacleOptions, $obstacleKey);

            $treeTotal =
                (float) $trunkData['price'] +
                (float) $canopyData['price'] +
                (float) $timeData['price'] +
                (float) $truckData['price'] +
                (float) $obstacleData['price'];

            $treeSubtotal += $treeTotal;

            $validatedTrees[] = [
                'tree_number' => $index + 1,
                'trunk_key' => $trunkKey,
                'trunk_label' => $trunkData['label'],
                'trunk_price' => $trunkData['price'],
                'canopy_key' => $canopyKey,
                'canopy_label' => $canopyData['label'],
                'canopy_price' => $canopyData['price'],
                'time_key' => $timeKey,
                'time_label' => $timeData['label'],
                'time_price' => $timeData['price'],
                'truck_key' => $truckKey,
                'truck_label' => $truckData['label'],
                'truck_price' => $truckData['price'],
                'obstacle_key' => $obstacleKey,
                'obstacle_label' => $obstacleData['label'],
                'obstacle_price' => $obstacleData['price'],
                'tree_total' => $treeTotal,
            ];
        }
    }

    $validatedServices = [];
    $serviceSubtotal = 0.00;

    foreach ($services as $serviceKey => $serviceRow) {
        if (!isset($additionalServiceOptions[$serviceKey])) {
            continue;
        }

        $enabled = !empty($serviceRow['enabled']);
        if (!$enabled) {
            continue;
        }

        $qty = (int) ($serviceRow['qty'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }

        $label = $additionalServiceOptions[$serviceKey]['label'];
        $unitPrice = (float) $additionalServiceOptions[$serviceKey]['price'];
        $lineTotal = $qty * $unitPrice;

        $serviceSubtotal += $lineTotal;

        $validatedServices[] = [
            'service_key' => $serviceKey,
            'service_label' => $label,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ];
    }

    if (!$errors) {
        $subtotal = $treeSubtotal + $serviceSubtotal;

        $createdBy = (int) ($_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? 0);
        if ($createdBy <= 0) {
            $createdBy = null;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO estimates (
                    estimate_type,
                    customer_name, customer_email, customer_phone, property_address,
                    subtotal, notes, created_by
                ) VALUES (
                    'Tree Work',
                    ?, ?, ?, ?,
                    ?, ?, ?
                )
            ");

            $stmt->execute([
                $customerName,
                $customerEmail !== '' ? $customerEmail : null,
                $customerPhone !== '' ? $customerPhone : null,
                $propertyAddress !== '' ? $propertyAddress : null,
                $subtotal,
                $notes !== '' ? $notes : null,
                $createdBy
            ]);

            $estimateId = (int) $pdo->lastInsertId();

            $stmtTree = $pdo->prepare("
                INSERT INTO estimate_tree_items (
                    estimate_id, tree_number,
                    trunk_key, trunk_label, trunk_price,
                    canopy_key, canopy_label, canopy_price,
                    time_key, time_label, time_price,
                    truck_key, truck_label, truck_price,
                    obstacle_key, obstacle_label, obstacle_price
                ) VALUES (
                    ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?
                )
            ");

            foreach ($validatedTrees as $tree) {
                $stmtTree->execute([
                    $estimateId,
                    $tree['tree_number'],

                    $tree['trunk_key'],
                    $tree['trunk_label'],
                    $tree['trunk_price'],
                    $tree['canopy_key'],
                    $tree['canopy_label'],
                    $tree['canopy_price'],
                    $tree['time_key'],
                    $tree['time_label'],
                    $tree['time_price'],
                    $tree['truck_key'],
                    $tree['truck_label'],
                    $tree['truck_price'],
                    $tree['obstacle_key'],
                    $tree['obstacle_label'],
                    $tree['obstacle_price'],
                ]);
            }

            if (!empty($validatedServices)) {
                $stmtService = $pdo->prepare("
                    INSERT INTO estimate_additional_services (
                        estimate_id,
                        service_key, service_label, qty, unit_price, line_total
                    ) VALUES (
                        ?,
                        ?, ?, ?, ?, ?
                    )
                ");

                foreach ($validatedServices as $service) {
                    $stmtService->execute([
                        $estimateId,
                        $service['service_key'],
                        $service['service_label'],
                        $service['qty'],
                        $service['unit_price'],
                        $service['line_total'],
                    ]);
                }
            }

            $pdo->commit();

            header('Location: tree.php?saved=1&id=' . $estimateId);
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Failed to save estimate: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    $flash = 'Estimate saved successfully.';
}

/*
|--------------------------------------------------------------------------
| Delete Estimate
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_estimate') {
    $estimate_id = (int) ($_POST['estimate_id'] ?? 0);

    if ($estimate_id <= 0) {
        $errors[] = 'Invalid estimate selected.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM estimates WHERE id = ? AND estimate_type = 'Tree Work'");
        $stmt->execute([$estimate_id]);

        header('Location: tree.php?deleted=1');
        exit();
    }
}

if (isset($_GET['deleted'])) {
    $alert = 'Estimate deleted successfully.';
}

/*
|--------------------------------------------------------------------------
| Recent Estimates
|--------------------------------------------------------------------------
*/
$type = 'Tree Work';

$stmt = $pdo->prepare("
    SELECT e.*, emp.name AS created_by_name
    FROM estimates e
    LEFT JOIN employees emp ON emp.id = e.created_by
    WHERE e.estimate_type = ?
    ORDER BY e.id DESC
    LIMIT 15
");
$stmt->execute([$type]);
$recentEstimates = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="container py-4 text-light" data-bs-theme="dark">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="mb-1">Tree Estimates</h1>
        </div>

        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#pricingModal">
            Edit Pricing
        </button>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($alert): ?>
        <div class="alert alert-info"><?= htmlspecialchars($alert) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card bg-dark border-secondary shadow-sm">
                <div class="card-header border-secondary">
                    <h4 class="mb-0">Create New Estimate</h4>
                </div>
                <div class="card-body">
                    <form method="post" id="estimateForm">
                        <input type="hidden" name="action" value="create_estimate">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Customer Name *</label>
                                <input type="text" name="customer_name"
                                    class="form-control bg-dark text-light border-secondary"
                                    value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Estimate Type</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary"
                                    value="Tree Work" disabled>
                            </div>
                        </div>

                        <hr class="border-secondary my-4">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Trees</h5>
                            <button type="button" class="btn btn-outline-light btn-sm" id="addTreeBtn">+ Add Another
                                Tree</button>
                        </div>

                        <div id="treeItemsWrap"></div>

                        <template id="treeTemplate">
                            <div class="card bg-dark border-secondary mb-3 tree-item">
                                <div
                                    class="card-header border-secondary d-flex justify-content-between align-items-center">
                                    <strong class="tree-title">Tree #1</strong>
                                    <button type="button"
                                        class="btn btn-outline-danger btn-sm remove-tree-btn">Remove</button>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Trunk Size *</label>
                                            <select
                                                class="form-select bg-dark text-light border-secondary tree-input trunk-select"
                                                data-field="trunk_key" required>
                                                <?php foreach ($trunkOptions as $key => $opt): ?>
                                                    <option value="<?= htmlspecialchars($key) ?>"
                                                        data-price="<?= htmlspecialchars($opt['price']) ?>">
                                                        <?= htmlspecialchars($opt['label']) ?>    <?= $key !== '' ? ' - ' . money($opt['price']) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Canopy Size *</label>
                                            <select
                                                class="form-select bg-dark text-light border-secondary tree-input canopy-select"
                                                data-field="canopy_key" required>
                                                <?php foreach ($canopyOptions as $key => $opt): ?>
                                                    <option value="<?= htmlspecialchars($key) ?>"
                                                        data-price="<?= htmlspecialchars($opt['price']) ?>">
                                                        <?= htmlspecialchars($opt['label']) ?>    <?= $key !== '' ? ' - ' . money($opt['price']) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Estimated Time *</label>
                                            <select
                                                class="form-select bg-dark text-light border-secondary tree-input time-select"
                                                data-field="time_key" required>
                                                <?php foreach ($timeOptions as $key => $opt): ?>
                                                    <option value="<?= htmlspecialchars($key) ?>"
                                                        data-price="<?= htmlspecialchars($opt['price']) ?>">
                                                        <?= htmlspecialchars($opt['label']) ?>    <?= $key !== '' ? ' - ' . money($opt['price']) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Truck Access *</label>
                                            <select
                                                class="form-select bg-dark text-light border-secondary tree-input truck-select"
                                                data-field="truck_key" required>
                                                <?php foreach ($truckOptions as $key => $opt): ?>
                                                    <option value="<?= htmlspecialchars($key) ?>"
                                                        data-price="<?= htmlspecialchars($opt['price']) ?>">
                                                        <?= htmlspecialchars($opt['label']) ?>    <?= $key !== '' ? ' - ' . money($opt['price']) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Obstacles *</label>
                                            <select
                                                class="form-select bg-dark text-light border-secondary tree-input obstacle-select"
                                                data-field="obstacle_key" required>
                                                <?php foreach ($obstacleOptions as $key => $opt): ?>
                                                    <option value="<?= htmlspecialchars($key) ?>"
                                                        data-price="<?= htmlspecialchars($opt['price']) ?>">
                                                        <?= htmlspecialchars($opt['label']) ?>    <?= $key !== '' ? ' - ' . money($opt['price']) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mt-3 small text-light">
                                        Tree Total:
                                        <span class="fw-bold text-success tree-total">$0.00</span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <hr class="border-secondary my-4">

                        <h5 class="mb-3">Additional Services</h5>

                        <div class="card bg-dark border-secondary mb-3">
                            <div class="card-body">
                                <?php foreach ($additionalServiceOptions as $serviceKey => $service): ?>
                                    <?php
                                    $postedService = $_POST['services'][$serviceKey] ?? [];
                                    $postedEnabled = !empty($postedService['enabled']);
                                    $postedQty = (int) ($postedService['qty'] ?? 1);
                                    if ($postedQty < 1)
                                        $postedQty = 1;
                                    ?>
                                    <div class="row g-2 align-items-center mb-3 service-row">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input service-enabled" type="checkbox"
                                                    name="services[<?= htmlspecialchars($serviceKey) ?>][enabled]" value="1"
                                                    id="service_<?= htmlspecialchars($serviceKey) ?>"
                                                    data-unit-price="<?= htmlspecialchars($service['price']) ?>"
                                                    <?= $postedEnabled ? 'checked' : '' ?>>
                                                <label class="form-check-label"
                                                    for="service_<?= htmlspecialchars($serviceKey) ?>">
                                                    <?= htmlspecialchars($service['label']) ?> -
                                                    <?= money($service['price']) ?> each
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Qty</label>
                                            <input type="number" min="1"
                                                class="form-control bg-dark text-light border-secondary service-qty"
                                                name="services[<?= htmlspecialchars($serviceKey) ?>][qty]"
                                                value="<?= $postedQty ?>">
                                        </div>
                                        <div class="col-md-3 text-md-end">
                                            <div class="small text-light">Line Total</div>
                                            <div class="fw-bold text-success service-line-total">$0.00</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="4"
                                class="form-control bg-dark text-light border-secondary"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>

                        <hr class="border-secondary my-4">

                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <div>
                                <div class="small text-light">Calculated Total</div>
                                <div class="fs-3 fw-bold text-success" id="estimateTotal">$0.00</div>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg">
                                Save Estimate
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-dark border-secondary shadow-sm">
                <div class="card-header border-secondary">
                    <h4 class="mb-0">Pricing Preview</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>All Trees Total</span>
                        <span id="previewTrees">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Additional Services</span>
                        <span id="previewServices">$0.00</span>
                    </div>

                    <hr class="border-secondary">

                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Total</span>
                        <span class="text-success" id="previewTotal">$0.00</span>
                    </div>

                    <div class="small text-light mt-3">
                        This total is based on all selected tree pricing, obstacles, and additional services.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card bg-dark border-secondary shadow-sm mt-4">
        <div class="card-header border-secondary">
            <h4 class="mb-0">Recent Estimates</h4>
        </div>
        <div class="card-body">
            <?php if (!$recentEstimates): ?>
                <div class="alert alert-info mb-0">No estimates have been created yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>Address</th>
                                <th>Total</th>
                                <th>Created</th>
                                <th style="min-width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEstimates as $row): ?>
                                <tr>
                                    <td><?= (int) $row['id'] ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['customer_name']) ?></div>
                                        <?php if (!empty($row['customer_email'])): ?>
                                            <div class="small"><?= htmlspecialchars($row['customer_email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['property_address'] ?: '-') ?></td>
                                    <td class="fw-bold text-success"><?= money($row['subtotal']) ?></td>
                                    <td>
                                        <?= !empty($row['created_at']) ? date('m/d/Y g:i A', strtotime($row['created_at'])) : '-' ?>
                                        <?php if (!empty($row['created_by_name'])): ?>
                                            <div class="small">by <?= htmlspecialchars($row['created_by_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="tree.php?pdf=<?= (int) $row['id'] ?>" target="_blank"
                                                class="btn btn-outline-light btn-sm">
                                                View PDF
                                            </a>

                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="email_estimate">
                                                <input type="hidden" name="estimate_id" value="<?= (int) $row['id'] ?>">
                                                <button type="submit" class="btn btn-outline-success btn-sm">
                                                    Email Customer
                                                </button>
                                            </form>

                                            <form method="post"
                                                onsubmit="return confirm('Are you sure you want to delete this estimate?');">
                                                <input type="hidden" name="action" value="delete_estimate">
                                                <input type="hidden" name="estimate_id" value="<?= (int) $row['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    Delete Estimate
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Style For Modal -->
<!-- Style For Modal -->
<style>
    #pricingModal .modal-dialog {
        max-height: 92vh;
    }

    #pricingModal .modal-content {
        max-height: 92vh;
    }

    #pricingModal .modal-body {
        overflow-y: auto;
        max-height: calc(92vh - 140px);
        padding-right: 10px;
    }

    /* Accordion styling */
    #pricingModal .accordion-button {
        background-color: #212529;
        color: #fff;
        box-shadow: none;
    }

    #pricingModal .accordion-button:not(.collapsed) {
        background-color: #1a1e21;
        color: #fff;
    }

    #pricingModal .accordion-item {
        background-color: #212529;
    }
</style>

<div class="modal fade" id="pricingModal" tabindex="-1" aria-labelledby="pricingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <form method="post">
                <input type="hidden" name="action" value="update_pricing">

                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Edit Tree Estimate Pricing</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <?php
                    $pricingGroups = [
                        'trunk' => ['title' => 'Trunk Sizes', 'options' => $trunkOptions],
                        'canopy' => ['title' => 'Canopy Sizes', 'options' => $canopyOptions],
                        'time' => ['title' => 'Estimated Time', 'options' => $timeOptions],
                        'truck' => ['title' => 'Truck Access', 'options' => $truckOptions],
                        'obstacle' => ['title' => 'Obstacles', 'options' => $obstacleOptions],
                        'additional_service' => ['title' => 'Additional Services', 'options' => $additionalServiceOptions],
                    ];
                    ?>

                    <div class="alert alert-info">
                        Updating these prices changes future estimates only. Existing saved estimates keep their
                        original pricing.
                    </div>

                    <div class="accordion" id="pricingAccordion">
                        <?php $i = 0;
                        foreach ($pricingGroups as $category => $group): ?>
                            <?php
                            $collapseId = 'collapse_' . $category;
                            $headingId = 'heading_' . $category;
                            $isFirst = $i === 0;
                            ?>

                            <div class="accordion-item border-secondary mb-2">
                                <h2 class="accordion-header" id="<?= $headingId ?>">
                                    <button class="accordion-button <?= !$isFirst ? 'collapsed' : '' ?>" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                                        <strong><?= htmlspecialchars($group['title']) ?></strong>
                                    </button>
                                </h2>

                                <div id="<?= $collapseId ?>"
                                    class="accordion-collapse collapse <?= $isFirst ? 'show' : '' ?>"
                                    data-bs-parent="#pricingAccordion">

                                    <div class="accordion-body">
                                        <div class="row g-3">
                                            <?php foreach ($group['options'] as $key => $option): ?>
                                                <?php if ($key === '')
                                                    continue; ?>

                                                <div class="col-md-6">
                                                    <label class="form-label text-light">
                                                        <?= htmlspecialchars($option['label']) ?>
                                                    </label>

                                                    <div class="input-group">
                                                        <span
                                                            class="input-group-text bg-dark text-light border-secondary">$</span>
                                                        <input type="number" step="0.01" min="0"
                                                            name="pricing[<?= $category ?>][<?= $key ?>]"
                                                            value="<?= number_format((float) $option['price'], 2, '.', '') ?>"
                                                            class="form-control bg-dark text-light border-secondary">
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <?php $i++; endforeach; ?>
                    </div>

                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Pricing</button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
    function formatMoney(amount) {
        return '$' + Number(amount || 0).toFixed(2);
    }

    const treeWrap = document.getElementById('treeItemsWrap');
    const treeTemplate = document.getElementById('treeTemplate');
    const addTreeBtn = document.getElementById('addTreeBtn');

    function getSelectedPrice(selectEl) {
        if (!selectEl) return 0;
        const option = selectEl.options[selectEl.selectedIndex];
        return parseFloat(option?.getAttribute('data-price') || '0');
    }

    function updateTreeNames() {
        const trees = treeWrap.querySelectorAll('.tree-item');

        trees.forEach((treeCard, index) => {
            treeCard.querySelector('.tree-title').textContent = 'Tree #' + (index + 1);

            treeCard.querySelectorAll('.tree-input').forEach((input) => {
                const field = input.getAttribute('data-field');
                input.name = `trees[${index}][${field}]`;
            });

            const removeBtn = treeCard.querySelector('.remove-tree-btn');
            removeBtn.style.display = trees.length > 1 ? '' : 'none';
        });
    }

    function updateTreeCardTotal(treeCard) {
        const trunk = getSelectedPrice(treeCard.querySelector('.trunk-select'));
        const canopy = getSelectedPrice(treeCard.querySelector('.canopy-select'));
        const time = getSelectedPrice(treeCard.querySelector('.time-select'));
        const truck = getSelectedPrice(treeCard.querySelector('.truck-select'));
        const obstacle = getSelectedPrice(treeCard.querySelector('.obstacle-select'));

        const total = trunk + canopy + time + truck + obstacle;
        treeCard.querySelector('.tree-total').textContent = formatMoney(total);

        return total;
    }

    function updateServiceTotals() {
        let serviceTotal = 0;

        document.querySelectorAll('.service-row').forEach((row) => {
            const enabled = row.querySelector('.service-enabled').checked;
            const qtyInput = row.querySelector('.service-qty');
            const qty = Math.max(1, parseInt(qtyInput.value || '1', 10));
            qtyInput.value = qty;

            const unitPrice = parseFloat(row.querySelector('.service-enabled').getAttribute('data-unit-price') || '0');
            const lineTotal = enabled ? (qty * unitPrice) : 0;

            row.querySelector('.service-line-total').textContent = formatMoney(lineTotal);
            serviceTotal += lineTotal;
        });

        return serviceTotal;
    }

    function updateEstimateTotal() {
        let treeTotal = 0;
        treeWrap.querySelectorAll('.tree-item').forEach((treeCard) => {
            treeTotal += updateTreeCardTotal(treeCard);
        });

        const serviceTotal = updateServiceTotals();
        const grandTotal = treeTotal + serviceTotal;

        document.getElementById('previewTrees').textContent = formatMoney(treeTotal);
        document.getElementById('previewServices').textContent = formatMoney(serviceTotal);
        document.getElementById('previewTotal').textContent = formatMoney(grandTotal);
        document.getElementById('estimateTotal').textContent = formatMoney(grandTotal);
    }

    function addTree(defaults = {}) {
        const fragment = treeTemplate.content.cloneNode(true);
        const treeCard = fragment.querySelector('.tree-item');

        treeCard.querySelectorAll('.tree-input').forEach((input) => {
            const field = input.getAttribute('data-field');
            if (defaults[field] !== undefined) {
                input.value = defaults[field];
            }

            input.addEventListener('change', updateEstimateTotal);
        });

        treeCard.querySelector('.remove-tree-btn').addEventListener('click', function () {
            treeCard.remove();
            updateTreeNames();
            updateEstimateTotal();
        });

        treeWrap.appendChild(fragment);
        updateTreeNames();
        updateEstimateTotal();
    }

    addTreeBtn.addEventListener('click', function () {
        addTree();
    });

    document.querySelectorAll('.service-enabled, .service-qty').forEach((el) => {
        el.addEventListener('change', updateEstimateTotal);
        el.addEventListener('input', updateEstimateTotal);
    });

    <?php
    $postedTrees = buildPostedTrees();
    if (!empty($postedTrees)):
        ?>
        treeWrap.innerHTML = '';
        <?php foreach ($postedTrees as $tree): ?>
            addTree({
                trunk_key: <?= json_encode($tree['trunk_key'] ?? '') ?>,
                canopy_key: <?= json_encode($tree['canopy_key'] ?? '') ?>,
                time_key: <?= json_encode($tree['time_key'] ?? '') ?>,
                truck_key: <?= json_encode($tree['truck_key'] ?? '') ?>,
                obstacle_key: <?= json_encode($tree['obstacle_key'] ?? '') ?>
            });
        <?php endforeach; ?>
    <?php else: ?>
        addTree();
    <?php endif; ?>

    updateEstimateTotal();
</script>

<?php require_once '../includes/footer.php'; ?>