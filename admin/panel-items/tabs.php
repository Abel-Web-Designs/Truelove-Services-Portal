<div class="container mt-5">

    <h2 class="mb-4 text-light">Admin Panel</h2>

    <?php if (isset($_GET['employee_added']) && $_GET['employee_added'] == 1): ?>
        <div class="alert alert-success">Employee added successfully</div>
    <?php elseif (isset($_GET['employee_added']) && $_GET['employee_added'] == 0): ?>
        <div class="alert alert-danger">Failed to add employee.</div>
    <?php endif; ?>

    <?php if (isset($_GET['announcement_sent']) && $_GET['announcement_sent'] == 1): ?>
        <div class="alert alert-success">Announcement sent</div>
    <?php elseif (isset($_GET['announcement_sent']) && $_GET['announcement_sent'] == 0): ?>
        <div class="alert alert-danger">Announcement message was empty.</div>
    <?php endif; ?>

    <?php if (isset($_GET['pc_added']) && $_GET['pc_added'] == 1): ?>
        <div class="alert alert-success">Passcode added</div>
    <?php elseif (isset($_GET['pc_added']) && $_GET['pc_added'] == 0): ?>
        <div class="alert alert-danger">Equipment and passcode are required.</div>
    <?php endif; ?>

    <?php if (isset($_GET['pc_updated']) && $_GET['pc_updated'] == 1): ?>
        <div class="alert alert-success">Passcode updated</div>
    <?php elseif (isset($_GET['pc_updated']) && $_GET['pc_updated'] == 0): ?>
        <div class="alert alert-danger">Invalid update input.</div>
    <?php endif; ?>

    <?php if (isset($_GET['pc_deleted']) && $_GET['pc_deleted'] == 1): ?>
        <div class="alert alert-success">Passcode deleted</div>
    <?php endif; ?>

    <?php if (isset($_GET['login_added']) && $_GET['login_added'] == 1): ?>
        <div class="alert alert-success">Login added</div>
    <?php elseif (isset($_GET['login_added']) && $_GET['login_added'] == 0): ?>
        <div class="alert alert-danger">App name, username, and password are required.</div>
    <?php endif; ?>

    <?php if (isset($_GET['login_updated']) && $_GET['login_updated'] == 1): ?>
        <div class="alert alert-success">Login updated</div>
    <?php elseif (isset($_GET['login_updated']) && $_GET['login_updated'] == 0): ?>
        <div class="alert alert-danger">Invalid login update input.</div>
    <?php endif; ?>

    <?php if (isset($_GET['login_deleted']) && $_GET['login_deleted'] == 1): ?>
        <div class="alert alert-success">Login deleted</div>
    <?php endif; ?>

    <!-- TABS -->
    <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="employees-tab" data-bs-toggle="tab" data-bs-target="#employees"
                type="button" role="tab" aria-controls="employees" aria-selected="true">
                Employees
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="time-tab" data-bs-toggle="tab" data-bs-target="#time" type="button"
                role="tab" aria-controls="time" aria-selected="false">
                Time
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance"
                type="button" role="tab" aria-controls="attendance" aria-selected="false">
                Attendance
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button"
                role="tab" aria-controls="payroll" aria-selected="false">
                Payroll
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="announcements-tab" data-bs-toggle="tab" data-bs-target="#announcements"
                type="button" role="tab" aria-controls="announcements" aria-selected="false">
                Announcements
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="passwords-tab" data-bs-toggle="tab" data-bs-target="#passwords" type="button"
                role="tab" aria-controls="passwords" aria-selected="false">
                Passwords
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="app-passwords-tab" data-bs-toggle="tab" data-bs-target="#app_passwords"
                type="button" role="tab" aria-controls="app_passwords" aria-selected="false">
                App Logins
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabsContent"></div>