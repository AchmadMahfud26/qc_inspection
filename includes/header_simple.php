<?php
// includes/header_simple.php
// Minimal header for auth pages (login) - no navbar, no sidebar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QC INSPECTION - Login</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- App styles -->
    <link href="/qc_inspection/assets/css/style.css" rel="stylesheet">

</head>
<body>
<div class="auth-wrapper">
    <div class="container">