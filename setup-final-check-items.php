<?php
session_start();
require_once './config/config.php';
require_once './config/db.php';

// Check login
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Check if already run
$check_sql = "SELECT COUNT(*) as total FROM inspection_items WHERE process_type = 'Final Check'";
$check_result = $db->query($check_sql);
$check_row = $check_result->fetch_assoc();

if ($check_row['total'] > 0) {
    $message = "✅ Final Check inspection items sudah ada (" . $check_row['total'] . " items)";
    $status = "success";
} else {
    // Insert Final Check inspection items
    $final_check_items = [
        ['item_name' => 'Dimensional Check', 'standard' => 'Sesuai drawing', 'inspection_method' => 'Measure dengan calipers', 'sequence' => 1],
        ['item_name' => 'Surface Finish', 'standard' => 'Smooth & no defects', 'inspection_method' => 'Visual & Touch', 'sequence' => 2],
        ['item_name' => 'Paint Coverage', 'standard' => '100% coverage', 'inspection_method' => 'Visual', 'sequence' => 3],
        ['item_name' => 'Paint Adhesion', 'standard' => 'No peeling', 'inspection_method' => 'Cross-hatch test', 'sequence' => 4],
        ['item_name' => 'Component Assembly', 'standard' => 'All components present', 'inspection_method' => 'Visual', 'sequence' => 5],
        ['item_name' => 'Welding Quality', 'standard' => 'No visible defects', 'inspection_method' => 'Visual', 'sequence' => 6],
        ['item_name' => 'Documentation', 'standard' => 'Complete & correct', 'inspection_method' => 'Document review', 'sequence' => 7],
        ['item_name' => 'Serial Number Mark', 'standard' => 'Clear & legible', 'inspection_method' => 'Visual', 'sequence' => 8],
        ['item_name' => 'Packaging Quality', 'standard' => 'Proper packaging', 'inspection_method' => 'Visual', 'sequence' => 9],
        ['item_name' => 'Weight Check', 'standard' => 'Within tolerance', 'inspection_method' => 'Weigh with scale', 'sequence' => 10],
    ];

    $inserted = 0;
    foreach ($final_check_items as $item) {
        $sql = "INSERT INTO inspection_items 
                (item_name, process_type, standard, inspection_method, sequence, status) 
                VALUES 
                ('" . addslashes($item['item_name']) . "', 'Final Check', '" . addslashes($item['standard']) . "', 
                 '" . addslashes($item['inspection_method']) . "', " . $item['sequence'] . ", 'active')";
        
        if ($db->query($sql)) {
            $inserted++;
        }
    }

    if ($inserted === count($final_check_items)) {
        $message = "✅ Berhasil menambahkan " . $inserted . " Final Check inspection items";
        $status = "success";
    } else {
        $message = "⚠️ Hanya " . $inserted . " dari " . count($final_check_items) . " items berhasil ditambahkan";
        $status = "warning";
    }
}
?>

<?php include './includes/header.php'; ?>

<div class="container-fluid pt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>Setup Final Check Inspection Items</h2>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="alert alert-<?php echo $status; ?>">
                <i class="fas fa-<?php echo ($status === 'success') ? 'check-circle' : 'info-circle'; ?>"></i> 
                <?php echo $message; ?>
            </div>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Final Check Inspection Items (10 items)</h5>
                </div>
                <div class="card-body">
                    <?php
                    $items_sql = "SELECT id, item_name, standard, inspection_method FROM inspection_items 
                                 WHERE process_type = 'Final Check' ORDER BY sequence";
                    $items_result = $db->query($items_sql);
                    $items = $items_result->fetch_all(MYSQLI_ASSOC);
                    ?>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="30%">Item Name</th>
                                    <th width="30%">Standard</th>
                                    <th width="35%">Inspection Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $key => $item): ?>
                                    <tr>
                                        <td><?php echo $key + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['standard']); ?></td>
                                        <td><?php echo htmlspecialchars($item['inspection_method']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Kembali ke Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>/inspection/final_check/" class="btn btn-success">
                    <i class="fas fa-arrow-right"></i> Ke Final Check Inspection
                </a>
            </div>
        </div>
    </div>
</div>

<?php include './includes/footer.php'; ?>
