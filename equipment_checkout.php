<?php
require 'includes/db.php';
require 'includes/header.php';

date_default_timezone_set('America/Indiana/Indianapolis');

$success = false;
$errors = [];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mowingCrew      = trim($_POST['mowing_crew'] ?? '');
    $truck           = trim($_POST['truck'] ?? '');
    $trailer         = trim($_POST['trailer'] ?? '');
    $date            = $_POST['date'] ?? date('Y-m-d');
    $crew_lead       = isset($_POST['crew_lead']) ? implode(',', $_POST['crew_lead']) : '';
    $crew_members    = isset($_POST['crew_members']) ? implode(',', $_POST['crew_members']) : '';
    $mowers          = isset($_POST['mowers']) ? implode(',', $_POST['mowers']) : '';
    $trimmers        = isset($_POST['trimmers']) ? implode(',', $_POST['trimmers']) : '';
    $blowers         = isset($_POST['blowers']) ? implode(',', $_POST['blowers']) : '';
    $other_equipment = trim($_POST['other_equipment'] ?? '');

    if ($mowingCrew === '') $errors[] = 'Mowing crew is required.';
    if ($truck === '') $errors[] = 'Truck is required.';
    if ($trailer === '') $errors[] = 'Trailer is required.';
    if ($date === '') $errors[] = 'Date is required.';

    if (!$errors) {
        $stmt = $pdo->prepare("
            INSERT INTO equipment_checkout
            (mowing_crew, truck, trailer, date, crew_lead, crew_members, mowers, trimmers, blowers, other_equipment)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $mowingCrew,
            $truck,
            $trailer,
            $date,
            $crew_lead,
            $crew_members,
            $mowers,
            $trimmers,
            $blowers,
            $other_equipment
        ]);

        $success = true;

        // Optional: clear POST values after success
        $_POST = [];
    }
}

