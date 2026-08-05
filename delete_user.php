<?php
require 'includes/db.php';
require 'includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);

    // Prevent self-deletion (optional safeguard)
    if ($userId === $_SESSION['user_id']) {
        header('Location: admin_panel.php?error=self');
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$userId]);

    header('Location: admin_panel.php?deleted=1');
    exit();
}
