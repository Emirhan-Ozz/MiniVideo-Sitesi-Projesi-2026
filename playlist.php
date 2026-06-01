<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_video'])) {
    $vidId = (int)$_POST['remove_video'];
    $del = $conn->prepare("DELETE FROM Playlists WHERE UserID = ? AND VideoID = ?");
    $del->bind_param('ii', $_SESSION['user_id'], $vidId);
    $del->execute();
    header('Location: playlist.php?removed=1');
    exit;
}

$stmt = $conn->prepare("
    SELECT v.*, c.CategoryName,
           (SELECT COUNT(*) FROM Likes l WHERE l.VideoID = v.VideoID) AS LikeCount,
           p.AddedAt
    FROM Videos v
    JOIN Playlists p ON v.VideoID = p.VideoID
    LEFT JOIN Categories c ON v.CategoryID = c.CategoryID
    WHERE p.UserID = ?
    ORDER BY p.AddedAt DESC
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$favorites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include_once __DIR__ . '/header.php';
?>

<div class="container mt-4">
    <h1 class="page-title mb-4">
        <i class="bi bi-bookmark-heart text-warning"></i> Favori Listelerim
    </h1>

    <?php if (isset($_GET['removed'])): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="bi bi-bookmark-x"></i> Video favorilerden çıkarıldı.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($favorites)): ?>
        <div class="playlist-empty">
            <i class="bi bi-bookmark" style="font-size:4rem; opacity:0.25;"></i>
            <h5 class="mt-3 text-muted">Favori listeniz boş</h5>
            <p class="text-muted">
                Video sayfasında <strong>Favorilere Ekle</strong> butonuna basarak listenizi oluşturun.
            </p>
            <a href="index.php" class="btn btn-danger mt-2">
                <i class="bi bi-collection-play"></i> Videolara Göz At
            </a>
        </div>
    <?php else: ?>
        <p class="text-muted mb-4"><?= count($favorites) ?> favori video</p>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-4">
            <?php foreach ($favorites as $video): ?>
                <div class="col">
                    <div class="card video-card h-100">
                        <a href="video_detail.php?id=<?= $video['VideoID'] ?>" class="text-decoration-none text-dark">
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
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h6 class="card-title">
                                    <?= htmlspecialchars($video['Title'], ENT_QUOTES, 'UTF-8') ?>
                                </h6>
                                <div class="card-stats d-flex justify-content-between mt-2">
                                    <span>
                                        <?php if ($video['CategoryName']): ?>
                                            <span class="badge bg-secondary badge-category">
                                                <?= htmlspecialchars($video['CategoryName'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <span>
                                        <i class="bi bi-eye"></i> <?= number_format($video['ViewCount']) ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                        <div class="card-footer bg-transparent pt-2 pb-3 px-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i>
                                    <?= date('d.m.Y', strtotime($video['AddedAt'])) ?>
                                </small>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="remove_video" value="<?= $video['VideoID'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                                        onclick="return confirm('Bu videoyu favorilerden çıkarmak istiyor musunuz?')">
                                    <i class="bi bi-bookmark-x"></i> Listeden Çıkar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/footer.php'; ?>
