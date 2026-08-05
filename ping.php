<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (getUserRole() !== 'time_station') {
    http_response_code(403);
    exit('forbidden');
}

echo 'ok';