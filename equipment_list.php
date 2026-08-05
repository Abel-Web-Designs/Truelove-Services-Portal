<?php
session_start();
require 'includes/db.php';
require 'includes/header.php';

$equipment = $pdo->query("SELECT id, name, type_new, serial_number, purchase_date FROM equipment ORDER BY type_new, name")->fetchAll();
?>

<div class="container mt-4 text-light">
    <h1 class="mb-4">Equipment List</h1>

    <a href="equipment_add.php" class="btn btn-success mb-3">+ Add Equipment</a>

    <?php
    if (empty($equipment)) {
        echo '<p class="text-muted text-center">No equipment found.</p>';
    } else {
        $currentType = null;

        foreach ($equipment as $item):
            // Start a new section when type changes
            if ($item['type_new'] !== $currentType):
                // Close previous table if not first section
                if ($currentType !== null) echo '</tbody></table></div>';

                $currentType = $item['type_new'];
                ?>
                <h3 class="mt-4 text-light"><?= htmlspecialchars($currentType) ?></h3>
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
                <td><a href="equipment_view.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-primary">View</a></td>
            </tr>

        <?php endforeach; ?>

        </tbody>
        </table>
        </div>
    <?php } ?>
</div>

<?php require 'includes/footer.php'; ?>
