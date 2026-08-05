<?php
// equipment_add.php
session_start();
require 'includes/db.php';
require 'includes/header.php';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $serial = trim($_POST['serial']);
    $purchase_date = $_POST['purchase_date'] ?? null;

    if ($name !== '' && $type !== '') {
        $stmt = $pdo->prepare("INSERT INTO equipment (name, type_new, serial_number, purchase_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $type, $serial, $purchase_date]);
        $message = '<div class="alert alert-success">Equipment added successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
    }
}
?>

<div class="container mt-4">
    <h1 class="mb-4 text-light">Add Equipment</h1>

    <?= $message ?>

    <form method="POST" class="card p-4 shadow-sm">
        <div class="mb-3">
            <label class="form-label">Equipment Name*</label>
            <input type="text" name="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Type*</label>
            <select class="form-select" aria-label="Select type" name="type" required>
                <option value="">Select Type</option>
                <option value="Truck">Truck</option>
                <option value="Skidsteer">Skidsteer</option>
                <option value="Mower">Mower</option>
                <option value="Loader">Loader</option>
                <option value="Trailer">Trailer</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Serial Number</label>
            <input type="text" name="serial" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Purchase Date</label>
            <input type="date" name="purchase_date" class="form-control">
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Add Equipment</button>
            <a href="equipment_list.php" class="btn btn-secondary">Back</a>
        </div>
    </form>
</div>

<?php require 'footer.php'; // ✅ matches site footer ?>
