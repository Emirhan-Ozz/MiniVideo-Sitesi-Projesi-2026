<?php
require_once __DIR__ . '/db.php';
include_once __DIR__ . '/header.php';

// Kategorileri çek
$catResult = $conn->query("SELECT * FROM Categories ORDER BY CategoryName");
$categories = $catResult->fetch_all(MYSQLI_ASSOC);

// Kategori filtresi
$selectedCat = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Videoları çek (like sayısı alt sorgu ile)
if ($selectedCat > 0) {
    $stmt = $conn->prepare("
        SELECT v.*, c.CategoryName, u.Username,
               (SELECT COUNT(*) FROM Likes l WHERE l.VideoID = v.VideoID) AS LikeCount
        FROM Videos v
        LEFT JOIN Categories c ON v.CategoryID = c.CategoryID
        JOIN Users u ON v.UploaderID = u.UserID
        WHERE v.CategoryID = ?
        ORDER BY v.CreatedAt DESC
    ");
    $stmt->bind_param('i', $selectedCat);
    $stmt->execute();
    $videos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query("
        SELECT v.*, c.CategoryName, u.Username,
               (SELECT COUNT(*) FROM Likes l WHERE l.VideoID = v.VideoID) AS LikeCount
        FROM Videos v
        LEFT JOIN Categories c ON v.CategoryID = c.CategoryID
        JOIN Users u ON v.UploaderID = u.UserID
        ORDER BY v.CreatedAt DESC
    ");
    $videos = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container mt-4">

    <!-- Başlık ve Filtre -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h1 class="page-title mb-0">
            <i class="bi bi-collection-play text-danger"></i> Videolar
            <span class="text-muted fs-5 fw-normal">(<?= count($videos) ?>)</span>
        </h1>
        <form method="GET" class="d-flex gap-2 align-items-center category-filter">
            <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">Tüm Kategoriler</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['CategoryID'] ?>"
                        <?= $selectedCat === (int)$cat['CategoryID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['CategoryName'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($selectedCat > 0): ?>
                <a href="index.php" class="btn btn-sm btn-outline-secondary text-nowrap">
                    <i class="bi bi-x"></i> Temizle
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Video Kartları -->
    <?php if (empty($videos)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-camera-video-off" style="font-size:3.5rem; opacity:0.4;"></i>
            <h5 class="mt-3">Bu kategoride video bulunamadı.</h5>
            <a href="index.php" class="btn btn-outline-secondary mt-2">Tüm videolara bak</a>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-4">
            <?php foreach ($videos as $video): ?>
                <div class="col">
                    <a href="video_detail.php?id=<?= $video['VideoID'] ?>" class="text-decoration-none text-dark">
                        <div class="card video-card h-100">
                            <?php if (!empty($video['ThumbnailURL'])): ?>
                                <img src="<?= htmlspecialchars($video['ThumbnailURL'], ENT_QUOTES, 'UTF-8') ?>"
                                     class="card-img-top"
                                     alt="<?= htmlspecialchars($video['Title'], ENT_QUOTES, 'UTF-8') ?>"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div class="thumbnail-placeholder" style="display:none;">
                                    <i class="bi bi-play-circle text-white" style="font-size:3rem;opacity:.6;"></i>
                                </div>
                            <?php else: ?>
                                <div class="thumbnail-placeholder">
                                    <i class="bi bi-play-circle text-white" style="font-size:3rem;opacity:.6;"></i>
                                </div>--
                            <?php endif; ?>
                            <div class="card-body">
                                <h6 class="card-title">
                                    <?= htmlspecialchars($video['Title'], ENT_QUOTES, 'UTF-8') ?>
                                </h6>
                                <div class="card-stats d-flex justify-content-between align-items-center mt-2">
                                    <span>
                                        <?php if ($video['CategoryName']): ?>
                                            <span class="badge bg-secondary badge-category">
                                                <?= htmlspecialchars($video['CategoryName'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <span>
                                        <i class="bi bi-eye"></i> <?= number_format($video['ViewCount']) ?>
                                        &nbsp;
                                        <i class="bi bi-heart-fill text-danger"></i> <?= number_format($video['LikeCount']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php include_once __DIR__ . '/footer.php'; ?>
