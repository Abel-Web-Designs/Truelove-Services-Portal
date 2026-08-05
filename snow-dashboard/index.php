<?php
session_start();
require '../includes/db.php';
require '../includes/header.php';
?>

<div class="container mt-4 text-light">
    <h1 class="mb-4">Snow Dashboard</h1>
    <p>Get support and view walk through of snow related apps and information</p>

    <div class="accordion accordion-flush" id="snowremovalapps">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#UtilizeCore" aria-expanded="false" aria-controls="UtilizeCore">
                    UtilizeCore
                </button>
            </h2>
            <div id="UtilizeCore" class="accordion-collapse collapse" data-bs-parent="#snowremovalapps">
                <div class="accordion-body">
                    <h3>Common Issues</h3>
                    
                    <hr>

                    <!-- No Work Orders -->
                    <h5>No Work Orders</h5>
                    <p>When opening UtilizeCore, if your screen shows "No work orders", do the following</p>
                    <ol class="list-group list-group-numbered">
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold">Click "+" Button</div>
                            </div>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold">Select "Report Service"</div>
                            </div>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold">Select Location</div>
                                Make sure when selecting the location that you check the address that you are at
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#OneOnSite" aria-expanded="false" aria-controls="OneOnSite">
                    One OnSite
                </button>
            </h2>
            <div id="OneOnSite" class="accordion-collapse collapse" data-bs-parent="#snowremovalapps">
                <div class="accordion-body">
                    <h3>Common Issues</h3>
                    
                    <hr>

                    <!-- No Work Orders -->
                    <h5>No Work Orders</h5>
                    <p>When opening OnSite, if your screen shows "No work orders", do the following</p>
                    <ol class="list-group list-group-numbered">
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold">Click Profile</div>
                                In the bottom right, click the "Profile" button
                            </div>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold">Switch Profile Type</div>
                                At the top of the screen, make sure "Snow & Ice Management" is selected
                            </div>
                        </li>
                    </ol>

                    <hr>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#Powerhouse" aria-expanded="false" aria-controls="Powerhouse">
                    Powerhouse (MetryX)
                </button>
            </h2>
            <div id="Powerhouse" class="accordion-collapse collapse" data-bs-parent="#snowremovalapps">
                <div class="accordion-body">

                </div>
            </div>
        </div>
    </div>

</div>

<hr>
<a href="../dashboard.php" class="btn btn-light">Back to Dashboard</a>
</div> <!-- end .container -->
<footer class="bg-dark text-white text-center py-3 mt-5">
    <small>&copy; <?= date('Y') ?> Truelove Lawn Care All rights reserved.</small>
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