<?php
require 'includes/db.php';
require 'includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$id = $_GET['user_id'] ?? null;

if (!$id) {
    echo "User ID not provided.";
    exit();
}

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    echo "User not found.";
    exit();
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $pin = trim($_POST['pin']);
    $role = $_POST['role'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $emergency_contact = $_POST['emergency_contact'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $update = $pdo->prepare("UPDATE employees SET name = ?, pin = ?, role = ?, email = ?, phone = ?, address = ?, emergency_contact = ?, is_active = ? WHERE id = ?");
    $update->execute([$name, $pin, $role, $email, $phone, $address, $emergency_contact, $is_active, $id]);

    header("Location: admin_panel.php");
    exit();
}
?>

<?php include 'includes/header.php'; ?>

<h3 class="text-light">Edit User</h3>

<form method="POST">
    <div class="mb-3">
        <label class="text-light">Name</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
    </div>
    <div class="mb-3">
        <label class="text-light">PIN</label>
        <input type="text" name="pin" class="form-control" value="<?= htmlspecialchars($user['pin']) ?>" pattern="\d{4}" maxlength="4" required>
    </div>
    <div class="mb-3">
        <label class="text-light">Role</label>
        <select name="role" class="form-select">
            <option value="employee" <?= $user['role'] === 'employee' ? 'selected' : '' ?>>Employee</option>
            <option value="truck_driver" <?= $user['role'] === 'truck_driver' ? 'selected' : '' ?>>Truck Driver</option>
            <option value="work_phone" <?= $user['role'] === 'work_phone' ? 'selected' : '' ?>>Work Phone</option>
            <option value="mechanic" <?= $user['role'] === 'mechanic' ? 'selected' : '' ?>>Mechanic</option>
            <option value="time_station" <?= $user['role'] === 'time_station' ? 'selected' : '' ?>>Time Kiosk</option>
            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
    </div>
    <div class="mb-3 form-check">
        <input type="checkbox" name="is_active" id="is_active" class="form-check-input" <?= ((int)($user['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
        <label for="is_active" class="form-check-label text-light">Active (shows in reports)</label>
    </div>
    <div class="mb-3">
        <label class="text-light">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
    </div>
    <div class="mb-3">
        <label class="text-light">Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
    </div>
    <div class="mb-3">
        <label class="text-light">Address</label>
        <textarea name="address" class="form-control"><?= htmlspecialchars($user['address']) ?></textarea>
    </div>
    <div class="mb-3">
        <label class="text-light">Emergency Contact</label>
        <input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($user['emergency_contact']) ?>">
    </div>
    <button type="submit" class="btn btn-success">Save Changes</button>
    <a href="admin_panel.php" class="btn btn-secondary">Cancel</a>
</form>
<?php include 'includes/footer.php'; ?>
