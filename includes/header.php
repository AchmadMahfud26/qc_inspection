<?php
// includes/header.php
// Basic header and navbar for QC INSPECTION

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = current_user();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QC INSPECTION - Quality Control Inspection & Traceability System</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- App styles -->
    <link href="/qc_inspection/assets/css/style.css" rel="stylesheet">

</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <button class="btn btn-outline-light me-2 d-md-none" id="mobileSidebarOpen" title="Open sidebar"><i class="fa fa-bars"></i></button>
    <button class="btn btn-outline-light me-2 d-none d-md-inline" id="sidebarToggle" title="Toggle sidebar"><i class="fa fa-bars"></i></button>
    <a class="navbar-brand" href="/qc_inspection/index.php">QC INSPECTION</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <?php if ($user): ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userMenu" role="button" data-bs-toggle="dropdown">
                    <i class="fa fa-user"></i> <?php echo esc($user['name']); ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                    <li><a class="dropdown-item" href="/qc_inspection/index.php">Dashboard</a></li>
                    <li><a class="dropdown-item" href="/qc_inspection/admin/users/index.php">Manajemen User</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/qc_inspection/auth/logout.php">Logout</a></li>
                </ul>
            </li>
        <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="/qc_inspection/auth/login.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid content-wrapper">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
