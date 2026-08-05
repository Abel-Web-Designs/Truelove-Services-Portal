<?php
session_start();
require 'includes/db.php';
require 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $action = $_POST['action'];
    $id = intval($_POST['request_id']);

    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM parts_list WHERE id = ?");
        $stmt->execute([$id]);
    }
}

$types = ['Truck','Trailer','Skidsteer','Loader','Mower','Trimmer','Blower'];

$parts = $pdo->query("SELECT id, name, type_new, part_number FROM parts_list ORDER BY type_new, name")->fetchAll();
?>

<div class="container mt-4 text-light">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="mb-0">Parts List</h1>

        <!-- ================= ADD PART MODULE (BUTTON + DROPDOWN) ================= -->
        <div class="btn-group">
            <button
                type="button"
                class="btn btn-success"
                data-bs-toggle="modal"
                data-bs-target="#addPartModal"
                data-prefill-type=""
            >
                + Add Part
            </button>

            <button
                type="button"
                class="btn btn-success dropdown-toggle dropdown-toggle-split"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <span class="visually-hidden">Toggle Dropdown</span>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Add Part by Type</h6></li>
                <?php foreach ($types as $t): ?>
                    <li>
                        <button
                            type="button"
                            class="dropdown-item"
                            data-bs-toggle="modal"
                            data-bs-target="#addPartModal"
                            data-prefill-type="<?= htmlspecialchars($t) ?>"
                        >
                            Add <?= htmlspecialchars($t) ?> Part
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php if (empty($parts)): ?>
        <p class="text-light text-center">No parts found</p>
    <?php else: ?>
        <?php
        $currentType = null;
        foreach ($parts as $item):
            if ($item['type_new'] !== $currentType):
                if ($currentType !== null) echo '</tbody></table></div>';
                $currentType = $item['type_new'];
        ?>
            <h3 class="mt-4 text-light"><?= htmlspecialchars($currentType) ?></h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th class="text-nowrap">Part #</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
        <?php endif; ?>

        <tr>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td><?= htmlspecialchars($item['part_number'] ?? '') ?></td>
            <td>
                <form method="POST" class="d-flex justify-content-center gap-2 flex-wrap">
                    <input type="hidden" name="request_id" value="<?= $item['id'] ?>">
                    <button name="action" value="delete" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </td>
        </tr>

        <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- ================= ADD PART MODAL ================= -->
<div class="modal fade" id="addPartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Create New Part</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="addPartForm">
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="addPartError"></div>
                    <div class="alert alert-success d-none" id="addPartSuccess"></div>

                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type_new" id="type_new" class="form-select" required>
                            <option value="" disabled selected>Select type...</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Part Name</label>
                        <input type="text" name="name" id="name" class="form-control" maxlength="255" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Part Number (optional)</label>
                        <input type="text" name="part_number" id="part_number" class="form-control" maxlength="100">
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="addPartSubmitBtn">Save Part</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const modalEl = document.getElementById('addPartModal');
    const form = document.getElementById('addPartForm');
    const submitBtn = document.getElementById('addPartSubmitBtn');

    const typeSelect = document.getElementById('type_new');
    const nameInput = document.getElementById('name');
    const partNumberInput = document.getElementById('part_number');

    const errBox = document.getElementById('addPartError');
    const okBox  = document.getElementById('addPartSuccess');

    function showError(msg) {
        okBox.classList.add('d-none');
        errBox.textContent = msg;
        errBox.classList.remove('d-none');
    }
    function showSuccess(msg) {
        errBox.classList.add('d-none');
        okBox.textContent = msg;
        okBox.classList.remove('d-none');
    }
    function resetAlerts() {
        errBox.classList.add('d-none');
        okBox.classList.add('d-none');
        errBox.textContent = '';
        okBox.textContent = '';
    }

    // Prefill type when opening modal from dropdown
    modalEl.addEventListener('show.bs.modal', (event) => {
        resetAlerts();

        const trigger = event.relatedTarget;
        const prefillType = trigger?.getAttribute('data-prefill-type') || '';

        // Reset form each open
        form.reset();

        if (prefillType) {
            typeSelect.value = prefillType;
        } else {
            typeSelect.value = "";
        }

        // Focus name field
        setTimeout(() => nameInput.focus(), 150);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        resetAlerts();

        const type_new = typeSelect.value.trim();
        const name = nameInput.value.trim();
        const part_number = partNumberInput.value.trim();

        if (!type_new) return showError("Please select a type.");
        if (!name) return showError("Please enter a part name.");

        submitBtn.disabled = true;
        submitBtn.textContent = "Saving...";

        try {
            const res = await fetch('parts_create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type_new, name, part_number })
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok || !data.ok) {
                showError(data.error || "Failed to create part.");
            } else {
                showSuccess("Part created! Reloading...");
                setTimeout(() => window.location.reload(), 600);
            }
        } catch (err) {
            showError("Network error. Please try again.");
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = "Save Part";
        }
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
