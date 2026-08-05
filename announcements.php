<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$deleteId]);
    header("Location: announcements.php?deleted=1");
    exit();
}

$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();

require 'includes/header.php';
?>

<div class="container mt-5">
    <div class="card p-4">
        <h3>Announcements</h3>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Announcement deleted successfully.</div>
        <?php endif; ?>

        <?php if (empty($announcements)): ?>
            <p>No announcements found.</p>
        <?php else: ?>
            <div class="row g-3 mt-3">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card shadow-sm h-100 d-flex flex-column">
                            <div class="card-body flex-grow-1">
                                <p class="card-text"><?= nl2br(htmlspecialchars($announcement['message'])) ?></p>
                            </div>
                            <div class="card-footer bg-transparent border-0 pt-0 d-flex justify-content-between align-items-center">
                                <small class="text-muted"><?= date('F j, Y, g:i a', strtotime($announcement['created_at'])) ?></small>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this announcement?');" class="mb-0">
                                    <input type="hidden" name="delete_id" value="<?= $announcement['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="admin_panel.php" class="btn btn-link mt-3">Back to Admin Panel</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
