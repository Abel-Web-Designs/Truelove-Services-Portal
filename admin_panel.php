<?php include 'admin/panel-items/php-scripts.php' ?>

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
            <button class="nav-link" id="time-tab" data-bs-toggle="tab" data-bs-target="#time" type="button" role="tab"
                aria-controls="time" aria-selected="false">
                Time
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button"
                role="tab" aria-controls="attendance" aria-selected="false">
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

    <div class="tab-content" id="adminTabsContent">

        <!-- ================= EMPLOYEES ================= -->
        <div class="tab-pane fade show active" id="employees" role="tabpanel" aria-labelledby="employees-tab">

            <button class="btn btn-primary btn-sm mb-3" data-bs-toggle="collapse" data-bs-target="#addEmployee">
                + Add Employee
            </button>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted small text-light">Sort by:</span>

                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary text-light" type="button" onclick="sortTable(0)">
                        Name <span class="sort-indicator"></span>
                    </button>
                    <button class="btn btn-outline-secondary text-light" type="button" onclick="sortTable(1)">
                        PIN <span class="sort-indicator"></span>
                    </button>
                    <button class="btn btn-outline-secondary text-light" type="button" onclick="sortTable(2)">
                        Role <span class="sort-indicator"></span>
                    </button>
                </div>
            </div>

            <div class="collapse mb-4" id="addEmployee">
                <div class="card p-3">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="add_employee" value="1">

                        <div class="col-md-4">
                            <input type="text" name="name" class="form-control" placeholder="Name" required>
                        </div>

                        <div class="col-md-3">
                            <input type="text" name="pin" class="form-control" placeholder="4-digit PIN" pattern="\d{4}"
                                required>
                        </div>

                        <div class="col-md-3">
                            <select name="role" class="form-select" required>
                                <option value="employee">Employee</option>
                                <option value="work_phone">Work Phone</option>
                                <option value="mechanic">Mechanic</option>
                                <option value="admin">Admin</option>
                                <option value="time_station">Time Kiosk</option>
                                <option value="truck_driver">Truck Driver</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <button class="btn btn-success w-100">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <table id="employeesTable" class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>PIN</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><?= htmlspecialchars($emp['name']) ?></td>
                            <td><?= htmlspecialchars($emp['pin']) ?></td>
                            <td class="text-nowrap">
                                <?= ucwords(str_replace('_', ' ', $emp['role'])) ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if ((int) ($emp['is_active'] ?? 1) === 1): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="employee_profile.php?user_id=<?= (int) $emp['id'] ?>"
                                        class="btn btn-outline-secondary btn-sm">
                                        View
                                    </a>

                                    <a href="edit_single_user.php?user_id=<?= (int) $emp['id'] ?>"
                                        class="btn btn-outline-primary btn-sm">
                                        Edit
                                    </a>

                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_employee_active">
                                        <input type="hidden" name="employee_id" value="<?= (int) $emp['id'] ?>">
                                        <?php if ((int) ($emp['is_active'] ?? 1) === 1): ?>
                                            <input type="hidden" name="set_active" value="0">
                                            <button type="submit" class="btn btn-outline-warning btn-sm"
                                                onclick="return confirm('Disable this employee? They will be hidden from reports and attendance.')">
                                                Disable
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="set_active" value="1">
                                            <button type="submit" class="btn btn-outline-success btn-sm">
                                                Enable
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <form method="POST" action="delete_user.php"
                                        onsubmit="return confirm('Delete this employee?')" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= (int) $emp['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ================= TIME ================= -->
        <div class="tab-pane fade" id="time">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h4 class="text-light mb-0">Time Punches</h4>
                </div>

                <div>
                    <a href="" class="btn btn-outline-secondary btn-sm text-light" data-bs-toggle="modal"
                        data-bs-target="#AddTimePunchModal">
                        Add Time Punch
                    </a>
                    <a href="edit_time_punches.php" class="btn btn-outline-secondary btn-sm text-light">
                        Edit Punches
                    </a>
                </div>

                <!-- ✅ Add Time Punch Modal -->
                <div class="modal fade" id="AddTimePunchModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content bg-dark text-light border-secondary">
                            <div class="modal-header border-secondary">
                                <h5 class="modal-title">Add Time Punch</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>

                            <form method="POST" id="addTimePunchForm">
                                <div class="modal-body">

                                    <?php if (!empty($addPunchError)): ?>
                                        <div class="alert alert-danger"><?= htmlspecialchars($addPunchError) ?></div>
                                    <?php endif; ?>

                                    <input type="hidden" name="add_time_punch" value="1">

                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Employee</label>
                                            <select name="employee_id" class="form-select" required>
                                                <option value="">-- Select Employee --</option>
                                                <?php foreach ($employeesForPunch as $emp): ?>
                                                    <option value="<?= (int) $emp['id'] ?>">
                                                        <?= htmlspecialchars($emp['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Time In -->
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">Time In</label>
                                            <div class="row g-2">
                                                <div class="col-7">
                                                    <input type="date" name="time_in_date" class="form-control"
                                                        value="<?= date('Y-m-d') ?>" required>
                                                </div>
                                                <div class="col-5">
                                                    <input type="time" name="time_in_time" class="form-control"
                                                        step="60" required>
                                                </div>
                                            </div>
                                            <div class="text-muted small mt-1">Choose the date + time they clocked in.
                                            </div>
                                        </div>

                                        <!-- Time Out -->
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">Time Out</label>
                                            <div class="row g-2">
                                                <div class="col-7">
                                                    <input type="date" name="time_out_date" class="form-control"
                                                        value="<?= date('Y-m-d') ?>" required>
                                                </div>
                                                <div class="col-5">
                                                    <input type="time" name="time_out_time" class="form-control"
                                                        step="60" required>
                                                </div>
                                            </div>
                                            <div class="text-muted small mt-1">If it’s overnight, pick the next day.
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-outline-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary" id="addPunchBtn">
                                        <span class="btn-text">Add Time Punch</span>
                                        <span class="spinner-border spinner-border-sm ms-2 d-none" id="addPunchSpinner"
                                            role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>

            <?php
            // 1) Group raw punches by employee first (chronological), so shifts can cross midnight.
            $byEmployee = [];
            foreach ($rawLogs as $log) {
                $name = $log['name'];
                $byEmployee[$name][] = $log;
            }

            // 2) Build shifts per employee across ALL timestamps (pair IN->OUT even across midnight)
            $shiftsByDate = []; // [date][employee][] = shift data
            
            foreach ($byEmployee as $empName => $punches) {

                usort($punches, fn($a, $b) => strtotime($a['timestamp']) <=> strtotime($b['timestamp']));

                $openIn = null;

                foreach ($punches as $p) {
                    $type = strtolower(trim($p['clock_type']));

                    if ($type === 'in') {
                        // If there is already an open IN, keep the earliest one (ignore double IN)
                        if ($openIn === null) {
                            $openIn = $p;
                        }
                        continue;
                    }

                    if ($type === 'out') {
                        if ($openIn !== null) {
                            // Found a normal IN->OUT pair (can cross midnight)
                            $inTs = strtotime($openIn['timestamp']);
                            $outTs = strtotime($p['timestamp']);
                            $sec = max(0, $outTs - $inTs);

                            // Assign the shift to the IN date (recommended for payroll)
                            $displayDate = date('Y-m-d', $inTs);

                            $shiftsByDate[$displayDate][$empName][] = [
                                'in' => $openIn,
                                'out' => $p,
                                'seconds' => $sec
                            ];

                            $openIn = null;
                        } else {
                            // OUT without IN (orphan OUT) - assign to OUT date
                            $displayDate = date('Y-m-d', strtotime($p['timestamp']));
                            $shiftsByDate[$displayDate][$empName][] = [
                                'in' => null,
                                'out' => $p,
                                'seconds' => 0
                            ];
                        }
                    }
                }

                // If an employee ended with an IN and no OUT, keep it as an incomplete shift on the IN date
                if ($openIn !== null) {
                    $displayDate = date('Y-m-d', strtotime($openIn['timestamp']));
                    $shiftsByDate[$displayDate][$empName][] = [
                        'in' => $openIn,
                        'out' => null,
                        'seconds' => 0
                    ];
                }
            }

            // Sort dates DESC
            krsort($shiftsByDate);
            ?>

            <?php if (empty($rawLogs)): ?>
                <div class="alert alert-info">No time punches found.</div>
            <?php else: ?>

                <div class="accordion" id="timeLogsAccordion">

                    <?php $i = 0; ?>
                    <?php foreach ($shiftsByDate as $date => $byEmployee): ?>
                        <?php $i++; ?>

                        <div class="accordion-item  border-secondary text-dark">

                            <h2 class="accordion-header" id="heading<?= $i ?>">
                                <button class="accordion-button collapsed bg-dark text-light" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#collapse<?= $i ?>" aria-expanded="false">

                                    <div class="d-flex justify-content-between w-100 align-items-center flex-wrap gap-2">

                                        <span class="fw-semibold">
                                            <?= date('l - F j, Y', strtotime($date)) ?>
                                        </span>

                                        <span class="text-light small">
                                            <?= count($byEmployee) ?> employee(s)
                                        </span>

                                    </div>

                                </button>
                            </h2>

                            <div id="collapse<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#timeLogsAccordion">

                                <div class="accordion-body">

                                    <?php foreach ($byEmployee as $empName => $punches): ?>

                                        <?php
                                        $pairs = $byEmployee[$empName];
                                        $totalSeconds = array_sum(array_column($pairs, 'seconds'));

                                        $h = floor($totalSeconds / 3600);
                                        $m = floor(($totalSeconds % 3600) / 60);
                                        $dayTotal = sprintf('%d:%02d', $h, $m);
                                        ?>

                                        <div class="mb-4">

                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                                <div class="fw-semibold"><?= htmlspecialchars($empName) ?></div>

                                                <div class="text-light small">
                                                    Total:
                                                    <span class="badge bg-success"><?= $dayTotal ?></span>
                                                </div>
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-dark table-striped align-middle mb-0">

                                                    <thead>
                                                        <tr>
                                                            <th style="width:110px;">Shift</th>
                                                            <th>Clock In</th>
                                                            <th>Clock Out</th>
                                                        </tr>
                                                    </thead>

                                                    <tbody>

                                                        <?php foreach ($pairs as $shiftIndex => $pair): ?>

                                                            <?php
                                                            $in = $pair['in'];
                                                            $out = $pair['out'];
                                                            $shiftSeconds = (int) $pair['seconds'];

                                                            if ($in && $out) {
                                                                $shiftSeconds = strtotime($out['timestamp']) - strtotime($in['timestamp']);
                                                            }

                                                            $sh = $shiftSeconds > 0 ? floor($shiftSeconds / 3600) : 0;
                                                            $sm = $shiftSeconds > 0 ? floor(($shiftSeconds % 3600) / 60) : 0;
                                                            $shiftTotal = $shiftSeconds > 0 ? sprintf('%d:%02d', $sh, $sm) : '';
                                                            ?>

                                                            <tr>

                                                                <td class="fw-semibold">
                                                                    Shift <?= $shiftIndex + 1 ?>

                                                                    <?php if ($shiftTotal): ?>
                                                                        <div class="text-light small">
                                                                            Total: <?= $shiftTotal ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </td>

                                                                <td>
                                                                    <?php if ($in): ?>
                                                                        <span class="badge bg-success">IN</span>
                                                                        <?= date('g:i A', strtotime($in['timestamp'])) ?>
                                                                    <?php else: ?>
                                                                        <span class="text-warning">Missing IN</span>
                                                                    <?php endif; ?>
                                                                </td>

                                                                <td>
                                                                    <?php if ($out): ?>
                                                                        <span class="badge bg-danger">OUT</span>
                                                                        <?= date('g:i A', strtotime($out['timestamp'])) ?>
                                                                    <?php else: ?>
                                                                        <span class="text-warning">Missing OUT</span>
                                                                    <?php endif; ?>
                                                                </td>

                                                            </tr>

                                                        <?php endforeach; ?>

                                                    </tbody>
                                                </table>
                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

        <!-- ================= ANNOUNCEMENTS ================= -->
        <div class="tab-pane fade" id="announcements" role="tabpanel" aria-labelledby="announcements-tab">

            <?php if ($quotaRemaining !== null): ?>
                <div class="alert alert-info d-flex align-items-center">
                    <span>📱 SMS quota remaining: <strong><?= htmlspecialchars((string) $quotaRemaining) ?></strong></span>

                    <div class="ms-auto d-flex gap-2">
                        <a href="https://textbelt.com/purchase/" target="_blank" class="btn btn-sm btn-outline-primary">
                            Buy More
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" type="button" onclick="CopyTextBeltAPIKey()">Copy
                            API
                            Key</button>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <textarea name="announcement" class="form-control" rows="4" placeholder="Write announcement..."
                        required></textarea>
                </div>

                <div class="d-grid gap-2 d-md-flex align-items-md-center mb-3">
                    <button class="btn btn-outline-secondary text-light" type="button" data-bs-toggle="collapse"
                        data-bs-target="#announcementRecipients">
                        Select Recipients (Optional)
                    </button>

                    <button class="btn btn-primary" type="submit">
                        Send Announcement
                    </button>

                    <a href="announcements.php" class="btn btn-outline-secondary text-light">
                        Manage Announcements
                    </a>
                </div>

                <div class="collapse mb-3" id="announcementRecipients">
                    <div class="card card-body">
                        <?php foreach ($employeesActive as $emp): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="recipients[]"
                                    value="<?= (int) $emp['id'] ?>" id="emp_<?= (int) $emp['id'] ?>">
                                <label class="form-check-label" for="emp_<?= (int) $emp['id'] ?>">
                                    <?= htmlspecialchars($emp['name']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>


        <!-- ================= ATTENDANCE ================= -->
        <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">

            <?php if (!empty($attendanceError)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($attendanceError) ?></div>
            <?php elseif (!empty($attendanceSuccess)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($attendanceSuccess) ?></div>
            <?php endif; ?>

            <div class="card p-3 mb-3">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-end">
                    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end m-0">
                        <div>
                            <label class="form-label mb-1">Attendance Date</label>
                            <input type="date" name="attendance_date" class="form-control"
                                value="<?= htmlspecialchars($attendanceDate) ?>">
                        </div>
                        <button class="btn btn-outline-dark" type="submit">Load</button>
                        <input type="hidden" name="start_date"
                            value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
                    </form>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-secondary"
                            onclick="attendanceSelectAll(true)">Select All</button>
                        <button type="button" class="btn btn-sm btn-secondary"
                            onclick="attendanceSelectAll(false)">Select None</button>
                    </div>
                </div>
            </div>

            <form method="POST" class="card p-3">
                <input type="hidden" name="save_attendance" value="1">
                <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($attendanceDate) ?>">

                <div class="mb-3">
                    <label class="form-label">Notes for the day</label>
                    <textarea name="attendance_note" class="form-control" rows="3"
                        placeholder="Optional note for this date..."><?= htmlspecialchars($attendanceNote) ?></textarea>
                </div>

                <div class="row">
                    <?php foreach ($employeesActive as $emp): ?>
                        <?php if (($emp['role'] ?? '') === 'time_station')
                            continue; ?>
                        <?php
                        $eid = (int) $emp['id'];
                        // Default unchecked unless previously saved as present
                        $checked = (!empty($attendancePresentById) && (int) ($attendancePresentById[$eid] ?? 0) === 1);
                        ?>
                        <div class="col-12 col-md-6 col-lg-4 mb-2">
                            <div
                                class="form-check form-switch d-flex justify-content-between align-items-center border rounded p-2">
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($emp['name']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($emp['role'] ?? '') ?></div>
                                </div>
                                <input class="form-check-input attendance-toggle" type="checkbox" role="switch"
                                    name="present[]" value="<?= $eid ?>" <?= $checked ? 'checked' : '' ?>>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-3">
                    <button class="btn btn-success">Save Attendance</button>
                </div>
            </form>

            <script>
                function attendanceSelectAll(on) {
                    document.querySelectorAll('.attendance-toggle').forEach(cb => cb.checked = !!on);
                }
            </script>

        </div>

        <!-- ================= PAYROLL ================= -->
        <div class="tab-pane fade" id="payroll">

            <?php if (isset($_GET['note_saved'])): ?>
                <div class="alert alert-success">Payroll note saved</div>
            <?php endif; ?>

            <?php if (!empty($payrollNoteError)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($payrollNoteError) ?></div>
            <?php endif; ?>

            <div class="card bg-dark border-secondary mb-3">
                <div class="card-body">
                    <!-- Added note for Austin & Jeff -->
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-auto">
                            <p class="form-label mb-0 text-light">
                                When generating payroll reports, please select <b class="text-info">Sunday</b> as the
                                first day. Please
                                follow the same when attaching notes. I am working on a fix for this currently. Thanks!
                            </p>
                        </div>
                    </div>

                    <hr class="border-secondary my-3">

                    <!-- Date Range (shared inputs) -->
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-auto">
                            <label for="start_date" class="form-label mb-0 text-light">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control"
                                value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>" required>
                            <div class="small mt-1 text-light">Select a week start date.</div>
                        </div>

                        <div class="col-12 col-md-auto">
                            <label for="end_date" class="form-label mb-0 text-light">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" readonly required>
                            <div class="small mt-1 text-light">Auto-calculated (start + 6 days).</div>
                        </div>
                    </div>

                    <hr class="border-secondary my-3">

                    <!-- Save Note (POST) -->
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="save_payroll_note" value="1">
                        <input type="hidden" name="period_start" id="period_start_hidden" value="">
                        <input type="hidden" name="period_end" id="period_end_hidden" value="">

                        <label class="form-label text-light">Payroll Notes (will appear on PDF)</label>
                        <textarea name="note" class="form-control" rows="4"
                            placeholder="Add a note for this payroll period..."></textarea>

                        <?php if (!empty($payrollNoteMeta)): ?>
                            <div class="text-light small mt-2">
                                Last saved by <?= htmlspecialchars($payrollNoteMeta['created_by_name'] ?? 'Unknown') ?>
                                • Updated
                                <?= htmlspecialchars($payrollNoteMeta['updated_at'] ?? $payrollNoteMeta['created_at'] ?? '') ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-3 d-flex gap-2 flex-wrap">
                            <button type="submit" name="action" value="save" class="btn btn-success">
                                Save Note
                            </button>

                            <button type="submit" name="action" value="load" class="btn btn-info">
                                Load Notes for Selected Week
                            </button>
                        </div>
                    </form>

                    <!-- Generate PDF (GET) -->
                    <form method="GET" action="export_time_punches.php" class="d-flex gap-2 flex-wrap align-items-end">
                        <input type="hidden" name="start_date" id="pdf_start_date" value="">
                        <input type="hidden" name="end_date" id="pdf_end_date" value="">

                        <button type="submit" class="btn btn-outline-secondary text-light">
                            Generate Hours Worked Report (PDF)
                        </button>
                    </form>

                    <?php if (!empty($payrollNotesForWeek)): ?>
                        <div class="mt-3">
                            <div class="text-light fw-semibold mb-2">Notes for this period</div>
                            <div class="list-group">
                                <?php foreach ($payrollNotesForWeek as $n): ?>
                                    <div class="list-group-item bg-dark text-light border-secondary">
                                        <div class="small text-light mb-1">
                                            <?= htmlspecialchars($n['created_by_name'] ?? 'Unknown') ?> •
                                            <?= htmlspecialchars($n['created_at']) ?>
                                        </div>
                                        <div><?= nl2br(htmlspecialchars($n['note'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>


                </div>
            </div>
        </div>

        <!-- ================= EQUIPMENT PASSWORDS ================= -->
        <div class="tab-pane fade" id="passwords" role="tabpanel" aria-labelledby="passwords-tab">

            <?php
            $equipmentList = $pdo->query("SELECT id, name FROM equipment ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

            $passcodes = $pdo->query("
                SELECT p.*, e.name AS equipment_name
                FROM equipment_passcodes p
                JOIN equipment e ON e.id = p.equipment_id
                ORDER BY e.name ASC, p.is_active DESC, p.id DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h4 class="text-light mb-0">Equipment Passcodes</h4>

                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse"
                    data-bs-target="#addPasscode">
                    + Add Passcode
                </button>
            </div>

            <div class="collapse mb-3" id="addPasscode">
                <div class="card card-body">
                    <form method="POST" class="row g-2">
                        <input type="hidden" name="add_passcode" value="1">

                        <div class="col-12 col-md-4">
                            <label class="form-label">Equipment</label>
                            <select name="equipment_id" class="form-select" required>
                                <option value="">Select equipment...</option>
                                <?php foreach ($equipmentList as $eq): ?>
                                    <option value="<?= (int) $eq['id'] ?>"><?= htmlspecialchars($eq['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label">Label</label>
                            <input type="text" name="label" class="form-control" placeholder="Door / Ignition / etc">
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label">Passcode</label>
                            <input type="text" name="passcode" class="form-control" required>
                        </div>

                        <div class="col-12 d-grid">
                            <button class="btn btn-success">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Equipment</th>
                            <th style="width: 220px;">Passcode</th>
                            <th class="text-end" style="width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($passcodes)): ?>
                            <tr>
                                <td colspan="3" class="text-light">No passcodes saved.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($passcodes as $p): ?>
                                <?php
                                $id = (int) $p['id'];
                                $collapseId = "edit_pc_$id";
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['equipment_name']) ?></td>
                                    <td><?= htmlspecialchars($p['passcode'] ?? '') ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                            <button class="btn btn-outline-primary btn-sm" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                                                Edit
                                            </button>

                                            <form method="POST" class="m-0" onsubmit="return confirm('Delete this passcode?')">
                                                <input type="hidden" name="delete_passcode" value="1">
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <tr class="collapse" id="<?= $collapseId ?>">
                                    <td colspan="3">
                                        <form method="POST" class="row g-2">
                                            <input type="hidden" name="update_passcode" value="1">
                                            <input type="hidden" name="id" value="<?= $id ?>">

                                            <div class="col-12 col-md-4">
                                                <label class="form-label">Equipment</label>
                                                <select name="equipment_id" class="form-select" required>
                                                    <?php foreach ($equipmentList as $eq): ?>
                                                        <option value="<?= (int) $eq['id'] ?>"
                                                            <?= ((int) $eq['id'] === (int) $p['equipment_id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($eq['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-12 col-md-3">
                                                <label class="form-label">Label</label>
                                                <input type="text" name="label" class="form-control"
                                                    value="<?= htmlspecialchars($p['label'] ?? '') ?>">
                                            </div>

                                            <div class="col-12 col-md-3">
                                                <label class="form-label">Passcode</label>
                                                <input type="text" name="passcode" class="form-control" required
                                                    value="<?= htmlspecialchars($p['passcode'] ?? '') ?>">
                                            </div>

                                            <div class="col-12 d-grid">
                                                <button class="btn btn-success btn-sm" type="submit">Save Changes</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ================= APP LOGINS ================= -->
        <div class="tab-pane fade" id="app_passwords" role="tabpanel" aria-labelledby="app-passwords-tab">

            <?php
            $app_logins = $pdo->query("SELECT * FROM app_passcodes ORDER BY app_name ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h4 class="text-light mb-0">App Logins</h4>

                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse"
                    data-bs-target="#addLogin">
                    + Add Login
                </button>
            </div>

            <div class="collapse mb-3" id="addLogin">
                <div class="card card-body">
                    <form method="POST" class="row g-2">
                        <input type="hidden" name="add_login" value="1">

                        <div class="col-12 col-md-4">
                            <label class="form-label">App Name</label>
                            <input type="text" name="app_name" class="form-control"
                                placeholder="OneSite, Powerhouse, etc" required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Username</label>
                            <input type="text" name="app_username" class="form-control" required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Password</label>
                            <input type="text" name="app_password" class="form-control" required>
                        </div>

                        <div class="col-12 d-grid">
                            <button class="btn btn-success" type="submit">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle">
                    <thead>
                        <tr>
                            <th>App</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th class="text-end" style="width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($app_logins)): ?>
                            <tr>
                                <td colspan="4" class="text-light">No logins saved</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($app_logins as $login): ?>
                                <?php
                                $id = (int) $login['id'];
                                $collapseId = "edit_login_$id";
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($login['app_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($login['app_username'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($login['app_password'] ?? '') ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                            <button class="btn btn-outline-primary btn-sm" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                                                Edit
                                            </button>

                                            <form method="POST" class="m-0" onsubmit="return confirm('Delete this login?')">
                                                <input type="hidden" name="delete_login" value="1">
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <tr class="collapse" id="<?= $collapseId ?>">
                                    <td colspan="4">
                                        <form method="POST" class="row g-2">
                                            <input type="hidden" name="update_login" value="1">
                                            <input type="hidden" name="id" value="<?= $id ?>">

                                            <div class="col-12 col-md-4">
                                                <label class="form-label">App Name</label>
                                                <input type="text" name="app_name" class="form-control" required
                                                    value="<?= htmlspecialchars($login['app_name'] ?? '') ?>">
                                            </div>

                                            <div class="col-12 col-md-4">
                                                <label class="form-label">Username</label>
                                                <input type="text" name="app_username" class="form-control" required
                                                    value="<?= htmlspecialchars($login['app_username'] ?? '') ?>">
                                            </div>

                                            <div class="col-12 col-md-4">
                                                <label class="form-label">Password</label>
                                                <input type="text" name="app_password" class="form-control" required
                                                    value="<?= htmlspecialchars($login['app_password'] ?? '') ?>">
                                            </div>

                                            <div class="col-12 d-grid">
                                                <button class="btn btn-success btn-sm" type="submit">Save Changes</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div>
</div>

<script>
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    const periodStartHidden = document.getElementById('period_start_hidden');
    const periodEndHidden = document.getElementById('period_end_hidden');
    const pdfStartHidden = document.getElementById('pdf_start_date');
    const pdfEndHidden = document.getElementById('pdf_end_date');

    function syncPeriodFields() {
        const startVal = startDateInput.value;
        const endVal = endDateInput.value;

        if (periodStartHidden) periodStartHidden.value = startVal || '';
        if (periodEndHidden) periodEndHidden.value = endVal || '';

        if (pdfStartHidden) pdfStartHidden.value = startVal || '';
        if (pdfEndHidden) pdfEndHidden.value = endVal || '';
    }

    function setEndDateFromStart() {
        if (!startDateInput.value) return;

        const start = new Date(startDateInput.value + 'T00:00:00');
        const end = new Date(start);

        end.setDate(start.getDate() + 6);

        const yyyy = end.getFullYear();
        const mm = String(end.getMonth() + 1).padStart(2, '0');
        const dd = String(end.getDate()).padStart(2, '0');

        endDateInput.value = `${yyyy}-${mm}-${dd}`;
        syncPeriodFields();
    }

    // When start date changes, calculate end date and sync hidden fields
    startDateInput.addEventListener('change', setEndDateFromStart);

    // On page load, if start_date is already filled (via querystring), compute end + sync
    document.addEventListener('DOMContentLoaded', () => {
        if (startDateInput.value) {
            setEndDateFromStart();
        } else {
            syncPeriodFields();
        }
    });
</script>
<script>
    let sortDirection = {};

    function sortTable(columnIndex) {
        const table = document.getElementById("employeesTable");
        const tbody = table.tBodies[0];
        const rows = Array.from(tbody.rows);

        // Toggle direction
        sortDirection[columnIndex] = !sortDirection[columnIndex];
        const direction = sortDirection[columnIndex] ? 1 : -1;

        rows.sort((a, b) => {
            let A = a.cells[columnIndex].innerText.toLowerCase();
            let B = b.cells[columnIndex].innerText.toLowerCase();

            // Numeric sort for PIN
            if (columnIndex === 1) {
                A = parseInt(A, 10);
                B = parseInt(B, 10);
            }

            if (A < B) return -1 * direction;
            if (A > B) return 1 * direction;
            return 0;
        });

        // Rebuild table
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));

        updateIndicators(columnIndex, direction);
    }

    function updateIndicators(activeIndex, direction) {
        document.querySelectorAll('.sort-indicator').forEach(el => el.textContent = '');
        const indicator = document.querySelectorAll('.sort-indicator')[activeIndex];
        indicator.textContent = direction === 1 ? ' ▲' : ' ▼';
    }

    function CopyTextBeltAPIKey() {
        const copyText = "089fcbbf8e498369811562800ba94222306a5f1fWQuAT5KRh6gNW5Y7jbaRjZCVH";
        navigator.clipboard.writeText(copyText)
            .catch(err => {
                console.error("Failed to copy: ", err);
            });
    }

    function togglePasscode(id) {
        const masked = document.getElementById('pc_mask_' + id);
        const plain = document.getElementById('pc_plain_' + id);

        if (!masked || !plain) return;

        const showing = masked.classList.contains('d-none');
        if (showing) {
            // hide
            masked.classList.remove('d-none');
            plain.classList.add('d-none');
        } else {
            // show
            masked.classList.add('d-none');
            plain.classList.remove('d-none');
        }
    }

    function togglePasscodeMobile(id) {
        const masked = document.getElementById('pc_mask_m_' + id);
        const plain = document.getElementById('pc_plain_m_' + id);
        if (!masked || !plain) return;

        const showing = masked.classList.contains('d-none');
        if (showing) { masked.classList.remove('d-none'); plain.classList.add('d-none'); }
        else { masked.classList.add('d-none'); plain.classList.remove('d-none'); }
    }
</script>
<?php include 'includes/footer.php'; ?>