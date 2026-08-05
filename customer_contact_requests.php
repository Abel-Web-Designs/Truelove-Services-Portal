<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Fetch requests newest first
$stmt = $pdo->query("
    SELECT id, customer_name, customer_phone, customer_message
    FROM customer_contact_request
    ORDER BY id DESC
");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';

/**
 * Format phone for a tel: link.
 * Keeps digits and a leading + if present.
 */
function phone_for_tel($phone) {
    $phone = trim((string)$phone);
    if ($phone === '') return '';
    // Keep + at start, strip everything else non-digit
    if (strpos($phone, '+') === 0) {
        return '+' . preg_replace('/\D+/', '', substr($phone, 1));
    }
    return preg_replace('/\D+/', '', $phone);
}
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="text-light mb-0">Customer Contact Requests</h2>
        <span class="badge bg-secondary"><?= count($requests) ?> total</span>
    </div>

    <?php if (empty($requests)): ?>
        <div class="alert alert-info">No customer requests yet.</div>
    <?php else: ?>

        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle">
                <thead>
                    <tr>
                        <th style="width: 90px;">ID</th>
                        <th style="width: 220px;">Name</th>
                        <th style="width: 180px;">Phone</th>
                        <th>Message</th>
                        <th class="text-end" style="width: 140px;">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <?php
                            $id    = (int)$r['id'];
                            $name  = $r['customer_name'] ?? '';
                            $phone = $r['customer_phone'] ?? '';
                            $msg   = $r['customer_message'] ?? '';

                            $tel = phone_for_tel($phone);
                        ?>
                        <tr>
                            <td><?= $id ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($name) ?></td>
                            <td>
                                <?php if ($tel): ?>
                                    <span class="font-monospace"><?= htmlspecialchars($phone) ?></span>
                                <?php else: ?>
                                    <span class="text-warning">No phone</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: pre-wrap;"><?= htmlspecialchars($msg) ?></td>
                            <td class="text-end">
                                <?php if ($tel): ?>
                                    <a class="btn btn-success btn-sm"
                                       href="tel:<?= htmlspecialchars($tel) ?>">
                                        Call
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" type="button" disabled>
                                        Call
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
