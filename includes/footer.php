<?php
if (!function_exists('base_url')) {
    require_once __DIR__ . '/config.php'; // or wherever base_url is defined
}
?>

<hr>

<a href="<?= htmlspecialchars(base_url('dashboard.php')) ?>" class="btn btn-light">
    Back to Dashboard
</a>

</div> <!-- end .container -->

<footer class="bg-dark text-white text-center py-3 mt-5">
    <small>&copy; <?= date('Y') ?> Truelove Services All rights reserved</small>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Service Worker -->
<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?= htmlspecialchars(base_url('service-worker.js')) ?>')
            .then(() => console.log('Service Worker registered'))
            .catch((err) => console.error('SW registration failed:', err));
    }
</script>

</body>
</html>
