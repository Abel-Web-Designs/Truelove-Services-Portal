<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header('Location: ../dashboard.php');
    exit();
}

function isApproverBlacklisted($userId)
{
    // Add the user_id(s) that should be blocked from approve/deny actions here.
    $blacklistedUsers = array(28);
    return in_array($userId, $blacklistedUsers, true);
}

$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$canApproveDeny = !isApproverBlacklisted($currentUserId);

date_default_timezone_set('America/Indiana/Indianapolis');

$TEXTBELT_KEY = getenv('TEXTBELT_API_KEY') ?: '089fcbbf8e498369811562800ba94222306a5f1fWQuAT5KRh6gNW5Y7jbaRjZCVH';
$alertMsg = '';
$alertClass = 'info';

function normalizePhone($phone)
{
    return preg_replace('/\D+/', '', $phone);
}

function getBadgeClass($status)
{
    if ($status === 'approved') {
        return 'bg-success';
    }
    if ($status === 'denied') {
        return 'bg-danger';
    }
    if ($status === 'pending') {
        return 'bg-warning text-dark';
    }
    if ($status === 'partial') {
        return 'bg-info text-dark';
    }
    return 'bg-secondary';
}

function buildDateRange($startDate, $endDate)
{
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    $end = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!$start || !$end || $end <= $start) {
        return [];
    }

    $dates = [];
    while ($start < $end) {
        $dates[] = $start->format('Y-m-d');
        $start->modify('+1 day');
    }

    return $dates;
}

function fetchRequestDates($pdo, $requestId)
{
    $stmt = $pdo->prepare('SELECT * FROM time_off_request_dates WHERE request_id = ? ORDER BY off_date ASC');
    $stmt->execute(array($requestId));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ensureRequestDates($pdo, $requestId, $startDate, $endDate)
{
    $dates = fetchRequestDates($pdo, $requestId);
    if ($dates) {
        return $dates;
    }

    $offDates = buildDateRange($startDate, $endDate);
    if (!$offDates) {
        return [];
    }

    $insert = $pdo->prepare('INSERT INTO time_off_request_dates (request_id, off_date, status) VALUES (?, ?, ?)');
    foreach ($offDates as $offDate) {
        $insert->execute([$requestId, $offDate, 'pending']);
    }

    return fetchRequestDates($pdo, $requestId);
}

function calculateOverallStatus($dateRows)
{
    $statuses = array_column($dateRows, 'status');
    if (count($statuses) === 0) {
        return 'pending';
    }

    $unique = array_unique($statuses);
    if (count($unique) === 1) {
        return $unique[0] === 'pending' ? 'pending' : $unique[0];
    }

    return 'partial';
}

function sendTextbeltNotification($phone, $message, $key)
{
    $phone = normalizePhone($phone);
    if ($phone === '') {
        return false;
    }

    $payload = http_build_query([
        'phone' => $phone,
        'message' => $message,
        'key' => $key,
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => $payload,
            'timeout' => 10,
        ],
    ]);

    $response = @file_get_contents('https://textbelt.com/text', false, $context);
    return $response !== false;
}

