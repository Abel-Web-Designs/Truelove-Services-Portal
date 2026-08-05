<?php
if (session_status() === PHP_SESSION_NONE) {

    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    $lifetime = 60 * 60 * 24 * 30; // 30 days

    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.cookie_lifetime', (string)$lifetime);

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'secure'   => $isHttps,  // <-- IMPORTANT
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// (rest of your auth.php below...)
date_default_timezone_set('America/Indiana/Indianapolis');

// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    // Allow 30 days of idle time for kiosk
    $maxIdle = 60 * 60 * 24 * 30;

    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $maxIdle) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }

    $_SESSION['last_activity'] = time();
}

function getUserRole() {
    return $_SESSION['role'] ?? 'guest';
}