<?php
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/header.php';
requireLogin();

$employee_id = $_SESSION['user_id'];
$message = '';
$playSuccessSound = false;
$playErrorSound = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clock_type'])) {
    $type = $_POST['clock_type'];

    if (in_array($type, ['in', 'out'])) {
        date_default_timezone_set('America/Indiana/Indianapolis');
        $now = date('Y-m-d H:i:s');
        $timeDisplay = date('g:i A');

        $stmt = $pdo->prepare("
            INSERT INTO time_logs (employee_id, clock_type, timestamp)
            VALUES (?, ?, ?)
        ");

        if ($stmt->execute([$employee_id, $type, $now])) {
            $message = "Successfully clocked <strong>" . strtoupper($type) . "</strong> at {$timeDisplay}.";
            $playSuccessSound = true;
        } else {
            $message = "Error clocking " . strtoupper($type) . ".";
            $playErrorSound = true;
        }
    }
}
?>

<div class="container mt-5 text-light" data-bs-theme="dark">

    <div class="card shadow-lg mx-auto" style="max-width: 420px;">
        <div class="card-header bg-dark text-center">
            <h4 class="mb-0">Clock In / Out</h4>
        </div>

        <div class="card-body text-center">

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="d-grid gap-3">

                <button
                    type="submit"
                    name="clock_type"
                    value="in"
                    class="btn btn-success btn-lg"
                >
                    ⏱️ Clock In
                </button>

                <button
                    type="submit"
                    name="clock_type"
                    value="out"
                    class="btn btn-danger btn-lg"
                >
                    🛑 Clock Out
                </button>

            </form>

            <a href="dashboard.php"
               class="btn btn-outline-secondary btn-sm mt-4">
                ← Back to Dashboard
            </a>

        </div>
    </div>

</div>

<audio id="successSound" src="assets/sounds/success.mp3" preload="auto"></audio>
<audio id="errorSound" src="assets/sounds/error.mp3" preload="auto"></audio>

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($playSuccessSound): ?>
        document.getElementById('successSound').play().catch(function(e) {
            console.log('Success sound blocked:', e);
        });
    <?php endif; ?>

    <?php if ($playErrorSound): ?>
        document.getElementById('errorSound').play().catch(function(e) {
            console.log('Error sound blocked:', e);
        });
    <?php endif; ?>
});
</script>

<?php include 'includes/footer2.php'; ?>