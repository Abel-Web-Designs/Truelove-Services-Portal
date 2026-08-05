<?php
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/header.php';

requireLogin();

if (getUserRole() !== 'admin') {
    header('Location: ../dashboard.php');
    exit();
}

date_default_timezone_set('America/Indiana/Indianapolis');

$message = '';
$error = '';

/* -------------------- SAVE CALL -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_call') {
    $applicant_name = trim($_POST['applicant_name'] ?? '');
    $phone_number   = trim($_POST['phone_number'] ?? '');
    $call_date      = trim($_POST['call_date'] ?? '');
    $call_status    = trim($_POST['call_status'] ?? 'no_answer');
    $notes          = trim($_POST['notes'] ?? '');

    $allowedStatuses = [
        'answered',
        'no_answer',
        'left_voicemail',
        'bad_number',
        'not_interested',
        'hired',
        'follow_up'
    ];

    if ($applicant_name === '' || $phone_number === '' || $call_date === '') {
        $error = 'Name, phone number, and call date are required.';
    } elseif (!in_array($call_status, $allowedStatuses, true)) {
        $error = 'Invalid call status.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO applicant_calls 
                (applicant_name, phone_number, call_date, call_status, notes, created_by)
            VALUES 
                (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $applicant_name,
            $phone_number,
            date('Y-m-d H:i:s', strtotime($call_date)),
            $call_status,
            $notes,
            $_SESSION['user_id'] ?? null
        ]);

        $message = 'Applicant call was saved successfully.';
    }
}

/* -------------------- DELETE CALL -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_call') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM applicant_calls WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Call record deleted.';
    }
}

/* -------------------- FILTERS -------------------- */
$q      = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$start  = trim($_GET['start'] ?? '');
$end    = trim($_GET['end'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(applicant_name LIKE ? OR phone_number LIKE ? OR notes LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status !== '') {
    $where[] = "call_status = ?";
    $params[] = $status;
}

if ($start !== '') {
    $where[] = "DATE(call_date) >= ?";
    $params[] = $start;
}

if ($end !== '') {
    $where[] = "DATE(call_date) <= ?";
    $params[] = $end;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* -------------------- STATS -------------------- */
$totalCalls = (int)$pdo->query("SELECT COUNT(*) FROM applicant_calls")->fetchColumn();
$answeredCalls = (int)$pdo->query("SELECT COUNT(*) FROM applicant_calls WHERE call_status = 'answered'")->fetchColumn();
$voicemails = (int)$pdo->query("SELECT COUNT(*) FROM applicant_calls WHERE call_status = 'left_voicemail'")->fetchColumn();
$followUps = (int)$pdo->query("SELECT COUNT(*) FROM applicant_calls WHERE call_status = 'follow_up'")->fetchColumn();
$hired = (int)$pdo->query("SELECT COUNT(*) FROM applicant_calls WHERE call_status = 'hired'")->fetchColumn();

$uniqueApplicants = (int)$pdo->query("
    SELECT COUNT(*) FROM (
        SELECT phone_number FROM applicant_calls GROUP BY phone_number
    ) x
")->fetchColumn();

$answerRate = $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 1) : 0;

/* -------------------- SEARCH RESULTS -------------------- */
$stmt = $pdo->prepare("
    SELECT *
    FROM applicant_calls
    $whereSql
    ORDER BY call_date DESC, id DESC
    LIMIT 300
");
$stmt->execute($params);
$calls = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- RECENT REPEAT NUMBERS -------------------- */
$repeatStmt = $pdo->query("
    SELECT phone_number, applicant_name, COUNT(*) AS call_count, MAX(call_date) AS last_called
    FROM applicant_calls
    GROUP BY phone_number
    HAVING COUNT(*) > 1
    ORDER BY last_called DESC
    LIMIT 10
");
$repeatCalls = $repeatStmt->fetchAll(PDO::FETCH_ASSOC);

function statusLabel($status) {
    return match ($status) {
        'answered' => 'Answered',
        'no_answer' => 'No Answer',
        'left_voicemail' => 'Left Voicemail',
        'bad_number' => 'Bad Number',
        'not_interested' => 'Not Interested',
        'hired' => 'Hired',
        'follow_up' => 'Follow Up',
        default => ucfirst(str_replace('_', ' ', $status))
    };
}

function statusBadge($status) {
    return match ($status) {
        'answered' => 'success',
        'no_answer' => 'secondary',
        'left_voicemail' => 'info',
        'bad_number' => 'danger',
        'not_interested' => 'warning',
        'hired' => 'primary',
        'follow_up' => 'light text-dark',
        default => 'secondary'
    };
}
?>

<div class="container-fluid py-4 text-light" data-bs-theme="dark">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="mb-1">Applicant Call Tracker</h1>
            <p class="text-light opacity-75 mb-0">
                Track who you called, when you called, whether they answered, and notes for future searches.
            </p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card bg-dark border-secondary shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-light opacity-75">Total Calls</div>
                    <div class="fs-3 fw-bold"><?= $totalCalls ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-2">
            <div class="card bg-dark border-secondary shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-light opacity-75">Applicants</div>
                    <div class="fs-3 fw-bold"><?= $uniqueApplicants ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-2">
            <div class="card bg-dark border-secondary shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-light opacity-75">Answered</div>
                    <div class="fs-3 fw-bold"><?= $answeredCalls ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-2">
            <div class="card bg-dark border-secondary shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-light opacity-75">Answer Rate</div>
                    <div class="fs-3 fw-bold"><?= $answerRate ?>%</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-2">
            <div class="card bg-dark border-secondary shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-light opacity-75">Follow Ups</div>
                    <div class="fs-3 fw-bold"><?= $followUps ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-2">
            <div class="card bg-dark border-secondary shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-light opacity-75">Hired</div>
                    <div class="fs-3 fw-bold"><?= $hired ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- ADD CALL -->
        <div class="col-lg-4">
            <div class="card bg-dark border-secondary shadow-lg">
                <div class="card-header border-secondary">
                    <h5 class="mb-0">Add Applicant Call</h5>
                </div>

                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_call">

                        <div class="mb-3">
                            <label class="form-label">Applicant Name</label>
                            <input type="text" name="applicant_name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Called Date / Time</label>
                            <input 
                                type="datetime-local" 
                                name="call_date" 
                                class="form-control" 
                                value="<?= date('Y-m-d\TH:i') ?>" 
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Call Status</label>
                            <select name="call_status" class="form-select">
                                <option value="answered">Answered</option>
                                <option value="no_answer" selected>No Answer</option>
                                <option value="left_voicemail">Left Voicemail</option>
                                <option value="bad_number">Bad Number</option>
                                <option value="not_interested">Not Interested</option>
                                <option value="follow_up">Follow Up Needed</option>
                                <option value="hired">Hired</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea 
                                name="notes" 
                                class="form-control" 
                                rows="4" 
                                placeholder="Example: Said he can start Monday, has mowing experience, needs called back..."
                            ></textarea>
                        </div>

                        <button class="btn btn-primary w-100">
                            Save Call
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($repeatCalls): ?>
                <div class="card bg-dark border-secondary shadow-lg mt-4">
                    <div class="card-header border-secondary">
                        <h5 class="mb-0">Recently Called More Than Once</h5>
                    </div>

                    <div class="list-group list-group-flush">
                        <?php foreach ($repeatCalls as $repeat): ?>
                            <div class="list-group-item bg-dark text-light border-secondary">
                                <div class="fw-bold"><?= htmlspecialchars($repeat['applicant_name']) ?></div>
                                <div class="small"><?= htmlspecialchars($repeat['phone_number']) ?></div>
                                <div class="small text-light opacity-75">
                                    <?= (int)$repeat['call_count'] ?> calls —
                                    Last: <?= date('m/d/Y g:i A', strtotime($repeat['last_called'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- SEARCH / RESULTS -->
        <div class="col-lg-8">
            <div class="card bg-dark border-secondary shadow-lg mb-4">
                <div class="card-header border-secondary">
                    <h5 class="mb-0">Search Call History</h5>
                </div>

                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search Name / Number / Notes</label>
                            <input 
                                type="text" 
                                name="q" 
                                class="form-control" 
                                value="<?= htmlspecialchars($q) ?>" 
                                placeholder="Search applicant..."
                            >
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <?php
                                $statuses = [
                                    'answered',
                                    'no_answer',
                                    'left_voicemail',
                                    'bad_number',
                                    'not_interested',
                                    'follow_up',
                                    'hired'
                                ];
                                foreach ($statuses as $s):
                                ?>
                                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>>
                                        <?= statusLabel($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Start</label>
                            <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($start) ?>">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">End</label>
                            <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($end) ?>">
                        </div>

                        <div class="col-md-1 d-flex align-items-end">
                            <button class="btn btn-outline-light w-100">
                                Go
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card bg-dark border-secondary shadow-lg">
                <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Results</h5>
                    <span class="badge bg-secondary"><?= count($calls) ?> shown</span>
                </div>

                <div class="card-body p-0">
                    <?php if (!$calls): ?>
                        <div class="p-4 text-center text-light opacity-75">
                            No applicant calls found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Date Called</th>
                                        <th>Applicant</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($calls as $call): ?>
                                        <tr>
                                            <td style="white-space: nowrap;">
                                                <?= date('m/d/Y', strtotime($call['call_date'])) ?><br>
                                                <span class="small text-light opacity-75">
                                                    <?= date('g:i A', strtotime($call['call_date'])) ?>
                                                </span>
                                            </td>

                                            <td class="fw-semibold">
                                                <?= htmlspecialchars($call['applicant_name']) ?>
                                            </td>

                                            <td>
                                                <a 
                                                    href="tel:<?= htmlspecialchars($call['phone_number']) ?>" 
                                                    class="text-light"
                                                >
                                                    <?= htmlspecialchars($call['phone_number']) ?>
                                                </a>
                                            </td>

                                            <td>
                                                <span class="badge bg-<?= statusBadge($call['call_status']) ?>">
                                                    <?= statusLabel($call['call_status']) ?>
                                                </span>
                                            </td>

                                            <td style="min-width: 250px;">
                                                <?= nl2br(htmlspecialchars($call['notes'] ?? '')) ?>
                                            </td>

                                            <td class="text-end">
                                                <form method="POST" onsubmit="return confirm('Delete this call record?');">
                                                    <input type="hidden" name="action" value="delete_call">
                                                    <input type="hidden" name="id" value="<?= (int)$call['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        Delete
                                                    </button>
                                                </form>
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
    </div>
</div>

<?php require '../includes/footer.php'; ?>