function formatApprovedDatesMessage($approvedDates)
{
    if (!$approvedDates) {
        return 'None';
    }

    $formatted = array_map(function ($date) {
        return date('m/d/Y', strtotime($date));
    }, $approvedDates);
    return implode(', ', $formatted);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['request_id']) && !empty($_POST['task'])) {
    $requestId = (int)$_POST['request_id'];
    $task = $_POST['task'];

    $stmt = $pdo->prepare('SELECT r.*, e.name, e.phone FROM time_off_requests r JOIN employees e ON r.employee_id = e.id WHERE r.id = ?');
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        $previousStatus = $request['status'];
        $newStatus = $previousStatus;
        $approvedDates = [];
        if (!empty($_POST['approved_dates']) && is_array($_POST['approved_dates'])) {
            foreach ($_POST['approved_dates'] as $date) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $approvedDates[] = $date;
                }
            }
            $approvedDates = array_unique($approvedDates);
        }

        if ($task === 'save_changes') {
            $startDate = trim(isset($_POST['start_date']) ? $_POST['start_date'] : '');
            $endDate = trim(isset($_POST['end_date']) ? $_POST['end_date'] : '');
            $reason = trim(isset($_POST['reason']) ? $_POST['reason'] : '');

            if ($startDate === '' || $endDate === '' || $reason === '') {
                $alertMsg = 'Start date, return date, and reason are required.';
                $alertClass = 'warning';
            } else {
                $start = DateTime::createFromFormat('Y-m-d', $startDate);
                $end = DateTime::createFromFormat('Y-m-d', $endDate);

                if (!$start || !$end || $end <= $start) {
                    $alertMsg = 'Return to work date must be after the start date.';
                    $alertClass = 'warning';
                } else {
                    $update = $pdo->prepare('UPDATE time_off_requests SET start_date = ?, end_date = ?, reason = ? WHERE id = ?');
                    $update->execute([$startDate, $endDate, $reason, $requestId]);

                    $delete = $pdo->prepare('DELETE FROM time_off_request_dates WHERE request_id = ?');
                    $delete->execute([$requestId]);

                    $offDates = buildDateRange($startDate, $endDate);
                    $insert = $pdo->prepare('INSERT INTO time_off_request_dates (request_id, off_date, status) VALUES (?, ?, ?)');
                    foreach ($offDates as $offDate) {
                        $insert->execute([$requestId, $offDate, 'pending']);
                    }

                    $dateRows = fetchRequestDates($pdo, $requestId);
                    $newStatus = calculateOverallStatus($dateRows);
                    $statusUpdate = $pdo->prepare('UPDATE time_off_requests SET status = ? WHERE id = ?');
                    $statusUpdate->execute([$newStatus, $requestId]);

                    $request['start_date'] = $startDate;
                    $request['end_date'] = $endDate;
                    $request['reason'] = $reason;
                    $request['status'] = $newStatus;

                    $alertClass = 'success';
                    $alertMsg = 'Request updated successfully.';
                }
            }
        }

        if (in_array($task, ['approve_selected_dates', 'deny_selected_dates'], true)) {
            if (!$canApproveDeny) {
                $alertClass = 'warning';
                $alertMsg = 'You are not allowed to approve or deny time off dates.';
            } else {
                $dateRows = ensureRequestDates($pdo, $requestId, $request['start_date'], $request['end_date']);
                $update = $pdo->prepare('UPDATE time_off_request_dates SET status = ? WHERE request_id = ? AND off_date = ?');
                foreach ($dateRows as $dateRow) {
                    $offDate = $dateRow['off_date'];
                    $isSelected = in_array($offDate, $approvedDates, true);
                    if ($task === 'approve_selected_dates') {
                        $status = $isSelected ? 'approved' : 'denied';
                    } else {
                        $status = $isSelected ? 'denied' : 'approved';
                    }
                    $update->execute([$status, $requestId, $offDate]);
                }
                $dateRows = fetchRequestDates($pdo, $requestId);
                $newStatus = calculateOverallStatus($dateRows);
                $statusUpdate = $pdo->prepare('UPDATE time_off_requests SET status = ? WHERE id = ?');
                $statusUpdate->execute([$newStatus, $requestId]);

                $request['status'] = $newStatus;
                $alertClass = 'success';
                $alertMsg = $task === 'approve_selected_dates' ? 'Selected dates were approved.' : 'Selected dates were denied.';
            }
        }

        if (!empty($request['phone']) && isset($newStatus) && $newStatus !== $previousStatus && in_array($newStatus, ['approved', 'denied', 'partial'], true)) {
            $approvedDateRows = array_filter(fetchRequestDates($pdo, $requestId), function ($dateRow) {
                return $dateRow['status'] === 'approved';
            });
            $approvedList = formatApprovedDatesMessage(array_column($approvedDateRows, 'off_date'));
            $statusText = $newStatus === 'partial' ? 'partially approved' : $newStatus;
            $message = sprintf(
                "Hi %s, your time off request from %s to %s has been %s. Approved dates: %s.",
                $request['name'],
                date('m/d/Y', strtotime($request['start_date'])),
                date('m/d/Y', strtotime($request['end_date'])),
                $statusText,
                $approvedList
            );

            if (sendTextbeltNotification($request['phone'], $message, $TEXTBELT_KEY)) {
                $alertMsg .= ' Text notification sent.';
            } else {
                $alertMsg .= ' Unable to send text notification.';
            }
        }
    }
}

