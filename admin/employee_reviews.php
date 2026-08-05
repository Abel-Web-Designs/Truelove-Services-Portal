<?php
require '../includes/db.php';
require '../includes/auth.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header('Location: ../dashboard.php');
    exit();
}

date_default_timezone_set('America/Indiana/Indianapolis');

$success = '';
$errors = [];

/* -------------------- ACTIVE EMPLOYEES -------------------- */
$employees = $pdo->query("
    SELECT id, name
    FROM employees
    WHERE is_active = 1
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- SAVE REVIEW -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_review') {
    $employee_id = (int) ($_POST['employee_id'] ?? 0);
    $review_date = trim($_POST['review_date'] ?? '');
    $overall_rating = trim($_POST['overall_rating'] ?? '');
    $exceeds_areas = trim($_POST['exceeds_areas'] ?? '');
    $improvement_areas = trim($_POST['improvement_areas'] ?? '');
    $action_plan = trim($_POST['action_plan'] ?? '');
    $additional_notes = trim($_POST['additional_notes'] ?? '');
    $created_by = (int) ($_SESSION['user_id'] ?? 0);

    if ($employee_id <= 0) {
        $errors[] = 'Please select an employee.';
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $review_date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $review_date) {
        $errors[] = 'Please enter a valid review date.';
    }

    if ($exceeds_areas === '' && $improvement_areas === '' && $additional_notes === '' && $action_plan === '') {
        $errors[] = 'Please enter at least some review details.';
    }

    if ($employee_id > 0) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM employees
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$employee_id]);
        if ((int) $stmt->fetchColumn() === 0) {
            $errors[] = 'Selected employee is not active.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            INSERT INTO employee_reviews
            (
                employee_id,
                review_date,
                overall_rating,
                exceeds_areas,
                improvement_areas,
                action_plan,
                additional_notes,
                created_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $employee_id,
            $review_date,
            $overall_rating !== '' ? $overall_rating : null,
            $exceeds_areas !== '' ? $exceeds_areas : null,
            $improvement_areas !== '' ? $improvement_areas : null,
            $action_plan !== '' ? $action_plan : null,
            $additional_notes !== '' ? $additional_notes : null,
            $created_by > 0 ? $created_by : null
        ]);

        header('Location: employee_reviews.php?saved=1');
        exit();
    }
}

if (isset($_GET['saved'])) {
    $success = 'Employee review saved successfully.';
}

/* -------------------- FILTERS -------------------- */
$filter_employee = (int) ($_GET['employee_id'] ?? 0);
$filter_date = trim($_GET['review_date'] ?? '');

$where = [];
$params = [];

if ($filter_employee > 0) {
    $where[] = 'r.employee_id = ?';
    $params[] = $filter_employee;
}

if ($filter_date !== '') {
    $where[] = 'r.review_date = ?';
    $params[] = $filter_date;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* -------------------- REVIEWS LIST -------------------- */
$sql = "
    SELECT
        r.*,
        e.name AS employee_name,
        cb.name AS created_by_name
    FROM employee_reviews r
    JOIN employees e ON e.id = r.employee_id
    LEFT JOIN employees cb ON cb.id = r.created_by
    $whereSql
    ORDER BY r.review_date DESC, r.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- DELETE REVIEW -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_review') {
    $review_id = (int) ($_POST['review_id'] ?? 0);

    if ($review_id <= 0) {
        $errors[] = 'Invalid review selected.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM employee_reviews WHERE id = ?");
        $stmt->execute([$review_id]);

        header('Location: employee_reviews.php?deleted=1');
        exit();
    }
}

if (isset($_GET['deleted'])) {
    $alert = 'Employee review deleted successfully.';
}

