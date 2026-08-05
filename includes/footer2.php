<hr>
</div>
<footer class="bg-dark text-white text-center py-3 mt-5">
    <small>&copy; <?= date('Y') ?> Truelove Services All rights reserved.</small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/service-worker.js')
        .then(() => console.log('Service Worker registered'))
        .catch((err) => console.error('SW registration failed:', err));
    }
</script>
</body>
</html>
