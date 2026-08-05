<?php
// Enable error reporting (optional but helpful for debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';

    if (preg_match('/^\d{4}$/', $pin)) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE pin = ?");
        $stmt->execute([$pin]);
        $user = $stmt->fetch();

        if ($user) {
            // Store user info in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            // Redirect AFTER setting session
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "Invalid PIN.";
        }
    } else {
        $error = "PIN must be 4 digits.";
    }
}

// Now it's safe to include header and output HTML
require 'includes/header.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Truelove Lawn Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card p-4">
                <h4 class="mb-3 text-center">Employee Login</h4>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label for="pin" class="form-label">4-digit PIN</label>
                        <input type="tel" name="pin" class="form-control" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>