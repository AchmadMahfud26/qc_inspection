<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login
require_login();

$page_title = "Laporan";
$extra_head_content = '';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

            <div class="d-flex justify-content-between align-items-center page-toolbar">
                <h3 class="page-main-title"><i class="fas fa-file-alt"></i> Laporan QC Inspection</h3>
            </div>

            <!-- Report Options -->
            <div class="row">
                <!-- Laporan Harian -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-calendar-day"></i> Laporan Harian
                        </div>
                        <div class="card-body">
                            <p class="card-text">Lihat dan download laporan inspeksi per hari dengan detail defect dan statistik.</p>
                            <form method="GET" action="daily_report.php" class="mt-3">
                                <div class="mb-3">
                                    <label for="daily_date" class="form-label">Pilih Tanggal:</label>
                                    <input type="date" class="form-control" id="daily_date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Lihat Laporan</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Laporan Mingguan -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-calendar-week"></i> Laporan Mingguan
                        </div>
                        <div class="card-body">
                            <p class="card-text">Analisa trend kualitas minggu ini dengan perbandingan per hari dan top defect.</p>
                            <form method="GET" action="weekly_report.php" class="mt-3">
                                <div class="mb-3">
                                    <label for="weekly_year" class="form-label">Tahun:</label>
                                    <input type="number" class="form-control" id="weekly_year" name="year" value="<?php echo date('Y'); ?>" min="2020" required>
                                </div>
                                <div class="mb-3">
                                    <label for="weekly_week" class="form-label">Minggu ke:</label>
                                    <input type="number" class="form-control" id="weekly_week" name="week" value="<?php echo date('W'); ?>" min="1" max="53" required>
                                </div>
                                <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> Lihat Laporan</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Laporan Bulanan -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-calendar"></i> Laporan Bulanan
                        </div>
                        <div class="card-body">
                            <p class="card-text">Laporan komprehensif bulan ini dengan grafik trend, pareto, dan ringkasan kualitas.</p>
                            <form method="GET" action="monthly_report.php" class="mt-3">
                                <div class="mb-3">
                                    <label for="monthly_year" class="form-label">Tahun:</label>
                                    <input type="number" class="form-control" id="monthly_year" name="year" value="<?php echo date('Y'); ?>" min="2020" required>
                                </div>
                                <div class="mb-3">
                                    <label for="monthly_month" class="form-label">Bulan:</label>
                                    <select class="form-select" id="monthly_month" name="month" required>
                                        <option value="1">Januari</option>
                                        <option value="2">Februari</option>
                                        <option value="3">Maret</option>
                                        <option value="4">April</option>
                                        <option value="5">Mei</option>
                                        <option value="6">Juni</option>
                                        <option value="7">Juli</option>
                                        <option value="8" <?php echo date('m') == 8 ? 'selected' : ''; ?>>Agustus</option>
                                        <option value="9">September</option>
                                        <option value="10">Oktober</option>
                                        <option value="11">November</option>
                                        <option value="12">Desember</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-eye"></i> Lihat Laporan</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Laporan Custom -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-warning text-dark">
                            <i class="fas fa-sliders-h"></i> Laporan Custom
                        </div>
                        <div class="card-body">
                            <p class="card-text">Buat laporan dengan filter sesuai kebutuhan (range tanggal, produk, jenis inspeksi).</p>
                            <form method="GET" action="custom_report.php" class="mt-3">
                                <div class="mb-3">
                                    <label for="custom_start" class="form-label">Tanggal Mulai:</label>
                                    <input type="date" class="form-control" id="custom_start" name="start_date" value="<?php echo date('Y-m-01'); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="custom_end" class="form-label">Tanggal Akhir:</label>
                                    <input type="date" class="form-control" id="custom_end" name="end_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-eye"></i> Lihat Laporan</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="card shadow-sm mt-5 border-0">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-history"></i> Laporan Terakhir</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted"><small><i class="fas fa-info-circle"></i> Laporan yang dibuat akan ditampilkan di sini untuk referensi cepat.</small></p>
                    <p class="text-center text-muted py-4">Belum ada laporan yang dibuat.</p>
                </div>
            </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
