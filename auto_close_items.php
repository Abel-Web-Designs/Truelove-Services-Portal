<?php
require 'includes/db.php';

$pdo->prepare("
UPDATE employee_supply_issues
SET returned_at = NOW()
WHERE returned_at IS NULL
AND issued_at <= CURDATE() - INTERVAL 45 DAY
")->execute();