require '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="h3 mb-0 text-light">Employee Reviews</h1>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($alert): ?>
        <div class="alert alert-info" role="alert"><?= htmlspecialchars($alert) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Add Employee Review</div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_review">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Employee</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">Select employee...</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= (int) $employee['id'] ?>" <?= ((int) ($_POST['employee_id'] ?? 0) === (int) $employee['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($employee['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Review Date</label>
                        <input type="date" name="review_date" class="form-control"
                            value="<?= htmlspecialchars($_POST['review_date'] ?? date('Y-m-d')) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Overall Rating</label>
                        <select name="overall_rating" class="form-select">
                            <option value="">Select rating...</option>
                            <?php
                            $ratings = ['Excellent', 'Above Expectations', 'Meets Expectations', 'Needs Improvement'];
                            $selectedRating = $_POST['overall_rating'] ?? '';
                            foreach ($ratings as $rating):
                                ?>
                                <option value="<?= htmlspecialchars($rating) ?>" <?= $selectedRating === $rating ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rating) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Areas Employee Is Exceeding In</label>
                        <textarea name="exceeds_areas" class="form-control"
                            rows="4"><?= htmlspecialchars($_POST['exceeds_areas'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Areas Where Employee Could Improve</label>
                        <textarea name="improvement_areas" class="form-control"
                            rows="4"><?= htmlspecialchars($_POST['improvement_areas'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Action Plan / Goals</label>
                        <textarea name="action_plan" class="form-control"
                            rows="3"><?= htmlspecialchars($_POST['action_plan'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="additional_notes" class="form-control"
                            rows="4"><?= htmlspecialchars($_POST['additional_notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Review</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Past Reviews</div>
        <div class="card-body">
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-5">
                    <label class="form-label">Filter by Employee</label>
                    <select name="employee_id" class="form-select">
                        <option value="">All employees</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= (int) $employee['id'] ?>" <?= $filter_employee === (int) $employee['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($employee['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Filter by Date</label>
                    <input type="date" name="review_date" class="form-control"
                        value="<?= htmlspecialchars($filter_date) ?>">
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                    <a href="employee_reviews.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>

            <?php if (!$reviews): ?>
                <div class="alert alert-info mb-0">No reviews found.</div>
            <?php else: ?>
                <div class="accordion" id="reviewsAccordion">
                    <?php foreach ($reviews as $index => $review): ?>
                        <?php
                        $collapseId = 'reviewCollapse' . (int) $review['id'];
                        $headingId = 'reviewHeading' . (int) $review['id'];
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?= $headingId ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#<?= $collapseId ?>" aria-expanded="false"
                                    aria-controls="<?= $collapseId ?>">
                                    <div class="w-100 d-flex justify-content-between flex-wrap gap-2 me-3">
                                        <span>
                                            <strong><?= htmlspecialchars($review['employee_name']) ?></strong>
                                            —
                                            <?= htmlspecialchars(date('m/d/Y', strtotime($review['review_date']))) ?>
                                        </span>
                                        <span>
                                            Rating: <?= htmlspecialchars($review['overall_rating'] ?: '-') ?>
                                        </span>
                                    </div>
                                </button>
                            </h2>
                            <div id="<?= $collapseId ?>" class="accordion-collapse collapse" aria-labelledby="<?= $headingId ?>"
                                data-bs-parent="#reviewsAccordion">
                                <div class="accordion-body">
                                    <div class="mb-2"><strong>Created By:</strong>
                                        <?= htmlspecialchars($review['created_by_name'] ?: '-') ?></div>

                                    <div class="mb-3">
                                        <strong>Areas Employee Is Exceeding In:</strong><br>
                                        <?= nl2br(htmlspecialchars($review['exceeds_areas'] ?: '-')) ?>
                                    </div>

                                    <div class="mb-3">
                                        <strong>Areas Where Employee Could Improve:</strong><br>
                                        <?= nl2br(htmlspecialchars($review['improvement_areas'] ?: '-')) ?>
                                    </div>

                                    <div class="mb-3">
                                        <strong>Action Plan / Goals:</strong><br>
                                        <?= nl2br(htmlspecialchars($review['action_plan'] ?: '-')) ?>
                                    </div>

                                    <div class="mb-3">
                                        <strong>Additional Notes:</strong><br>
                                        <?= nl2br(htmlspecialchars($review['additional_notes'] ?: '-')) ?>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <a href="employee_review_print.php?id=<?= (int) $review['id'] ?>" target="_blank"
                                            class="btn btn-outline-dark btn-sm">
                                            Print PDF
                                        </a>

                                        <form method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this review?');">
                                            <input type="hidden" name="action" value="delete_review">
                                            <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">

                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                Delete Review
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>