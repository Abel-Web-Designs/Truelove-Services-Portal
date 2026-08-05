<?php
require '../includes/db.php';
require '../includes/auth.php';

$isAdmin = isLoggedIn() && getUserRole() === 'admin';

$employees = [];
if ($isAdmin) {
    $employees = $pdo->query("
        SELECT id, name
        FROM employees
        WHERE is_active = 1
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function wb($key, $default = '+')
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT value FROM whiteboard_data WHERE `key`=?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    if ($value === false || trim($value) === '') {
        return $default;
    }

    return $value;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Truelove Services Digital Whiteboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f8f9fa;
            color: #111;
        }

        .whiteboard-wrapper {
            max-width: 1500px;
            margin: 0 auto;
            padding: 20px;
        }

        .section-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 14px;
            padding: 18px;
            height: 100%;
            box-shadow: 0 4px 14px rgba(0,0,0,.06);
        }

        .editable {
            display: inline-block;
            min-width: 24px;
            padding: 2px 6px;
            border-radius: 6px;
            transition: .15s ease-in-out;
        }

        .editable:hover {
            background: #e9ecef;
        }

        .editable[contenteditable="true"] {
            background: #fff3cd;
            outline: 2px solid #ffc107;
        }

        .editable.drag-over {
            background: #d1e7dd !important;
            outline: 2px dashed #198754;
        }

        .employee-chip {
            font-size: 1rem;
            padding: 10px 14px;
            cursor: grab;
            user-select: none;
        }

        .employee-chip:active {
            cursor: grabbing;
        }

        .logo-img {
            max-height: 260px;
            object-fit: contain;
        }

        @media (max-width: 768px) {
            .whiteboard-wrapper {
                padding: 12px;
            }

            .section-card {
                margin-bottom: 14px;
            }

            .logo-img {
                max-height: 180px;
            }

            .employee-chip {
                font-size: .95rem;
                padding: 9px 12px;
            }
        }
    </style>
</head>

<body>

<script>
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
</script>

