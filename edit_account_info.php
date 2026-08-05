<?php
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/header.php';

requireLogin();

date_default_timezone_set('America/Indiana/Indianapolis');

$userId = (int)($_SESSION['user_id'] ?? 0);
$success = '';
$error = '';

/* Get employee table columns so this works even if your schema is slightly different */
$columnsStmt = $pdo->query("SHOW COLUMNS FROM employees");
$columns = array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

function hasCol($columns, $name) {
    return in_array($name, $columns, true);
}

$allowedFields = [
    'name' => 'Full Name',
    'pin' => 'Time Clock PIN',
    'email' => 'Email',
    'phone' => 'Phone Number (No Spaces or Dashes)',
    'address' => 'Address',
    'city' => 'City',
    'state' => 'State',
    'zip' => 'ZIP Code',
    'emergency_contact' => 'Emergency Contact (Name & Number)',
    'emergency_contact_name' => 'Emergency Contact Name',
    'emergency_contact_phone' => 'Emergency Contact Phone',
];

$editableFields = array_filter(
    $allowedFields,
    fn($label, $field) => hasCol($columns, $field),
    ARRAY_FILTER_USE_BOTH
);

/* Fetch employee */
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die('<div class="container mt-5 text-light"><div class="alert alert-danger">Employee record not found.</div></div>');
}

/* Save updates */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = [];
    $params = [];

    foreach ($editableFields as $field => $label) {
        $value = trim($_POST[$field] ?? '');

        if ($field === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
            break;
        }

        $updates[] = "$field = ?";
        $params[] = $value;
    }

    /* Optional password change */
    $passwordCol = hasCol($columns, 'password') ? 'password' : (hasCol($columns, 'password_hash') ? 'password_hash' : null);

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$error && $newPassword !== '') {
        if (!$passwordCol) {
            $error = 'Password column was not found in the employees table.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $updates[] = "$passwordCol = ?";
            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
    }

    if (!$error && !empty($updates)) {
        if (hasCol($columns, 'updated_at')) {
            $updates[] = "updated_at = NOW()";
        }

        $params[] = $userId;

        $sql = "UPDATE employees SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $success = 'Your information has been updated.';

        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<div class="container py-4 text-light" data-bs-theme="dark">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h3 mb-1">My Information</h1>
                    <div class="text-light opacity-75">Update your contact information below.</div>
                </div>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">Back</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="card bg-dark border-secondary shadow">
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label">Employee Role</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($employee['role'] ?? 'Employee') ?>" disabled>
                        <div class="form-text text-light opacity-75">Role changes must be made by an admin.</div>
                    </div>

                    <?php foreach ($editableFields as $field => $label): ?>
                        <div class="mb-3">
                            <label for="<?= htmlspecialchars($field) ?>" class="form-label">
                                <?= htmlspecialchars($label) ?>
                            </label>
                            <input
                                type="<?= $field === 'email' ? 'email' : 'text' ?>"
                                class="form-control"
                                id="<?= htmlspecialchars($field) ?>"
                                name="<?= htmlspecialchars($field) ?>"
                                value="<?= htmlspecialchars($employee[$field] ?? '') ?>"
                            >
                        </div>
                    <?php endforeach; ?>

                    <hr class="border-secondary my-4">

                    <h2 class="h5 mb-3">Change Password</h2>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password">
                        <div class="form-text text-light opacity-75">Leave blank if you do not want to change your password.</div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password">
                    </div>

                </div>

                <div class="card-footer bg-black border-secondary d-flex justify-content-end gap-2">
                    <a href="dashboard.php" class="btn btn-outline-light">Cancel</a>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
            </form>

        </div>
    </div>
</div>

<?php if (file_exists('includes/footer.php')) require 'includes/footer.php'; ?>