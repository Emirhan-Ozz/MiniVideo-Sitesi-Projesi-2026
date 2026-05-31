<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_auth.php';

// Video başına toplam izlenme sayısı (Watch_History'den)
$viewStats = $conn->query("
    SELECT v.VideoID, v.Title, v.ViewCount,
           COUNT(wh.HistoryID) AS WatchCount
    FROM Videos v
    LEFT JOIN Watch_History wh ON wh.VideoID = v.VideoID
    GROUP BY v.VideoID, v.Title, v.ViewCount
    ORDER BY WatchCount DESC
")->fetch_all(MYSQLI_ASSOC);

// Son 50 izlenme kaydı (kullanıcı + ziyaretçi)
$recentViews = $conn->query("
    SELECT v.Title, u.Username, wh.WatchedAt, wh.VideoID
    FROM Watch_History wh
    JOIN Videos v ON wh.VideoID = v.VideoID
    LEFT JOIN Users u ON wh.UserID = u.UserID
    ORDER BY wh.WatchedAt DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

// Like istatistikleri (video başına)
$likeStats = $conn->query("
    SELECT v.VideoID, v.Title, COUNT(l.LikeID) AS LikeCount
    FROM Videos v
    LEFT JOIN Likes l ON l.VideoID = v.VideoID
    GROUP BY v.VideoID, v.Title
    ORDER BY LikeCount DESC
")->fetch_all(MYSQLI_ASSOC);

// Toplam istatistikler
$totals = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM Videos)        AS total_videos,
        (SELECT COUNT(*) FROM Users)         AS total_users,
        (SELECT COUNT(*) FROM Watch_History) AS total_views,
        (SELECT COUNT(*) FROM Likes)         AS total_likes,
        (SELECT COUNT(*) FROM Comments)      AS total_comments
")->fetch_assoc();

include_once __DIR__ . '/admin_header.php';
?>

<!-- Özet Kartlar -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-primary"><?= $totals['total_videos'] ?></div>
                <div class="small text-muted"><i class="bi bi-collection-play"></i> Video</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-success"><?= $totals['total_users'] ?></div>
                <div class="small text-muted"><i class="bi bi-people"></i> Kullanıcı</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-info"><?= $totals['total_views'] ?></div>
                <div class="small text-muted"><i class="bi bi-eye"></i> İzlenme</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-danger"><?= $totals['total_likes'] ?></div>
                <div class="small text-muted"><i class="bi bi-heart-fill"></i> Beğeni</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-warning"><?= $totals['total_comments'] ?></div>
                <div class="small text-muted"><i class="bi bi-chat-dots"></i> Yorum</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- Video Başına İzlenme -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-bar-chart-line"></i> Video Başına İzlenme
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover admin-table mb-0">
                        <thead>
                            <tr>
                                <th>Video</th>
                                <th class="text-center">İzlenme</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($viewStats)): ?>
                                <tr><td colspan="2" class="text-center text-muted py-3">Henüz izlenme yok.</td></tr>
                            <?php else: ?>
                                <?php
                                $maxViews = max(array_column($viewStats, 'WatchCount')) ?: 1;
                                foreach ($viewStats as $row):
                                    $pct = $maxViews > 0 ? round(($row['WatchCount'] / $maxViews) * 100) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <a href="video_detail.php?id=<?= $row['VideoID'] ?>"
                                               target="_blank" class="text-decoration-none small">
                                                <?= htmlspecialchars($row['Title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <div class="progress mt-1" style="height:4px;">
                                                <div class="progress-bar bg-info" style="width:<?= $pct ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="text-center fw-bold"><?= $row['WatchCount'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Video Başına Beğeni -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-heart-fill text-danger"></i> Video Başına Beğeni
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover admin-table mb-0">
                        <thead>
                            <tr>
                                <th>Video</th>
                                <th class="text-center">Beğeni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($likeStats)): ?>
                                <tr><td colspan="2" class="text-center text-muted py-3">Henüz beğeni yok.</td></tr>
                            <?php else: ?>
                                <?php
                                $maxLikes = max(array_column($likeStats, 'LikeCount')) ?: 1;
                                foreach ($likeStats as $row):
                                    $pct = $maxLikes > 0 ? round(($row['LikeCount'] / $maxLikes) * 100) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <a href="video_detail.php?id=<?= $row['VideoID'] ?>"
                                               target="_blank" class="text-decoration-none small">
                                                <?= htmlspecialchars($row['Title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <div class="progress mt-1" style="height:4px;">
                                                <div class="progress-bar bg-danger" style="width:<?= $pct ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="text-center fw-bold"><?= $row['LikeCount'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Son 50 İzlenme Kaydı -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-clock-history"></i> Son 50 İzlenme Kaydı
                <small class="text-muted ms-2">(UserID=NULL olanlar ziyaretçi)</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover admin-table mb-0">
                        <thead>
                            <tr>
                                <th>Video</th>
                                <th>Kullanıcı</th>
                                <th>İzlenme Tarihi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentViews)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">Henüz izlenme kaydı yok.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentViews as $row): ?>
                                    <tr>
                                        <td>
                                            <a href="video_detail.php?id=<?= $row['VideoID'] ?>"
                                               target="_blank" class="text-decoration-none">
                                                <?= htmlspecialchars($row['Title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($row['Username']): ?>
                                                <span class="badge bg-primary">
                                                    <i class="bi bi-person"></i>
                                                    <?= htmlspecialchars($row['Username'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-incognito"></i> Ziyaretçi
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small">
                                            <?= date('d.m.Y H:i', strtotime($row['WatchedAt'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

</div><!-- /container-fluid from admin_header.php -->
<?php include_once __DIR__ . '/footer.php'; ?>