<div class="whiteboard-wrapper">

    <div class="row g-3 align-items-stretch">

        <div class="col-lg-4">
            <div class="section-card">
                <h3 class="text-center"><u>Managers</u></h3>

                <p><b>General Manager -
                    <span class="editable" data-key="gm"><?= htmlspecialchars(wb('gm', 'Tyler')) ?></span>
                </b></p>

                <p><b>Assistant Manager -
                    <span class="editable" data-key="am"><?= htmlspecialchars(wb('am', 'Jacob')) ?></span>
                </b></p>

                <p><b>Sales Rep -
                    <span class="editable" data-key="sales_rep"><?= htmlspecialchars(wb('sales_rep', '+')) ?></span>
                </b></p>

                <div class="row">
                    <div class="col-12 text-center mb-2">
                        <b><u>Crew Leads</u></b><br>
                        <b>Senior Crew Lead -</b> <span class="editable" data-key="senior_crew_lead"><?= htmlspecialchars(wb('senior_crew_lead')) ?></span><br>
                    </div>

                    <div class="col-6">
                        <p>
                            <b>MC1-</b> <span class="editable" data-key="lead_mc1"><?= htmlspecialchars(wb('lead_mc1', 'Ricky')) ?></span><br>
                            <b>MC2-</b> <span class="editable" data-key="lead_mc2"><?= htmlspecialchars(wb('lead_mc2', 'Dylan B, Michael')) ?></span><br>
                            <b>MC3-</b> <span class="editable" data-key="lead_mc3"><?= htmlspecialchars(wb('lead_mc3', 'Ryan, Dylan M')) ?></span><br>
                            <b>LC1-</b> <span class="editable" data-key="lead_lc1"><?= htmlspecialchars(wb('lead_lc1')) ?></span><br>
                        </p>
                    </div>

                    <div class="col-6">
                        <p>
                            <b>TC1-</b> <span class="editable" data-key="lead_tc1"><?= htmlspecialchars(wb('lead_tc1')) ?></span><br>
                            <b>TC2-</b> <span class="editable" data-key="lead_tc2"><?= htmlspecialchars(wb('lead_tc2')) ?></span><br>
                            <b>FC1-</b> <span class="editable" data-key="lead_fc1"><?= htmlspecialchars(wb('lead_fc1')) ?></span><br>
                            <b>TCK1-</b> <span class="editable" data-key="lead_tck1"><?= htmlspecialchars(wb('lead_tck1')) ?></span><br>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 text-center d-flex align-items-center justify-content-center">
            <div class="section-card w-100">
                <a href="https://truelove-lawn-care.abelwebdesigns.com">
                    <img src="https://truelove-lawn-care.abelwebdesigns.com/img/truelove-services.jpeg" class="img-fluid logo-img">
                </a>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="section-card">
                <div class="text-center mb-2"><b><u>Crew Members</u></b></div>

                <div class="row">
                    <div class="col-6">
                        <span class="editable" data-key="crew1"><?= htmlspecialchars(wb('crew1', 'Dylan')) ?></span><br>
                        <span class="editable" data-key="crew2"><?= htmlspecialchars(wb('crew2', 'Michael')) ?></span><br>
                        <span class="editable" data-key="crew5"><?= htmlspecialchars(wb('crew5')) ?></span><br>
                        <span class="editable" data-key="crew6"><?= htmlspecialchars(wb('crew6')) ?></span><br>
                        <span class="editable" data-key="crew7"><?= htmlspecialchars(wb('crew7')) ?></span><br>
                    </div>

                    <div class="col-6">
                        <span class="editable" data-key="crew3"><?= htmlspecialchars(wb('crew3', 'Jordan')) ?></span><br>
                        <span class="editable" data-key="crew4"><?= htmlspecialchars(wb('crew4', 'Ethan')) ?></span><br>
                        <span class="editable" data-key="crew8"><?= htmlspecialchars(wb('crew8')) ?></span><br>
                        <span class="editable" data-key="crew9"><?= htmlspecialchars(wb('crew9')) ?></span><br>
                        <span class="editable" data-key="crew10"><?= htmlspecialchars(wb('crew10')) ?></span><br>
                    </div>
                </div>

                <hr>

                <div class="text-center mb-2"><b><u>Firewood Crew</u></b></div>

                <div class="row">
                    <div class="col-6">
                        <span class="editable" data-key="firecrew1"><?= htmlspecialchars(wb('firecrew1')) ?></span><br>
                        <span class="editable" data-key="firecrew2"><?= htmlspecialchars(wb('firecrew2')) ?></span><br>
                    </div>

                    <div class="col-6">
                        <span class="editable" data-key="firecrew3"><?= htmlspecialchars(wb('firecrew3')) ?></span><br>
                        <span class="editable" data-key="firecrew4"><?= htmlspecialchars(wb('firecrew4')) ?></span><br>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <hr>

    <div class="row g-3 align-items-stretch">

        <div class="col-lg-4">
            <div class="section-card">
                <h3 class="text-center"><u>Crews</u></h3>

                <div class="row">
                    <div class="col-6">
                        <p>MC1 - <span class="editable" data-key="mc1"><?= htmlspecialchars(wb('mc1', 'Ricky')) ?></span></p>
                        <p>MC2 - <span class="editable" data-key="mc2"><?= htmlspecialchars(wb('mc2', 'Dylan B, Michael')) ?></span></p>
                        <p>MC3 - <span class="editable" data-key="mc3"><?= htmlspecialchars(wb('mc3', 'Ryan, Dylan M')) ?></span></p>
                        <p>QC1 - <span class="editable" data-key="qc"><?= htmlspecialchars(wb('qc')) ?></span></p>
                        <p>FLOAT - <span class="editable" data-key="float"><?= htmlspecialchars(wb('float')) ?></span></p>
                    </div>

                    <div class="col-6">
                        <p>LC1 - <span class="editable" data-key="lc1"><?= htmlspecialchars(wb('lc1')) ?></span></p>
                        <p>TC1 - <span class="editable" data-key="tc1"><?= htmlspecialchars(wb('tc1', 'Jacob, Jordan')) ?></span></p>
                        <p>TC2 - <span class="editable" data-key="tc2"><?= htmlspecialchars(wb('tc2')) ?></span></p>
                        <p>FC1 - <span class="editable" data-key="fc1"><?= htmlspecialchars(wb('fc1')) ?></span></p>
                        <p>TCK1 - <span class="editable" data-key="tck1"><?= htmlspecialchars(wb('tck1', 'Tim')) ?></span></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="section-card">
                <h3 class="text-center"><u>Meeting Schedule</u></h3>

                <div class="row">
                    <div class="col-5">
                        <p>Monday:</p>
                        <p>Tuesday:</p>
                        <p>Wednesday:</p>
                        <p>Thursday:</p>
                        <p>Friday:</p>
                    </div>

                    <div class="col-7">
                        <p><span class="editable" data-key="meeting1"><?= htmlspecialchars(wb('meeting1', 'all staff')) ?></span></p>
                        <p><span class="editable" data-key="meeting2"><?= htmlspecialchars(wb('meeting2', 'Mowing Crews')) ?></span></p>
                        <p><span class="editable" data-key="meeting3"><?= htmlspecialchars(wb('meeting3', 'Tree Crews')) ?></span></p>
                        <p><span class="editable" data-key="meeting4"><?= htmlspecialchars(wb('meeting4', 'Landscape Crews')) ?></span></p>
                        <p><span class="editable" data-key="meeting5"><?= htmlspecialchars(wb('meeting5', 'Management Meeting')) ?></span></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="section-card">
                <h3 class="text-center"><u>People Out</u></h3>

                <div class="row">
                    <div class="col-5">
                        <p>Monday:</p>
                        <p>Tuesday:</p>
                        <p>Wednesday:</p>
                        <p>Thursday:</p>
                        <p>Friday:</p>
                    </div>

                    <div class="col-7">
                        <p><span class="editable" data-key="timeoff1"><?= htmlspecialchars(wb('timeoff1')) ?></span></p>
                        <p><span class="editable" data-key="timeoff2"><?= htmlspecialchars(wb('timeoff2')) ?></span></p>
                        <p><span class="editable" data-key="timeoff3"><?= htmlspecialchars(wb('timeoff3')) ?></span></p>
                        <p><span class="editable" data-key="timeoff4"><?= htmlspecialchars(wb('timeoff4')) ?></span></p>
                        <p><span class="editable" data-key="timeoff5"><?= htmlspecialchars(wb('timeoff5')) ?></span></p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php if ($isAdmin): ?>
        <div class="row mt-3">
            <div class="col-12">
                <div class="section-card">
                    <h4 class="text-center mb-3"><u>Active Employees</u></h4>

                    <?php if (empty($employees)): ?>
                        <div class="alert alert-warning mb-0 text-center">
                            No active employees found.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <?php foreach ($employees as $employee): ?>
                                <span
                                    class="badge bg-dark employee-chip"
                                    draggable="true"
                                    data-name="<?= htmlspecialchars(explode(' ', $employee['name'])[0]) ?>"
                                >
                                    <?= htmlspecialchars(explode(' ', $employee['name'])[0]) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <div class="text-center text-muted small mt-3">
                            Drag an employee name into any crew or whiteboard field.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
