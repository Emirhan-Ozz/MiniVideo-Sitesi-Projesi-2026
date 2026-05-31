<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli — Mini Video Sitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="admin.php">
            <i class="bi bi-shield-fill text-warning"></i> Admin Paneli
        </a>
        <div class="navbar-nav ms-auto d-flex flex-row align-items-center gap-3">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active text-white' : '' ?>"
               href="admin.php">
                <i class="bi bi-collection-play"></i> Videolar
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'stats.php' ? 'active text-white' : '' ?>"
               href="stats.php">
                <i class="bi bi-bar-chart-line"></i> İstatistikler
            </a>
            <span class="nav-link text-muted">|</span>
            <a class="nav-link" href="index.php">
                <i class="bi bi-house"></i> Siteye Dön
            </a>
            <span class="nav-link text-warning small">
                <i class="bi bi-person-circle"></i>
                <?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </span>
            <a class="btn btn-sm btn-outline-danger" href="logout.php">
                <i class="bi bi-box-arrow-right"></i> Çıkış
            </a>
        </div>
    </div>
</nav>
<div class="container-fluid px-4 py-4">