$stmt = $pdo->query("SELECT r.*, e.name FROM time_off_requests r JOIN employees e ON r.employee_id = e.id ORDER BY CASE r.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'partial' THEN 2 WHEN 'denied' THEN 3 ELSE 4 END, r.start_date DESC, r.end_date DESC");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h3 class="mb-0 text-light">Manage Time Off Requests</h3>
        <a href="../dashboard.php" class="btn btn-outline-light btn-sm">Back</a>
    </div>

    <?php if ($alertMsg): ?>
        <div class="alert alert-<?= htmlspecialchars($alertClass) ?>">
            <?= htmlspecialchars($alertMsg) ?>
        </div>
    <?php endif; ?>

    <?php if (!$requests): ?>
        <div class="alert alert-info">No time off requests found.</div>
    <?php else: ?>
        <div class="table-responsive d-none d-md-block">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Start Date</th>
                        <th>Return To Work Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $row): ?>
                        <?php $badgeClass = getBadgeClass($row['status']); ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars(date('m/d/Y', strtotime($row['start_date']))) ?></td>
                            <td><?= htmlspecialchars(date('m/d/Y', strtotime($row['end_date']))) ?></td>
                            <td><?= nl2br(htmlspecialchars($row['reason'])) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= ucfirst(htmlspecialchars($row['status'])) ?></span></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#manageModal<?= (int)$row['id'] ?>">Manage</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="d-md-none">
            <?php foreach ($requests as $row): ?>
                <?php $badgeClass = getBadgeClass($row['status']); ?>
                <div class="card shadow-sm mb-3 border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div class="fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
                            <span class="badge <?= $badgeClass ?>"><?= ucfirst(htmlspecialchars($row['status'])) ?></span>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="small text-muted">Start</div>
                                <div><?= htmlspecialchars(date('m/d/Y', strtotime($row['start_date']))) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="small text-muted">Return</div>
                                <div><?= htmlspecialchars(date('m/d/Y', strtotime($row['end_date']))) ?></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="small text-muted mb-1">Reason</div>
                            <div style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($row['reason'])) ?></div>
                        </div>
                        <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#manageModal<?= (int)$row['id'] ?>">Manage</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($requests as $row): ?>
            <?php
            $requestId = (int)$row['id'];
            $dateRows = ensureRequestDates($pdo, $requestId, $row['start_date'], $row['end_date']);
            $approvedDates = array_filter($dateRows, function ($item) {
                return $item['status'] === 'approved';
            });
            $deniedDates = array_filter($dateRows, function ($item) {
                return $item['status'] === 'denied';
            });
            ?>

            <div class="modal fade" id="manageModal<?= $requestId ?>" tabindex="-1" aria-labelledby="manageModalLabel<?= $requestId ?>" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <form id="manageForm<?= $requestId ?>" method="POST">
                            <input type="hidden" name="request_id" value="<?= $requestId ?>">
                            <input type="hidden" name="task" value="save_changes">
                            <div class="modal-header">
                                <h5 class="modal-title" id="manageModalLabel<?= $requestId ?>">Manage Request for <?= htmlspecialchars($row['name']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <span class="badge <?= getBadgeClass($row['status']) ?>"><?= ucfirst(htmlspecialchars($row['status'])) ?></span>
                                </div>
                                <div class="mb-4">
                                    <div class="d-flex flex-wrap gap-3 align-items-center">
                                        <div><strong>Employee:</strong> <?= htmlspecialchars($row['name']) ?></div>
                                        <div><strong>Start:</strong> <?= htmlspecialchars(date('m/d/Y', strtotime($row['start_date']))) ?></div>
                                        <div><strong>Return:</strong> <?= htmlspecialchars(date('m/d/Y', strtotime($row['end_date']))) ?></div>
                                    </div>
                                </div>
                                <div class="row gy-4">
                                    <div class="col-12 col-xl-6">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <h6 class="mb-3">Edit Request</h6>
                                                <div class="mb-3">
                                                    <label class="form-label" for="start_date_<?= $requestId ?>">Start Date</label>
                                                    <input type="date" id="start_date_<?= $requestId ?>" name="start_date" class="form-control" value="<?= htmlspecialchars($row['start_date']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label" for="end_date_<?= $requestId ?>">Return To Work Date</label>
                                                    <input type="date" id="end_date_<?= $requestId ?>" name="end_date" class="form-control" value="<?= htmlspecialchars($row['end_date']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label" for="reason_<?= $requestId ?>">Reason</label>
                                                    <textarea id="reason_<?= $requestId ?>" name="reason" class="form-control" rows="4" required><?= htmlspecialchars($row['reason']) ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-xl-6">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body">
                                                <h6 class="mb-3">Requested Dates</h6>
                                                <p class="small text-muted mb-3">Check dates to approve. Unchecked dates will be denied.</p>
                                                <div class="row row-cols-1 g-2 mb-3">
                                                    <?php foreach ($dateRows as $dateRow): ?>
                                                        <?php $formattedDate = date('m/d/Y', strtotime($dateRow['off_date'])); ?>
                                                        <div class="col">
                                                            <label class="form-check form-check-inline w-100 d-flex align-items-center justify-content-between gap-3 mb-0">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <input class="form-check-input" type="checkbox" name="approved_dates[]" value="<?= htmlspecialchars($dateRow['off_date']) ?>" <?= $dateRow['status'] === 'approved' ? 'checked' : '' ?>>
                                                                    <span class="form-check-label"><?= $formattedDate ?></span>
                                                                </div>
                                                                <span class="badge <?= getBadgeClass($dateRow['status']) ?>"><?= ucfirst($dateRow['status']) ?></span>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="mb-3">
                                                    <div class="small text-muted">Summary</div>
                                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                                        <span class="badge bg-success">Approved <?= count($approvedDates) ?></span>
                                                        <span class="badge bg-danger">Denied <?= count($deniedDates) ?></span>
                                                        <span class="badge bg-secondary">Total <?= count($dateRows) ?></span>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <div class="small text-muted">Approved Dates</div>
                                                    <div><?= $approvedDates ? implode(', ', array_map(function ($item) {
                                                    return date('m/d/Y', strtotime($item['off_date']));
                                                }, $approvedDates)) : '<span class="text-muted">None</span>' ?></div>
                                                </div>
                                                <div>
                                                    <div class="small text-muted">Denied Dates</div>
                                                    <div><?= $deniedDates ? implode(', ', array_map(function ($item) {
                                                    return date('m/d/Y', strtotime($item['off_date']));
                                                }, $deniedDates)) : '<span class="text-muted">None</span>' ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="w-100 d-flex flex-column flex-sm-row gap-2">
                                <?php if ($canApproveDeny): ?>
                                    <button type="submit" name="task" value="approve_selected_dates" class="btn btn-outline-success flex-fill">Approve Selected Dates</button>
                                    <button type="submit" name="task" value="deny_selected_dates" class="btn btn-outline-danger flex-fill">Deny Selected Dates</button>
                                <?php else: ?>
                                    <div class="alert alert-warning w-100 mb-0">You are not authorized to approve or deny time off dates.</div>
                                <?php endif; ?>
                                <button type="submit" name="task" value="save_changes" class="btn btn-primary flex-fill">Save Changes</button>
                            </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