if (isAdmin) {
    function cleanValue(value) {
        value = value.trim();
        return value === '' ? '+' : value;
    }

    function saveField(el) {
        let key = el.dataset.key;
        let value = cleanValue(el.innerText);

        el.innerText = value;
        el.contentEditable = false;

        if (value === el.dataset.originalValue) {
            return;
        }

        fetch('save_whiteboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                key: key,
                value: value
            })
        })
        .then(res => res.json())
        .then(data => {
            console.log("Saved:", data);
            el.dataset.originalValue = value;
        })
        .catch(err => {
            console.error(err);
            alert('There was an error saving this field.');
        });
    }

    document.querySelectorAll('.editable').forEach(el => {
        el.style.cursor = "pointer";
        el.dataset.originalValue = cleanValue(el.innerText);

        el.addEventListener('click', function () {
            this.contentEditable = true;
            this.focus();

            const range = document.createRange();
            range.selectNodeContents(this);

            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        });

        el.addEventListener('blur', function () {
            saveField(el);
        });

        el.addEventListener('keydown', function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                saveField(el);
            }

            if (e.key === "Escape") {
                e.preventDefault();
                el.innerText = el.dataset.originalValue || '+';
                el.contentEditable = false;
            }
        });

        el.addEventListener('dragover', function (e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });

        el.addEventListener('dragleave', function () {
            this.classList.remove('drag-over');
        });

        el.addEventListener('drop', function (e) {
            e.preventDefault();
            this.classList.remove('drag-over');

            const name = e.dataTransfer.getData('text/plain');
            let current = cleanValue(this.innerText);

            if (!name) {
                return;
            }

            if (current === '+') {
                this.innerText = name;
            } else {
                const existingNames = current.split(',').map(n => n.trim().toLowerCase());

                if (!existingNames.includes(name.toLowerCase())) {
                    this.innerText = current + ', ' + name;
                }
            }

            saveField(this);
        });
    });

    document.querySelectorAll('.employee-chip').forEach(chip => {
        chip.addEventListener('dragstart', function (e) {
            e.dataTransfer.setData('text/plain', this.dataset.name);
        });
    });
}
</script>

<script>
let lastUpdate = 0;

function checkWhiteboardUpdate() {
    fetch('whiteboard_last_update.txt?cache=' + Date.now())
        .then(res => res.text())
        .then(ts => {
            ts = parseInt(ts);

            if (lastUpdate === 0) {
                lastUpdate = ts;
                return;
            }

            if (ts !== lastUpdate) {
                location.reload();
            }
        })
        .catch(err => console.error(err));
}

setInterval(checkWhiteboardUpdate, 5000);
</script>

</body>
</html>