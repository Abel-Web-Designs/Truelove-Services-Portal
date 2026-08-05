<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

file_put_contents(__DIR__ . '/Log/cron_debug.log', "START " . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);

try {
    require __DIR__ . '/includes/snow_db.php';
    file_put_contents(__DIR__ . '/Log/cron_debug.log', "DB LOADED\n", FILE_APPEND);

    require __DIR__ . '/includes/snow_functions.php';
    file_put_contents(__DIR__ . '/Log/cron_debug.log', "FUNCTIONS LOADED\n", FILE_APPEND);

    date_default_timezone_set('America/New_York');

    // -------------------- CRON TOGGLE CHECK --------------------
    $stmt = $pdo->prepare("SELECT value FROM app_settings WHERE `key` = ?");
    $stmt->execute(['snow_deduct_enabled']);
    $enabled = (string)($stmt->fetchColumn() ?? '1'); // default ON if missing

    if ($enabled !== '1') {
        file_put_contents(__DIR__ . '/Log/cron_debug.log', "PAUSED (no deductions)\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/Log/cron_debug.log', "END\n\n", FILE_APPEND);
        exit;
    }
    // -----------------------------------------------------------

    updateAllDaysAhead($pdo);
    file_put_contents(__DIR__ . '/Log/cron_debug.log', "UPDATE RAN\n", FILE_APPEND);

} catch (Throwable $e) {
    file_put_contents(
        __DIR__ . '/Log/cron_debug.log',
        "ERROR: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
}

file_put_contents(__DIR__ . '/Log/cron_debug.log', "END\n\n", FILE_APPEND);
