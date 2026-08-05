<?php
file_put_contents(
    __DIR__ . '/cron_probe.log',
    "Cron executed at " . date('Y-m-d H:i:s') . PHP_EOL,
    FILE_APPEND
);

echo "OK\n";
