<?php
// includes/header.php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

/**
 * Auto-detect BASE_URL for the project root folder (your "TLC" folder).
 * Works for: domain.com/TLC/... or domain.com/tlc/...
 */
$projectRootFs = realpath(__DIR__ . '/..');               // .../TLC
$docRootFs     = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : null;

$baseUrl = '';
if ($docRootFs && $projectRootFs && strpos($projectRootFs, $docRootFs) === 0) {
    $baseUrl = substr($projectRootFs, strlen($docRootFs)); // e.g. "/TLC"
    $baseUrl = str_replace('\\', '/', $baseUrl);
    $baseUrl = rtrim($baseUrl, '/');
} else {
    // Fallback if DOCUMENT_ROOT mapping is weird (rare). Best-effort:
    $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    if ($baseUrl === '/' || $baseUrl === '\\') $baseUrl = '';
}

// Make it available to other includes/pages
if (!defined('BASE_URL')) {
    define('BASE_URL', $baseUrl);
}

// Helper to build URLs safely
function base_url(string $path = ''): string {
    $path = '/' . ltrim($path, '/');
    return rtrim(BASE_URL, '/') . $path;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Truelove Services</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- PWA / icons -->
    <link rel="manifest" href="<?= htmlspecialchars(base_url('manifest.json')) ?>">
    <meta name="theme-color" content="#198754">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(base_url('img/web-app-manifest-192x192.png')) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link rel="icon" type="image/png" href="<?= htmlspecialchars(base_url('img/favicon-96x96.png')) ?>" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(base_url('img/favicon.svg')) ?>" />
    <link rel="shortcut icon" href="<?= htmlspecialchars(base_url('img/favicon.ico')) ?>" />
    <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars(base_url('img/apple-touch-icon.png')) ?>" />
    <meta name="apple-mobile-web-app-title" content="TLC" />

    <style>
        @media (max-width: 576px) {
            .card-body { font-size: 0.95rem; }
            .card-title { font-size: 1rem; }
        }
    </style>
</head>

<body class="bg-dark">

<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand" href="<?= htmlspecialchars(base_url('dashboard.php')) ?>">Truelove Services</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent"
                aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-end" id="navbarContent">
            <ul class="navbar-nav">
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                    <li class="nav-item">
                        <span class="nav-link text-white">
                            Welcome, <?= htmlspecialchars($_SESSION['name'] ?? '') ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?= htmlspecialchars(base_url('logout.php')) ?>">Logout</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
