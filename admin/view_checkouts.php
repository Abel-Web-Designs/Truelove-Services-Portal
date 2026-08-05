<?php
require_once '../includes/header.php';

if (getUserRole() !== 'admin') {
    echo '<div class="alert alert-danger m-3">Access denied. Admins only.</div>';
    exit;
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)($_POST['delete_id'] ?? 0);

    if ($deleteId > 0) {
        $stmt = $pdo->prepare("DELETE FROM equipment_checkout WHERE id = ?");
        $stmt->execute([$deleteId]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all equipment logs
$logs = $pdo->query("
    SELECT *
    FROM equipment_checkout
    ORDER BY date DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function renderBadges($value, $emptyText = 'None listed') {
    $value = trim((string)$value);

    if ($value === '') {
        return '<span class="text-light opacity-75">' . e($emptyText) . '</span>';
    }

    $parts = preg_split('/\r\n|\r|\n|,/', $value);
    $parts = array_filter(array_map('trim', $parts));

    if (empty($parts)) {
        return '<span class="text-light opacity-75">' . e($emptyText) . '</span>';
    }

    $html = '';
    foreach ($parts as $part) {
        $html .= '<span class="badge text-bg-secondary me-1 mb-1">' . e($part) . '</span>';
    }

    return $html;
}
?>

<div class="container py-4" data-bs-theme="dark">

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h2 class="text-light mb-1">Equipment Checkout Logs</h2>
            <div class="text-light">
                View, search, and manage submitted equipment checkout records.
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <span class="badge rounded-pill text-bg-primary px-3 py-2 fs-6">
                Total Logs: <?= count($logs) ?>
            </span>
        </div>
    </div>

    <div class="card bg-dark border-secondary shadow-sm">
        <div class="card-header bg-black border-secondary">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-lg-6">
                    <label for="logSearch" class="form-label text-light mb-1">Search Logs</label>
                    <input
                        type="text"
                        id="logSearch"
                        class="form-control bg-dark text-light border-secondary"
                        placeholder="Search by crew, truck, trailer, lead, equipment..."
                    >
                </div>

                <div class="col-12 col-lg-6">
                    <div class="text-light small">
                        Tip: click <strong>View Details</strong> to expand each log.
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="p-4">
                    <div class="alert alert-info mb-0">No logs available.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0" id="logsTable">
                        <thead class="table-secondary text-dark">
                            <tr>
                                <th style="min-width: 130px;">Date</th>
                                <th style="min-width: 130px;">Crew</th>
                                <th style="min-width: 140px;">Truck</th>
                                <th style="min-width: 140px;">Trailer</th>
                                <th style="min-width: 160px;">Crew Lead</th>
                                <th style="min-width: 180px;">Quick Summary</th>
                                <th style="width: 210px;" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $index => $log): ?>
                                <?php
                                    $collapseId = 'logDetails' . (int)$log['id'];

                                    $summaryParts = [];
                                    if (!empty(trim((string)$log['mowers'])))   $summaryParts[] = 'Mowers';
                                    if (!empty(trim((string)$log['trimmers']))) $summaryParts[] = 'Trimmers';
                                    if (!empty(trim((string)$log['blowers'])))  $summaryParts[] = 'Blowers';
                                    if (!empty(trim((string)$log['other_equipment']))) $summaryParts[] = 'Other';

                                    $summaryText = !empty($summaryParts) ? implode(', ', $summaryParts) : 'No equipment listed';

                                    $searchBlob = strtolower(implode(' ', [
                                        $log['date'] ?? '',
                                        $log['mowing_crew'] ?? '',
                                        $log['truck'] ?? '',
                                        $log['trailer'] ?? '',
                                        $log['crew_lead'] ?? '',
                                        $log['crew_members'] ?? '',
                                        $log['mowers'] ?? '',
                                        $log['trimmers'] ?? '',
                                        $log['blowers'] ?? '',
                                        $log['other_equipment'] ?? ''
                                    ]));
                                ?>

                                <tr class="log-row" data-search="<?= e($searchBlob) ?>">
                                    <td>
                                        <div class="fw-semibold text-light">
                                            <?= date('M j, Y', strtotime($log['date'])) ?>
                                        </div>
                                        <div class="small text-light opacity-75">
                                            ID #<?= (int)$log['id'] ?>
                                        </div>
                                    </td>

                                    <td class="text-light"><?= e($log['mowing_crew']) ?: '<span class="opacity-75">—</span>' ?></td>
                                    <td class="text-light"><?= e($log['truck']) ?: '<span class="opacity-75">—</span>' ?></td>
                                    <td class="text-light"><?= e($log['trailer']) ?: '<span class="opacity-75">—</span>' ?></td>
                                    <td class="text-light"><?= e($log['crew_lead']) ?: '<span class="opacity-75">—</span>' ?></td>

                                    <td>
                                        <span class="badge text-bg-info"><?= e($summaryText) ?></span>
                                    </td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end flex-wrap gap-2">
                                            <button
                                                class="btn btn-sm btn-outline-light"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#<?= e($collapseId) ?>"
                                                aria-expanded="false"
                                                aria-controls="<?= e($collapseId) ?>"
                                            >
                                                View Details
                                            </button>

                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this entry?');" class="d-inline">
                                                <input type="hidden" name="delete_id" value="<?= (int)$log['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <tr class="details-row">
                                    <td colspan="7" class="p-0 border-0">
                                        <div class="collapse" id="<?= e($collapseId) ?>">
                                            <div class="bg-black border-top border-secondary p-3 p-lg-4">
                                                <div class="row g-4">

                                                    <div class="col-12 col-xl-4">
                                                        <div class="card bg-dark border-secondary h-100">
                                                            <div class="card-header border-secondary">
                                                                <strong class="text-light">Crew Info</strong>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="mb-3">
                                                                    <div class="small text-uppercase text-light opacity-75">Mowing Crew</div>
                                                                    <div class="text-light fw-semibold"><?= e($log['mowing_crew']) ?: '—' ?></div>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <div class="small text-uppercase text-light opacity-75">Crew Lead</div>
                                                                    <div class="text-light fw-semibold"><?= e($log['crew_lead']) ?: '—' ?></div>
                                                                </div>

                                                                <div>
                                                                    <div class="small text-uppercase text-light opacity-75">Crew Members</div>
                                                                    <div class="text-light"><?= nl2br(e($log['crew_members'])) ?: '—' ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-xl-4">
                                                        <div class="card bg-dark border-secondary h-100">
                                                            <div class="card-header border-secondary">
                                                                <strong class="text-light">Vehicle Info</strong>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="mb-3">
                                                                    <div class="small text-uppercase text-light opacity-75">Truck</div>
                                                                    <div class="text-light fw-semibold"><?= e($log['truck']) ?: '—' ?></div>
                                                                </div>

                                                                <div>
                                                                    <div class="small text-uppercase text-light opacity-75">Trailer</div>
                                                                    <div class="text-light fw-semibold"><?= e($log['trailer']) ?: '—' ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-xl-4">
                                                        <div class="card bg-dark border-secondary h-100">
                                                            <div class="card-header border-secondary">
                                                                <strong class="text-light">Equipment Summary</strong>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="mb-3">
                                                                    <div class="small text-uppercase text-light opacity-75 mb-1">Mowers</div>
                                                                    <?= renderBadges($log['mowers'], 'No mowers listed') ?>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <div class="small text-uppercase text-light opacity-75 mb-1">Trimmers</div>
                                                                    <?= renderBadges($log['trimmers'], 'No trimmers listed') ?>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <div class="small text-uppercase text-light opacity-75 mb-1">Blowers</div>
                                                                    <?= renderBadges($log['blowers'], 'No blowers listed') ?>
                                                                </div>

                                                                <div>
                                                                    <div class="small text-uppercase text-light opacity-75 mb-1">Other Equipment</div>
                                                                    <?= renderBadges($log['other_equipment'], 'No other equipment listed') ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div id="noResultsMessage" class="p-4 text-center text-light d-none">
                    No matching logs found.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('logSearch');
    const rows = document.querySelectorAll('#logsTable tbody tr.log-row');
    const noResults = document.getElementById('noResultsMessage');

    if (!searchInput || !rows.length) return;

    searchInput.addEventListener('input', function () {
        const term = this.value.trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach(function (row) {
            const detailRow = row.nextElementSibling;
            const haystack = row.getAttribute('data-search') || '';
            const match = haystack.includes(term);

            row.style.display = match ? '' : 'none';
            if (detailRow && detailRow.classList.contains('details-row')) {
                detailRow.style.display = match ? '' : 'none';
            }

            if (match) visibleCount++;
        });

        if (noResults) {
            noResults.classList.toggle('d-none', visibleCount !== 0);
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>