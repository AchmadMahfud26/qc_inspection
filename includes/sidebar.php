<?php
// includes/sidebar.php
// Main sidebar navigation
$user = current_user();
?>
<div class="sidebar-container bg-dark text-white vh-100 p-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h6 class="text-uppercase mb-0">QC INSPECTION</h6>
            <small class="text-muted">QC & Traceability</small>
        </div>
        <button class="btn btn-sm btn-outline-light d-md-none" id="mobileSidebarClose" title="Close sidebar"><i class="fa fa-times"></i></button>
    </div>

    <ul class="nav nav-pills flex-column">
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/index.php"><i class="fa fa-tachometer-alt"></i> <span class="nav-label ms-2">Dashboard</span></a></li>
        <li class="nav-item mt-3"><strong class="text-muted ps-2">Master Data</strong></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/admin/products/index.php"><i class="fa fa-boxes"></i> <span class="nav-label ms-2">Produk</span></a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/admin/customers/index.php"><i class="fa fa-building"></i> <span class="nav-label ms-2">Customer</span></a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/admin/defects/index.php"><i class="fa fa-ban"></i> <span class="nav-label ms-2">Defect</span></a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/admin/inspection_items/index.php"><i class="fa fa-list"></i> <span class="nav-label ms-2">Inspection Item</span></a></li>

        <li class="nav-item mt-3"><strong class="text-muted ps-2">Inspection</strong></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/inspection/after_welding/index.php"><i class="fa fa-fire-extinguisher"></i> <span class="nav-label ms-2">After Welding</span></a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/inspection/after_painting/index.php"><i class="fa fa-paint-roller"></i> <span class="nav-label ms-2">After Painting</span></a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/inspection/final_check/index.php"><i class="fa fa-check-square"></i> <span class="nav-label ms-2">Final Check</span></a></li>

        <li class="nav-item mt-3"><strong class="text-muted ps-2">Data & Analysis</strong></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/data_inspection.php"><i class="fa fa-table"></i> <span class="nav-label ms-2">Data Inspection</span></a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/analysis/defect_analysis.php"><i class="fa fa-bug"></i> <span class="nav-label ms-2">Analisa Defect</span></a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/analysis/product_analysis.php"><i class="fa fa-boxes"></i> <span class="nav-label ms-2">Analisa Produk</span></a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/analysis/process_analysis.php"><i class="fa fa-cog"></i> <span class="nav-label ms-2">Analisa Proses</span></a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/analysis/pareto.php"><i class="fa fa-chart-line"></i> <span class="nav-label ms-2">Pareto Defect</span></a></li>

        <li class="nav-item mt-3"><strong class="text-muted ps-2">Lainnya</strong></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/reports/index.php"><i class="fa fa-file-alt"></i> <span class="nav-label ms-2">Report</span></a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/qc_inspection/auth/logout.php"><i class="fa fa-sign-out-alt"></i> <span class="nav-label ms-2">Logout</span></a></li>
    </ul>

    <div class="mt-4 d-none d-md-block text-center">
        <small class="text-muted">Toggle</small>
        <div class="mt-2">
            <button class="btn btn-sm btn-outline-light" id="sidebarMiniToggle" title="Collapse sidebar"><i class="fa fa-angle-double-left"></i></button>
        </div>
    </div>

</div>