<?php
require 'includes/db.php';
require 'includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$id = $_GET['user_id'] ?? null;

if (!$id) {
    include 'includes/header.php';
    echo "<div class='alert alert-danger m-4'>User ID not provided.</div>";
    include 'includes/footer.php';
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    include 'includes/header.php';
    echo "<div class='alert alert-danger m-4'>User not found.</div>";
    include 'includes/footer.php';
    exit();
}

include 'includes/header.php';
?>

<div class="container mt-5 text-light" data-bs-theme="dark">

    <!-- HEADER CARD -->
    <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h3 class="mb-1"><?= htmlspecialchars($user['name']) ?></h3>
                <span class="badge bg-secondary">
                    <?= ucwords(str_replace('_', ' ', $user['role'])) ?>
                </span>
            </div>

            <a href="admin_panel.php" class="btn btn-outline-secondary mt-3 mt-sm-0">
                ← Back to Admin Panel
            </a>
        </div>
    </div>

    <!-- DETAILS CARD -->
    <div class="card">
        <div class="card-body">

            <h5 class="mb-4">Employee Details</h5>

            <div class="row g-3">

                <?php
                function field($label, $value) {
                    $value = $value ?: '<span class="text-muted">Not provided</span>';
                    echo "
                    <div class='col-md-6'>
                        <div class='p-3 border rounded bg-body'>
                            <div class='text-muted small mb-1'>$label</div>
                            <div class='fw-semibold'>$value</div>
                        </div>
                    </div>";
                }

                field('Name', htmlspecialchars($user['name']));
                field('PIN', htmlspecialchars($user['pin']));
                field('Role', ucwords(str_replace('_',' ',$user['role'])));
                field('Email', htmlspecialchars($user['email']));
                field('Phone', htmlspecialchars($user['phone']));
                field('Emergency Contact', htmlspecialchars($user['emergency_contact']));
                ?>

                <!-- Address (full width) -->
                <div class="col-12">
                    <div class="p-3 border rounded bg-body">
                        <div class="text-muted small mb-1">Address</div>
                        <div class="fw-semibold">
                            <?= $user['address']
                                ? nl2br(htmlspecialchars($user['address']))
                                : '<span class="text-muted">Not provided</span>' ?>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
