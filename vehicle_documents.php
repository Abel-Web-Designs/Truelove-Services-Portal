<?php
session_start();
require 'includes/db.php';
require 'includes/header.php';

$equipment = $pdo->query("
    SELECT id, name, type_new, serial_number, purchase_date 
    FROM equipment 
    WHERE type_new = 'Truck' OR type_new = 'Trailer' 
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4 text-light">
    <h1 class="mb-4">Vehicle Documents</h1>

    <?php
    if (empty($equipment)) {
        echo '<p class="text-muted text-center">No equipment found.</p>';
    } else {
        $currenttype_new = null;

        foreach ($equipment as $item):
            // Start a new section when type_new changes
            if ($item['type_new'] !== $currenttype_new):
                // Close previous table if not first section
                if ($currenttype_new !== null) echo '</tbody></table></div>';

                $currenttype_new = $item['type_new'];
                ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
            <?php endif; ?>

            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><a href="vehicle_document_view.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-primary">View</a></td>
            </tr>

        <?php endforeach; ?>

        </tbody>
        </table>
        </div>
    <?php } ?>
</div>

<?php require 'includes/footer.php'; ?>
