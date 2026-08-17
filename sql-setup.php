<?php
/**
 * sql-setup.php
 * Setup script untuk insert master data inspection items
 * Akses: http://localhost/qc_inspection/sql-setup.php
 */

require_once 'config/config.php';
require_once 'config/db.php';

// Check if already setup
$check_sql = "SELECT COUNT(*) as total FROM inspection_items WHERE process_type = 'After Welding'";
$check_result = $db->query($check_sql);
$check_row = $check_result->fetch_assoc();

if ($check_row['total'] > 0) {
    echo '<div style="padding: 20px; font-family: Arial; background: #d4edda; color: #155724; border-radius: 4px;">';
    echo '<h2>✅ Master Data Sudah Ada</h2>';
    echo '<p>Inspection items untuk After Welding sudah disetup.</p>';
    echo '<p><a href="http://localhost/qc_inspection/inspection/after_welding/" style="color: #155724; font-weight: bold;">Klik di sini untuk membuka After Welding Inspection</a></p>';
    echo '</div>';
    exit;
}

// Insert dummy inspection items for After Welding
$insert_items = [
    ['After Welding', 'AW-001', 'Weld Appearance', 'Penampilan hasil las harus mulus tanpa cacat', 'Visual Inspection', 1],
    ['After Welding', 'AW-002', 'Weld Bead', 'Bentuk manik las harus teratur dan konsisten', 'Visual Inspection', 2],
    ['After Welding', 'AW-003', 'Weld Continuity', 'Kontinuitas las harus sempurna tanpa putus', 'Visual Inspection', 3],
    ['After Welding', 'AW-004', 'Weld Spatter', 'Percikan las harus minimal, tidak menempel pada produk', 'Visual Inspection', 4],
    ['After Welding', 'AW-005', 'Porosity', 'Pori pada las tidak boleh ada atau minimal', 'Visual/Radiographic', 5],
    ['After Welding', 'AW-006', 'Undercut', 'Undercut pada tepian las tidak boleh ada', 'Visual Inspection', 6],
    ['After Welding', 'AW-007', 'Crack', 'Retak pada sambungan las tidak diperbolehkan', 'Visual/Radiographic', 7],
    ['After Welding', 'AW-008', 'Overlap', 'Overlap pada las tidak boleh ada', 'Visual Inspection', 8],
    ['After Welding', 'AW-009', 'Lack of Fusion', 'Lack of fusion tidak boleh ada', 'Visual/Radiographic', 9],
    ['After Welding', 'AW-010', 'Penetration', 'Penetrasi las harus sempurna sesuai spesifikasi', 'Radiographic', 10],
    ['After Welding', 'AW-011', 'Length', 'Panjang produk sesuai dengan spesifikasi gambar', 'Measurement', 11],
    ['After Welding', 'AW-012', 'Width', 'Lebar produk sesuai dengan spesifikasi gambar', 'Measurement', 12],
    ['After Welding', 'AW-013', 'Height', 'Tinggi produk sesuai dengan spesifikasi gambar', 'Measurement', 13],
    ['After Welding', 'AW-014', 'Hole Position', 'Posisi hole sesuai dengan spesifikasi gambar', 'Measurement', 14],
    ['After Welding', 'AW-015', 'Diameter', 'Diameter sesuai dengan spesifikasi gambar', 'Measurement', 15],
    ['After Welding', 'AW-016', 'Flatness', 'Permukaan produk harus rata tanpa bengkok', 'Measurement', 16],
    ['After Welding', 'AW-017', 'Component Completeness', 'Semua komponen harus ada dan lengkap', 'Visual Inspection', 17],
    ['After Welding', 'AW-018', 'Bracket Position', 'Posisi bracket sesuai dengan gambar teknik', 'Visual Inspection', 18],
    ['After Welding', 'AW-019', 'Pipe Position', 'Posisi pipa sesuai dengan gambar teknik', 'Visual Inspection', 19],
    ['After Welding', 'AW-020', 'Fitting Position', 'Posisi fitting sesuai dengan gambar teknik', 'Visual Inspection', 20],
];

$success_count = 0;
$error_count = 0;

foreach ($insert_items as $item) {
    $process_type = $item[0];
    $item_code = $item[1];
    $item_name = $item[2];
    $standard = $item[3];
    $inspection_method = $item[4];
    $sequence = $item[5];
    
    $sql = "INSERT INTO inspection_items 
            (process_type, item_code, item_name, standard, inspection_method, sequence, status)
            VALUES ('$process_type', '$item_code', '$item_name', '$standard', '$inspection_method', $sequence, 'active')";
    
    if ($db->query($sql)) {
        $success_count++;
    } else {
        $error_count++;
        echo 'Error: ' . $db->error . '<br>';
    }
}

