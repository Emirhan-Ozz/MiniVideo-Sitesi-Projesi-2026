<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini Video Sitesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-play-circle-fill text-danger"></i> Mini Video
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <div class="navbar-nav ms-auto align-items-lg-center gap-1">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <a class="nav-link" href="admin.php">
                            <i class="bi bi-shield-fill text-warning"></i> Admin
                        </a>
                    <?php endif; ?>
                    <a class="nav-link" href="upload.php">
                        <i class="bi bi-cloud-upload"></i> Video Yükle
                    </a>
                    <a class="nav-link" href="playlist.php">
                        <i class="bi bi-bookmark-heart"></i> Favoriler
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i>
                        Çıkış <span class="text-warning">(<?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?>)</span>
                    </a>
                <?php else: ?>
                    <a class="nav-link" href="login.php">
                        <i class="bi bi-box-arrow-in-right"></i> Giriş
                    </a>
                    <a class="btn btn-danger btn-sm ms-2" href="register.php">
                        <i class="bi bi-person-plus"></i> Kayıt Ol
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
