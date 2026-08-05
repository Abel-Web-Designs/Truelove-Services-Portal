<?php
require 'includes/db.php';
require 'includes/auth.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$success = '';
$error = '';

/* YOUR TEXTBELT KEY */
$textbeltKey = '089fcbbf8e498369811562800ba94222306a5f1fWQuAT5KRh6gNW5Y7jbaRjZCVH';

/* YOUR GOOGLE REVIEW LINK */
$reviewLink = 'https://app.reviewflowz.com/l/7JPV7uXzzQoECAGD6BcX6W';

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function getTextbeltQuota($key)
{
    $json = @file_get_contents("https://textbelt.com/quota/" . urlencode($key));
    if (!$json) return null;
    $data = json_decode($json, true);
    return $data['quotaRemaining'] ?? null;
}
$TEXTBELT_KEY = getenv('TEXTBELT_API_KEY') ?: '089fcbbf8e498369811562800ba94222306a5f1fWQuAT5KRh6gNW5Y7jbaRjZCVH';
$quotaRemaining = getTextbeltQuota($TEXTBELT_KEY);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');

    /* Replace {name} with actual customer name */
    $message = str_replace('{name}', $name, $message);

    if (!$name || !$phone || !$message) {
        $error = "All fields are required.";
    } else {

        $payload = http_build_query([
            'phone' => $phone,
            'message' => $message,
            'key' => $textbeltKey
        ]);

        $ch = curl_init('https://textbelt.com/text');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (!empty($result['success'])) {
            $success = "Text sent successfully to $phone";
        } else {
            $error = $result['error'] ?? 'Failed to send text.';
        }
    }
}

require 'includes/header.php';
?>

<div class="container py-4">

<h2 class="text-light mb-4">Send Google Review Request</h2>

<?php if ($success): ?>
<div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>


<div class="card bg-dark border-secondary">
<div class="card-body">
<?php if ($quotaRemaining !== null): ?>
                <div class="alert alert-info d-flex align-items-center">
                    <span>📱 SMS quota remaining: <strong><?= htmlspecialchars((string)$quotaRemaining) ?></strong></span>

                    <div class="ms-auto d-flex gap-2">
                        <a href="https://textbelt.com/purchase/" target="_blank" class="btn btn-sm btn-outline-primary">
                            Buy More
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" type="button" onclick="CopyTextBeltAPIKey()">Copy API
                            Key</button>
                    </div>
                </div>
            <?php endif; ?>
<form method="POST">

<div class="mb-3">
<label class="form-label text-light">Customer Name</label>
<input type="text" name="name" class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label text-light">Phone Number</label>
<input type="text" name="phone" class="form-control" placeholder="3175551234" required>
</div>

<div class="mb-3">
<label class="form-label text-light">Message</label>

<textarea name="message" class="form-control" rows="4" required>Hello {name}! Thanks for choosing Truelove Services.

If you have a moment, we'd really appreciate a Google review:

<?= $reviewLink ?>

Thank you for your support!
</textarea>

</div>

<button class="btn btn-primary">
Send Text
</button>

</form>

</div>
</div>

</div>
<script>
    function CopyTextBeltAPIKey() {
        const copyText = "089fcbbf8e498369811562800ba94222306a5f1fWQuAT5KRh6gNW5Y7jbaRjZCVH";
        navigator.clipboard.writeText(copyText)
            .catch(err => {
                console.error("Failed to copy: ", err);
            });
    }
</script>
<?php include 'includes/footer.php'; ?>