// Insert After Painting items
$insert_painting_items = [
    ['After Painting', 'AP-001', 'Paint Coverage', 'Lapisan cat harus merata di seluruh permukaan', 'Visual Inspection', 1],
    ['After Painting', 'AP-002', 'Paint Color', 'Warna cat sesuai dengan spesifikasi (RAL/Pantone)', 'Visual Inspection', 2],
    ['After Painting', 'AP-003', 'Paint Gloss', 'Tingkat kilap cat sesuai spesifikasi', 'Gloss Meter', 3],
    ['After Painting', 'AP-004', 'Paint Thickness', 'Ketebalan cat sesuai spesifikasi (min-max)', 'Thickness Meter', 4],
    ['After Painting', 'AP-005', 'Brush Marks', 'Tidak ada bekas kuas atau roll pada permukaan', 'Visual Inspection', 5],
    ['After Painting', 'AP-006', 'Drips & Runs', 'Tidak ada tetesan atau mengalir pada permukaan', 'Visual Inspection', 6],
    ['After Painting', 'AP-007', 'Sags', 'Tidak ada turun/menggelembung pada permukaan cat', 'Visual Inspection', 7],
    ['After Painting', 'AP-008', 'Adhesion', 'Daya lekat cat pada substrat harus baik', 'Adhesion Test', 8],
    ['After Painting', 'AP-009', 'Dirt & Dust', 'Tidak ada debu atau kotoran menempel pada cat', 'Visual Inspection', 9],
    ['After Painting', 'AP-010', 'Edge Coverage', 'Tepi dan corner harus tertutup cat dengan baik', 'Visual Inspection', 10],
];

foreach ($insert_painting_items as $item) {
    $process_type = $item[0];
    $item_code = $item[1];
    $item_name = $item[2];
    $standard = $item[3];
    $inspection_method = $item[4];
    $sequence = $item[5];
    
    $sql = "INSERT INTO inspection_items 
            (process_type, item_code, item_name, standard, inspection_method, sequence, status)
            VALUES ('$process_type', '$item_code', '$item_name', '$standard', '$inspection_method', $sequence, 'active')";
    
    if ($db->query($sql)) {
        $success_count++;
    } else {
        $error_count++;
    }
}

// Insert Final Check items
$insert_final_items = [
    ['Final Check', 'FC-001', 'Visual Inspection', 'Inspeksi visual overall produk', 'Visual Inspection', 1],
    ['Final Check', 'FC-002', 'Dimension Check', 'Pengecekan dimensi produk sesuai gambar teknik', 'Measurement', 2],
    ['Final Check', 'FC-003', 'Weight Check', 'Pengecekan berat produk', 'Weighing', 3],
    ['Final Check', 'FC-004', 'Leakage Test', 'Pengecekan kebocoran jika ada pressure area', 'Leak Test', 4],
    ['Final Check', 'FC-005', 'Surface Inspection', 'Inspeksi permukaan produk (karat, cacat, dll)', 'Visual Inspection', 5],
    ['Final Check', 'FC-006', 'Documentation', 'Kelengkapan dokumentasi (gambar, spec, dll)', 'Document Check', 6],
    ['Final Check', 'FC-007', 'Packaging', 'Pengecekan packaging dan label', 'Visual Inspection', 7],
];

foreach ($insert_final_items as $item) {
    $process_type = $item[0];
    $item_code = $item[1];
    $item_name = $item[2];
    $standard = $item[3];
    $inspection_method = $item[4];
    $sequence = $item[5];
    
    $sql = "INSERT INTO inspection_items 
            (process_type, item_code, item_name, standard, inspection_method, sequence, status)
            VALUES ('$process_type', '$item_code', '$item_name', '$standard', '$inspection_method', $sequence, 'active')";
    
    if ($db->query($sql)) {
        $success_count++;
    } else {
        $error_count++;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Master Data - QC INSPECTION</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: #f5f5f5; padding: 40px 20px;">

<div class="container">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title mb-4"><i class="fas fa-check-circle"></i> Setup Master Data Selesai ✅</h2>
                    
                    <?php if ($error_count === 0): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <h4 class="alert-heading">Sukses!</h4>
                            <p>Master data telah berhasil ditambahkan ke database.</p>
                            <hr>
                            <p class="mb-0">Total data inserted: <strong><?php echo $success_count; ?></strong></p>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <h4 class="alert-heading">Peringatan!</h4>
                            <p>Ada beberapa error saat insert data.</p>
                            <hr>
                            <p class="mb-0">Success: <strong><?php echo $success_count; ?></strong> | Error: <strong><?php echo $error_count; ?></strong></p>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <h5 class="mt-5 mb-3">Data yang ditambahkan:</h5>
                    <ul>
                        <li><strong>After Welding</strong>: 20 inspection items</li>
                        <li><strong>After Painting</strong>: 10 inspection items</li>
                        <li><strong>Final Check</strong>: 7 inspection items</li>
                    </ul>
                    <p class="text-muted">Total: 37 inspection items</p>

                    <hr class="my-4">

                    <h5 class="mb-3">Langkah Selanjutnya:</h5>
                    <ol>
                        <li>Kunjungi halaman <strong>After Welding Inspection</strong></li>
                        <li>Klik tombol <strong>"Tambah Inspeksi Baru"</strong></li>
                        <li>Isi form dengan data produk dan inspeksi items</li>
                        <li>Upload foto defect jika ada (opsional)</li>
                        <li>Simpan data</li>
                    </ol>

                    <hr class="my-4">

                    <div class="d-flex gap-2">
                        <a href="http://localhost/qc_inspection/inspection/after_welding/" class="btn btn-primary btn-lg">
                            <i class="fas fa-fire-extinguisher"></i> Buka After Welding
                        </a>
                        <a href="http://localhost/qc_inspection/" class="btn btn-secondary btn-lg">
                            <i class="fas fa-home"></i> Kembali ke Dashboard
                        </a>
                    </div>

                </div>
            </div>

            <div class="mt-4 p-3 bg-light rounded text-muted small">
                <strong>Catatan:</strong> File setup ini dapat dihapus setelah master data berhasil ditambahkan.
                <br>Lokasi file: <code>sql-setup.php</code>
            </div>
        </div>
    </div>
</div>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$db->close();
?>
