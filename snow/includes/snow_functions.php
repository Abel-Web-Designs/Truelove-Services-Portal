<?php
function logLine($message, $logFile) {
    file_put_contents(
        $logFile,
        '[' . date('Y-m-d H:i:s') . " EST] $message\n",
        FILE_APPEND
    );
}

/**
 * Update days ahead for a single employee by ID
 */
function updateDaysAhead(PDO $pdo, int $employeeId) {
    $stmt = $pdo->prepare("
        SELECT days_ahead, last_checked 
        FROM snow_balances 
        WHERE employee_id = ?
    ");
    $stmt->execute([$employeeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) return;

    $daysAhead = (int)$row['days_ahead'];
    $lastChecked = $row['last_checked'] ? new DateTime($row['last_checked']) : null;
    $today = new DateTime('today');

    if ($lastChecked === null) {
        // First run, set last_checked
        $stmt = $pdo->prepare("
            UPDATE snow_balances SET last_checked = ? WHERE employee_id = ?
        ");
        $stmt->execute([$today->format('Y-m-d'), $employeeId]);
        return;
    }

    // Calculate weekdays between last_checked and today
    $period = new DatePeriod(
        clone $lastChecked,
        new DateInterval('P1D'),
        $today
    );

    $decrement = 0;
    foreach ($period as $day) {
        $weekday = (int)$day->format('N'); // 1=Mon ... 7=Sun
        if ($weekday >= 1 && $weekday <= 5) {
            $decrement++;
        }
    }

    if ($decrement > 0) {
        $newBalance = max($daysAhead - $decrement, 0);
        $stmt = $pdo->prepare("
            UPDATE snow_balances
            SET days_ahead = ?, last_checked = ?
            WHERE employee_id = ?
        ");
        $stmt->execute([$newBalance, $today->format('Y-m-d'), $employeeId]);
    }
}

/**
 * Update all employees at once (weekdays only)
 */
function updateAllDaysAhead(PDO $pdo, bool $enableLogging = false, string $logFile = '') {
    date_default_timezone_set('America/New_York');
    $today = new DateTime('today');

    $stmt = $pdo->query("
        SELECT employee_id, days_ahead, last_decrement_date
        FROM snow_balances
        WHERE days_ahead > 0
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $daysAhead = (int)$row['days_ahead'];
        $lastDate = $row['last_decrement_date'] ? new DateTime($row['last_decrement_date']) : null;

        // If never decremented before, set today and skip
        if (!$lastDate) {
            $pdo->prepare("
                UPDATE snow_balances
                SET last_decrement_date = ?
                WHERE employee_id = ?
            ")->execute([$today->format('Y-m-d'), $row['employee_id']]);
            continue;
        }

        // Count weekdays between last run and today
        $daysToSubtract = 0;
        $cursor = clone $lastDate;
        $cursor->modify('+1 day');

        while ($cursor <= $today) {
            $dayOfWeek = (int)$cursor->format('N'); // 1=Mon, 7=Sun
            if ($dayOfWeek <= 5) {
                $daysToSubtract++;
            }
            $cursor->modify('+1 day');
        }

        if ($daysToSubtract <= 0) continue;

        $newDaysAhead = max(0, $daysAhead - $daysToSubtract);

        if ($enableLogging && $logFile) {
            logLine(
                "Employee {$row['employee_id']} | -$daysToSubtract days | New balance: $newDaysAhead",
                $logFile
            );
        }

        $pdo->prepare("
            UPDATE snow_balances
            SET days_ahead = ?, last_decrement_date = ?
            WHERE employee_id = ?
        ")->execute([
            $newDaysAhead,
            $today->format('Y-m-d'),
            $row['employee_id']
        ]);
    }
}
