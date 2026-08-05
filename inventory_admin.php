<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

/* =========================================================
   AUTO CLOSE ITEMS AFTER 45 DAYS
========================================================= */
$pdo->prepare("
    UPDATE employee_supply_issues
    SET returned_at = NOW()
    WHERE returned_at IS NULL
    AND issued_at <= CURDATE() - INTERVAL 45 DAY
")->execute();

/* ---------------- ADD ITEM ---------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_item'])) {
    $name = trim($_POST['name']);
    $cat  = trim($_POST['category']);
    $unit = trim($_POST['unit']);

    if($name){
        $stmt=$pdo->prepare("INSERT INTO supply_items(name,category,unit) VALUES(?,?,?)");
        $stmt->execute([$name,$cat,$unit]);
    }
}

/* ---------------- ISSUE ITEM ---------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['issue_item'])) {
    $stmt=$pdo->prepare("INSERT INTO employee_supply_issues
    (employee_id,item_id,qty,size,issued_at,notes,issued_by)
    VALUES(?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['employee_id'],
        $_POST['item_id'],
        $_POST['qty'],
        $_POST['size'],
        $_POST['issued_at'],
        $_POST['notes'],
        $_SESSION['user_id']
    ]);
}

/* ---------------- RETURN ITEM ---------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['return_item'])) {
    $stmt=$pdo->prepare("UPDATE employee_supply_issues
    SET returned_at=NOW(), returned_by=?
    WHERE id=?");
    $stmt->execute([$_SESSION['user_id'],$_POST['issue_id']]);
}

/* ---------------- FILTER ---------------- */
$filter_employee = $_GET['employee_id'] ?? '';

/* ---------------- LOAD DATA ---------------- */
$employees=$pdo->query("SELECT id,name FROM employees WHERE is_active=1 ORDER BY name")->fetchAll();
$items=$pdo->query("SELECT * FROM supply_items WHERE is_active=1 ORDER BY name")->fetchAll();

$sql = "
SELECT 
    esi.*,
    e.name emp,
    i.name item,
    45 - DATEDIFF(CURDATE(), esi.issued_at) AS days_left
FROM employee_supply_issues esi
JOIN employees e ON e.id=esi.employee_id
JOIN supply_items i ON i.id=esi.item_id
WHERE esi.returned_at IS NULL
";

$params = [];

if($filter_employee){
    $sql .= " AND esi.employee_id = ?";
    $params[] = $filter_employee;
}

$sql .= " ORDER BY issued_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$issued = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container mt-4">
<h2>Employee Items / PPE</h2>

<!-- ADD ITEM -->
<div class="card mb-4">
<div class="card-header">Add Item</div>
<div class="card-body">
<form method="POST">
<input name="name" class="form-control mb-2" placeholder="Item name" required>
<input name="category" class="form-control mb-2" placeholder="Category">
<input name="unit" class="form-control mb-2" placeholder="Unit (each, pair)">
<button class="btn btn-primary" name="add_item">Add</button>
</form>
</div>
</div>

<!-- ISSUE ITEM -->
<div class="card mb-4">
<div class="card-header">Issue Item</div>
<div class="card-body">
<form method="POST">
<select name="employee_id" class="form-select mb-2" required>
<option value="">Select Employee</option>
<?php foreach($employees as $e): ?>
<option value="<?=$e['id']?>"><?=$e['name']?></option>
<?php endforeach;?>
</select>

<select name="item_id" class="form-select mb-2" required>
<option value="">Select Item</option>
<?php foreach($items as $i): ?>
<option value="<?=$i['id']?>"><?=$i['name']?></option>
<?php endforeach;?>
</select>

<input type="number" name="qty" class="form-control mb-2" value="" placeholder="Quanity">
<input name="size" class="form-control mb-2" placeholder="Size (optional)">
<input type="date" name="issued_at" class="form-control mb-2" value="<?=date('Y-m-d')?>">
<input name="notes" class="form-control mb-2" placeholder="Notes">

<button class="btn btn-success" name="issue_item">Issue Item</button>
</form>
</div>
</div>

<!-- FILTER -->
<div class="card mb-3">
<div class="card-header">Filter by Employee</div>
<div class="card-body">
<form method="GET">
<select name="employee_id" class="form-select" onchange="this.form.submit()">
<option value="">All Employees</option>
<?php foreach($employees as $e): ?>
<option value="<?=$e['id']?>" <?=($filter_employee==$e['id']?'selected':'')?>>
<?=$e['name']?>
</option>
<?php endforeach;?>
</select>
</form>
</div>
</div>

<!-- OUTSTANDING ITEMS -->
<div class="card">
<div class="card-header">Outstanding Items (Auto clears after 45 days)</div>
<div class="card-body table-responsive">
<table class="table table-striped">
<tr>
    <th>Employee</th>
    <th>Item</th>
    <th>Qty</th>
    <th>Date</th>
    <th>Days Left</th>
    <th>Actions</th>
</tr>
<?php foreach($issued as $r): ?>
<tr>
<td><?=$r['emp']?></td>
<td><?=$r['item']?></td>
<td><?=$r['qty']?></td>
<td><?=$r['issued_at']?></td>
<td>
<?php
$days = (int)$r['days_left'];

$badge = 'bg-success';
if($days <= 10) $badge = 'bg-warning';
if($days <= 3)  $badge = 'bg-danger';

echo "<span class='badge $badge'>{$days} days</span>";
?>
</td>
<td>
<form method="POST">
<input type="hidden" name="issue_id" value="<?=$r['id']?>">
<button class="btn btn-sm btn-danger" name="return_item">Return</button>
</form>
</td>
</tr>
<?php endforeach;?>
</table>
</div>
</div>

</div>

<?php include 'includes/footer.php'; ?>