// Get employees for checkboxes
$employees = $pdo->query("
    SELECT id, name
    FROM employees
    WHERE role = 'employee'
      AND is_active = 1
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

function checkedArray($name, $value) {
    $values = $_POST[$name] ?? [];
    return in_array($value, $values) ? 'checked' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipment Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #111827;
            color: #f9fafb;
        }

        .page-wrap {
            max-width: 700px;
            margin: 0 auto;
            padding: 12px;
        }

        .checkout-card {
            background: #1f2937;
            border: 1px solid #374151;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
            overflow: hidden;
        }

        .checkout-header {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            border-bottom: 1px solid #374151;
            padding: 18px 18px 14px;
        }

        .checkout-header h3 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            color: #fff;
        }

        .checkout-header p {
            margin: 6px 0 0;
            color: #cbd5e1;
            font-size: .95rem;
        }

        .section-block {
            padding: 16px 18px;
            border-top: 1px solid #374151;
        }

        .section-title {
            font-size: .85rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #93c5fd;
            margin-bottom: 12px;
        }

        .form-label {
            color: #f8fafc;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            background: #111827;
            border: 1px solid #4b5563;
            color: #fff;
            border-radius: 14px;
            min-height: 48px;
        }

        .form-control:focus,
        .form-select:focus {
            background: #111827;
            color: #fff;
            border-color: #60a5fa;
            box-shadow: 0 0 0 .2rem rgba(96, 165, 250, .2);
        }

        .form-control::placeholder {
            color: #9ca3af;
        }

        .chip-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .chip-check {
            position: relative;
        }

        .chip-check input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .chip-check label {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid #4b5563;
            background: #111827;
            color: #f9fafb;
            font-size: .95rem;
            font-weight: 600;
            line-height: 1.2;
            cursor: pointer;
            user-select: none;
            transition: .15s ease;
            min-height: 44px;
        }

        .chip-check input:checked + label {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        .chip-check label:active {
            transform: scale(.98);
        }

        .helper-text {
            color: #9ca3af;
            font-size: .9rem;
            margin-top: 6px;
        }

        .sticky-submit {
            position: sticky;
            bottom: 0;
            background: rgba(17, 24, 39, .92);
            backdrop-filter: blur(10px);
            border-top: 1px solid #374151;
            padding: 14px 18px 18px;
        }

        .btn-submit {
            min-height: 52px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
        }

        .alert {
            border-radius: 14px;
        }
    </style>
</head>
<body>

<div class="page-wrap py-3">
    <div class="checkout-card">

        <div class="checkout-header">
            <h3>Daily Equipment Checkout</h3>
        </div>

        <div class="section-block">
            <?php if ($success): ?>
                <div class="alert alert-success mb-0">
                    Equipment checkout submitted successfully.
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-0">
                    <strong>Please fix the following:</strong>
                    <ul class="mb-0 mt-2 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" novalidate>
            <div class="section-block">
                <div class="section-title">Basic Info</div>

                <div class="mb-3">
                    <label class="form-label">Mowing Crew</label>
                    <input
                        type="text"
                        name="mowing_crew"
                        class="form-control"
                        value="<?= e($_POST['mowing_crew'] ?? '') ?>"
                        placeholder="Example: MC1"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Truck</label>
                    <input
                        type="text"
                        name="truck"
                        class="form-control"
                        value="<?= e($_POST['truck'] ?? '') ?>"
                        placeholder="Example: TK001"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Trailer</label>
                    <input
                        type="text"
                        name="trailer"
                        class="form-control"
                        value="<?= e($_POST['trailer'] ?? '') ?>"
                        placeholder="Example: TR001"
                        required
                    >
                </div>

                <div class="mb-0">
                    <label class="form-label">Date</label>
                    <input
                        type="date"
                        name="date"
                        class="form-control"
                        value="<?= e($_POST['date'] ?? date('Y-m-d')) ?>"
                        required
                    >
                </div>
            </div>

            <div class="section-block">
                <div class="section-title">Crew Lead</div>
                <div class="chip-grid">
                    <?php foreach ($employees as $emp): ?>
                        <div class="chip-check">
                            <input
                                type="checkbox"
                                name="crew_lead[]"
                                value="<?= e($emp['name']) ?>"
                                id="lead<?= (int)$emp['id'] ?>"
                                <?= checkedArray('crew_lead', $emp['name']) ?>
                            >
                            <label for="lead<?= (int)$emp['id'] ?>">
                                <?= e($emp['name']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="section-block">
                <div class="section-title">Crew Members</div>
                <div class="chip-grid">
                    <?php foreach ($employees as $emp): ?>
                        <div class="chip-check">
                            <input
                                type="checkbox"
                                name="crew_members[]"
                                value="<?= e($emp['name']) ?>"
                                id="member<?= (int)$emp['id'] ?>"
                                <?= checkedArray('crew_members', $emp['name']) ?>
                            >
                            <label for="member<?= (int)$emp['id'] ?>">
                                <?= e($emp['name']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="section-block">
                <div class="section-title">Mowers</div>
                <div class="chip-grid">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <div class="chip-check">
                            <input
                                type="checkbox"
                                name="mowers[]"
                                value="<?= $i ?>"
                                id="mower<?= $i ?>"
                                <?= checkedArray('mowers', (string)$i) ?>
                            >
                            <label for="mower<?= $i ?>">#<?= $i ?></label>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="section-block">
                <div class="section-title">Trimmers</div>
                <div class="chip-grid">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <div class="chip-check">
                            <input
                                type="checkbox"
                                name="trimmers[]"
                                value="<?= $i ?>"
                                id="trimmer<?= $i ?>"
                                <?= checkedArray('trimmers', (string)$i) ?>
                            >
                            <label for="trimmer<?= $i ?>">#<?= $i ?></label>
                        </div>
                    <?php endfor; ?>

                    <div class="chip-check">
                        <input
                            type="checkbox"
                            name="trimmers[]"
                            value="No Number"
                            id="trimmerNoNumber1"
                            <?= checkedArray('trimmers', 'No Number') ?>
                        >
                        <label for="trimmerNoNumber1">No Number</label>
                    </div>
                </div>
            </div>

            <div class="section-block">
                <div class="section-title">Blowers</div>
                <div class="chip-grid">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <div class="chip-check">
                            <input
                                type="checkbox"
                                name="blowers[]"
                                value="<?= $i ?>"
                                id="blower<?= $i ?>"
                                <?= checkedArray('blowers', (string)$i) ?>
                            >
                            <label for="blower<?= $i ?>">#<?= $i ?></label>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="section-block">
                <div class="section-title">Other Equipment</div>
                <label class="form-label">Notes / Extra Equipment</label>
                <input
                    type="text"
                    name="other_equipment"
                    class="form-control"
                    value="<?= e($_POST['other_equipment'] ?? '') ?>"
                    placeholder="Backpack sprayer, gas can, hedge trimmer, etc."
                >
            </div>

            <div class="sticky-submit">
                <button type="submit" class="btn btn-primary w-100 btn-submit">
                    Submit Checkout
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>