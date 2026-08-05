<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

requireLogin();
$backgroundColor = $_COOKIE['bg_color'] ?? '#000000';
if (function_exists('getUserRole') && getUserRole() !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

date_default_timezone_set('America/Indiana/Indianapolis');

$selectedDate = $_GET['date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    $selectedDate = $_POST['date'] ?? $selectedDate;

    $stmt = $pdo->prepare("DELETE FROM attendance_text_logs WHERE id = ?");
    $stmt->execute([$deleteId]);

    header('Location: ?date=' . urlencode($selectedDate));
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        atl.*,
        e.name AS employee_name
    FROM attendance_text_logs atl
    LEFT JOIN employees e ON e.id = atl.employee_id
    WHERE atl.attendance_date = ?
    ORDER BY atl.created_at DESC
");
$stmt->execute([$selectedDate]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: format phone for display and return a clickable tel: link
function format_phone_link($phone)
{
    $digits = preg_replace('/\D+/', '', (string)$phone);

    if (strlen($digits) === 10) {
        $formatted = substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 4);
    } elseif (strlen($digits) === 11 && $digits[0] === '1') {
        // US number with leading '1'
        $formatted = substr($digits, 0, 1) . ' ' . substr($digits, 1, 3) . '-' . substr($digits, 4, 3) . '-' . substr($digits, 7, 4);
    } elseif ($digits === '') {
        return '';
    } else {
        // Unknown format: show as-is
        $formatted = $phone;
    }

    $tel = 'tel:' . $digits;
    return '<a href="' . htmlspecialchars($tel) . '">' . htmlspecialchars($formatted) . '</a>';
}
?>

<div class="container py-4 text-light" data-bs-theme="dark">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="mb-1">Text Attendance</h1>
            <p class="mb-0 text-light">
                View attendance messages received from Go High Level.
            </p>
        </div>

        <form method="GET" class="d-flex gap-2">
            <input 
                type="date" 
                name="date" 
                class="form-control bg-dark text-light border-secondary"
                value="<?= htmlspecialchars($selectedDate) ?>"
            >

            <button class="btn btn-primary">
                Filter
            </button>
        </form>
    </div>

    <div class="card bg-dark border-secondary text-light shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">
                Messages for <?= htmlspecialchars(date('F j, Y', strtotime($selectedDate))) ?>
            </h5>
        </div>

        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="alert alert-secondary mb-0">
                    No text attendance messages found for this date.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Employee / Contact</th>
                                <th>Status</th>
                                <th class="w-50">Original Message</th>
                                <th>Phone</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($logs as $row): ?>
                                <?php
                                    $displayName = $row['employee_name']
                                        ?: ($row['ghl_contact_name'] ?: 'Unknown');

                                    $badgeClass = 'bg-secondary';

                                    if ($row['status'] === 'out') {
                                        $badgeClass = 'bg-danger';
                                    } elseif ($row['status'] === 'in') {
                                        $badgeClass = 'bg-success';
                                    }
                                ?>

                                <tr>
                                    <td>
                                        <?= htmlspecialchars(date('g:i A', strtotime($row['created_at']))) ?>
                                    </td>

                                    <td>
                                        <strong><?= htmlspecialchars($displayName) ?></strong>

                                        <?php if (!$row['employee_id']): ?>
                                            <div class="small text-warning">
                                                Not matched to employee
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars(strtoupper($row['status'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['message']) ?>
                                    </td>

                                    <td>
                                        <?= format_phone_link($row['phone']) ?>
                                    </td>

                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this attendance message?');" class="m-0">
                                            <input type="hidden" name="delete_id" value="<?= htmlspecialchars($row['id']) ?>">
                                            <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>