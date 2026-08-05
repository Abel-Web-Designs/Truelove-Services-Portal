<?php
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/header.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$error = $success = '';

// Send notifications to ONE email (simple + reliable)
$ADMIN_NOTIFY_EMAIL = 'tyler@trueloveservices.com';

// IMPORTANT: set this to the SAME From you used on approve/deny page that works
$MAIL_FROM  = 'no-reply@truelove-lawn-care.abelwebdesigns.com';   // <-- CHANGE THIS to match your approve/deny page
$MAIL_REPLY_FALLBACK = $MAIL_FROM;

// Debug log file in this same directory
$DEBUG_LOG = __DIR__ . '/timeoff_email_debug.log';

function debugLog($path, $line) {
    @file_put_contents($path, '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'] ?? '';
    $endDate   = $_POST['end_date'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    if (!$startDate || !$endDate || !$reason) {
        $error = "All fields are required.";
    } else {

        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO time_off_requests 
            (employee_id, start_date, end_date, reason, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $startDate,
            $endDate,
            $reason
        ]);

        // Get employee name + email for headers/body
        $empStmt = $pdo->prepare("SELECT name, email FROM employees WHERE id = ?");
        $empStmt->execute([$_SESSION['user_id']]);
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC);

        $employeeName  = $employee['name']  ?? ('Employee ID ' . (int)$_SESSION['user_id']);
        $employeeEmail = trim($employee['email'] ?? '');

        // Reply-To: employee if valid, else fallback
        $replyTo = filter_var($employeeEmail, FILTER_VALIDATE_EMAIL)
            ? $employeeEmail
            : $MAIL_REPLY_FALLBACK;

        $subject = "New Time Off Request Submitted";

        $message =
            "A new time off request has been submitted.\n\n" .
            "Employee: {$employeeName}\n" .
            "Employee Email: " . ($employeeEmail ?: 'N/A') . "\n" .
            "Start Date: {$startDate}\n" .
            "Return to Work Date: {$endDate}\n\n" .
            "Reason:\n{$reason}\n\n" .
            "Please log into the admin panel to review.\n";

        // Build headers (match your approve/deny approach: plain text)
        $headers = [];
        $headers[] = "From: {$MAIL_FROM}";
        $headers[] = "Reply-To: {$replyTo}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/plain; charset=UTF-8";

        // Log what we're about to do (proves this block runs)
        debugLog($DEBUG_LOG, "Attempting mail() to={$ADMIN_NOTIFY_EMAIL} from={$MAIL_FROM} replyTo={$replyTo} user_id=" . (int)$_SESSION['user_id']);

        // Use envelope sender -f (helps deliverability on many hosts)
        $sent = @mail($ADMIN_NOTIFY_EMAIL, $subject, $message, implode("\r\n", $headers), "-f {$MAIL_FROM}");

        debugLog($DEBUG_LOG, "mail() returned: " . ($sent ? 'TRUE' : 'FALSE'));

        if ($sent) {
            $success = "Time off request submitted. Notification email sent.";
        } else {
            $success = "Time off request submitted.";
            $error = "Note: Request saved, but email failed to send. Check timeoff_email_debug.log for details.";
        }
    }
}
?>

<h3 class="text-light">Submit Time Off Request</h3>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" class="mt-3">
    <div class="mb-3">
        <label class="form-label text-light">Start Date</label>
        <input type="date" name="start_date" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label text-light">Return to Work Date</label>
        <input type="date" name="end_date" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label text-light">Reason</label>
        <textarea name="reason" class="form-control" rows="3" required></textarea>
    </div>
    <center><p class="text-light">Time off request must be submitted at least 1 week in advance to be exempt from point accumulation. Time off request will be reviewed; however, approval is not guaranteed.</p></center>
    <button type="submit" class="btn btn-success">Submit Request</button>
</form>

<?php include 'includes/footer.php'; ?>