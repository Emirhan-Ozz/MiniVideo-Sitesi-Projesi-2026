<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Video verisini çek
$stmt = $conn->prepare("
    SELECT v.*, c.CategoryName, u.Username AS UploaderName
    FROM Videos v
    LEFT JOIN Categories c ON v.CategoryID = c.CategoryID
    JOIN Users u ON v.UploaderID = u.UserID
    WHERE v.VideoID = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if (!$video) {
    header('Location: index.php');
    exit;
}

// Watch_History kaydı ekle + ViewCount artır
$userId = $_SESSION['user_id'] ?? null;
if ($userId !== null) {
    $wh = $conn->prepare("INSERT INTO Watch_History (VideoID, UserID) VALUES (?, ?)");
    $wh->bind_param('ii', $id, $userId);
} else {
    $wh = $conn->prepare("INSERT INTO Watch_History (VideoID, UserID) VALUES (?, NULL)");
    $wh->bind_param('i', $id);
}
$wh->execute();
$conn->query("UPDATE Videos SET ViewCount = ViewCount + 1 WHERE VideoID = $id");
$video['ViewCount']++;

// Yorum formu POST işleme
$commentError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postAction = $_POST['action'];

    if ($postAction === 'comment') {
        if (!isset($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
        $content = trim($_POST['content'] ?? '');
        if ($content === '') {
            $commentError = 'Yorum boş olamaz.';
        } else {
            $cs = $conn->prepare("INSERT INTO Comments (VideoID, UserID, Content) VALUES (?, ?, ?)");
            $cs->bind_param('iis', $id, $_SESSION['user_id'], $content);
            $cs->execute();
            header("Location: video_detail.php?id=$id#comments");
            exit;
        }

    } elseif ($postAction === 'favorite') {
        if (!isset($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
        $fs = $conn->prepare("INSERT IGNORE INTO Playlists (UserID, VideoID) VALUES (?, ?)");
        $fs->bind_param('ii', $_SESSION['user_id'], $id);
        $fs->execute();
        header("Location: video_detail.php?id=$id&fav=1");
        exit;

    } elseif ($postAction === 'unfavorite') {
        if (!isset($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
        $us = $conn->prepare("DELETE FROM Playlists WHERE UserID = ? AND VideoID = ?");
        $us->bind_param('ii', $_SESSION['user_id'], $id);
        $us->execute();
        header("Location: video_detail.php?id=$id&fav=0");
        exit;
    }
}

// Yorumları çek
$cs2 = $conn->prepare("
    SELECT c.*, u.Username
    FROM Comments c
    JOIN Users u ON c.UserID = u.UserID
    WHERE c.VideoID = ?
    ORDER BY c.CreatedAt DESC
");
$cs2->bind_param('i', $id);
$cs2->execute();
$comments = $cs2->get_result()->fetch_all(MYSQLI_ASSOC);

// Like sayısı
$likeCount = (int)$conn->query("SELECT COUNT(*) FROM Likes WHERE VideoID = $id")->fetch_row()[0];

// Kullanıcı favorilere eklemiş mi?
$isFavorite = false;
if (isset($_SESSION['user_id'])) {
    $fc = $conn->prepare("SELECT 1 FROM Playlists WHERE UserID = ? AND VideoID = ?");
    $fc->bind_param('ii', $_SESSION['user_id'], $id);
    $fc->execute();
    $isFavorite = $fc->get_result()->num_rows > 0;
}

include_once __DIR__ . '/header.php';
?>

<div class="container mt-4">

    <?php if (isset($_GET['fav'])): ?>
        <div class="alert alert-<?= $_GET['fav'] === '1' ? 'success' : 'info' ?> alert-dismissible fade show">
            <?php if ($_GET['fav'] === '1'): ?>
                <i class="bi bi-bookmark-check-fill"></i> Video favorilere eklendi!
            <?php else: ?>
                <i class="bi bi-bookmark-x"></i> Video favorilerden çıkarıldı.
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Sol: Video + İçerik -->
        <div class="col-lg-8">

            <!-- Video Oynatıcı -->
            <div class="video-player-wrapper mb-4">
                <?php
                    $videoSrc = trim($video['VideoURL'] ?? '');
                    $posterSrc = trim($video['ThumbnailURL'] ?? '');
                    $isLocalVideo = preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $videoSrc);
                    $ext = strtolower(pathinfo(parse_url($videoSrc, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                    $mimeMap = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg'];
                    $mime = $mimeMap[$ext] ?? '';
                ?>
                <?php if ($isLocalVideo): ?>
                    <video controls preload="metadata"
                           <?= $posterSrc !== '' ? 'poster="' . htmlspecialchars($posterSrc, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                        <source src="<?= htmlspecialchars($videoSrc, ENT_QUOTES, 'UTF-8') ?>" <?= $mime ? 'type="' . $mime . '"' : '' ?>>
                        Tarayıcınız video oynatmayı desteklemiyor.
                    </video>
                <?php else: ?>
                    <iframe
                        src="<?= htmlspecialchars($videoSrc, ENT_QUOTES, 'UTF-8') ?>"
                        title="<?= htmlspecialchars($video['Title'], ENT_QUOTES, 'UTF-8') ?>"
                        allowfullscreen
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                    </iframe>
                <?php endif; ?>
            </div>

            <!-- Başlık -->
            <h2 class="fw-bold mb-2">
                <?= htmlspecialchars($video['Title'], ENT_QUOTES, 'UTF-8') ?>
            </h2>

            <!-- Meta bilgiler -->
            <div class="d-flex flex-wrap gap-3 mb-3 text-muted small align-items-center">
                <span><i class="bi bi-eye"></i> <?= number_format($video['ViewCount']) ?> izlenme</span>
                <span><i class="bi bi-person-circle"></i> <?= htmlspecialchars($video['UploaderName'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($video['CategoryName'])): ?>
                    <span class="badge bg-secondary">
                        <?= htmlspecialchars($video['CategoryName'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
                <span><i class="bi bi-calendar3"></i> <?= date('d.m.Y', strtotime($video['CreatedAt'])) ?></span>
            </div>

            <!-- Like + Favori Butonları -->
            <div class="d-flex gap-2 mb-4 flex-wrap align-items-center">
                <button id="like-btn"
                        class="btn btn-outline-danger"
                        data-video-id="<?= $video['VideoID'] ?>">
                    <i class="bi bi-heart-fill"></i>
                    <span id="like-count"><?= $likeCount ?></span> Beğeni
                </button>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="<?= $isFavorite ? 'unfavorite' : 'favorite' ?>">
                        <button type="submit" class="btn <?= $isFavorite ? 'btn-warning' : 'btn-outline-warning' ?>">
                            <i class="bi bi-<?= $isFavorite ? 'bookmark-fill' : 'bookmark-plus' ?>"></i>
                            <?= $isFavorite ? 'Favorilerden Çıkar' : 'Favorilere Ekle' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Açıklama -->
            <?php if (!empty($video['Description'])): ?>
                <div class="card mb-4 border-0 bg-white shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted mb-2"><i class="bi bi-text-left"></i> Açıklama</h6>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($video['Description'], ENT_QUOTES, 'UTF-8')) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Yorumlar Bölümü -->
            <div id="comments">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-chat-dots"></i> Yorumlar
                    <span class="text-muted fw-normal fs-6">(<?= count($comments) ?>)</span>
                </h5>

                <!-- Yorum Formu -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="comment">
                        <?php if ($commentError): ?>
                            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($commentError, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <div class="d-flex gap-2">
                            <i class="bi bi-person-circle fs-4 text-muted mt-1"></i>
                            <div class="flex-grow-1">
                                <textarea name="content" class="form-control mb-2" rows="2"
                                          placeholder="Yorumunuzu yazın..." required></textarea>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-send"></i> Gönder
                                </button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-light border mb-4">
                        <i class="bi bi-info-circle text-primary"></i>
                        Yorum yazmak için
                        <a href="login.php" class="fw-semibold">giriş yapın</a>
                        veya
                        <a href="register.php" class="fw-semibold">kayıt olun</a>.
                    </div>
                <?php endif; ?>

                <!-- Yorum Listesi -->
                <?php if (empty($comments)): ?>
                    <p class="text-muted text-center py-4">
                        <i class="bi bi-chat-square-dots" style="font-size:2rem; opacity:.3;"></i><br>
                        Henüz yorum yok. İlk yorumu siz yapın!
                    </p>
                <?php else: ?>
                    <div class="comments-list">
                        <?php foreach ($comments as $c): ?>
                            <div class="comment-item">
                                <div>
                                    <span class="comment-author">
                                        <i class="bi bi-person-circle"></i>
                                        <?= htmlspecialchars($c['Username'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="comment-date">
                                        <?= date('d.m.Y H:i', strtotime($c['CreatedAt'])) ?>
                                    </span>
                                </div>
                                <div class="comment-content">
                                    <?= nl2br(htmlspecialchars($c['Content'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sağ: Bilgi Kartı -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm sticky-top" style="top:20px;">
                <div class="card-header bg-dark text-white">
                    <i class="bi bi-info-circle"></i> Video Bilgisi
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Yükleyen</dt>
                        <dd class="col-7"><?= htmlspecialchars($video['UploaderName'], ENT_QUOTES, 'UTF-8') ?></dd>

                        <?php if (!empty($video['CategoryName'])): ?>
                            <dt class="col-5 text-muted">Kategori</dt>
                            <dd class="col-7"><?= htmlspecialchars($video['CategoryName'], ENT_QUOTES, 'UTF-8') ?></dd>
                        <?php endif; ?>

                        <dt class="col-5 text-muted">İzlenme</dt>
                        <dd class="col-7"><?= number_format($video['ViewCount']) ?></dd>

                        <dt class="col-5 text-muted">Beğeni</dt>
                        <dd class="col-7"><?= $likeCount ?></dd>

                        <dt class="col-5 text-muted">Tarih</dt>
                        <dd class="col-7 mb-0"><?= date('d.m.Y', strtotime($video['CreatedAt'])) ?></dd>
                    </dl>
                </div>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="card-footer bg-transparent">
                        <a href="login.php" class="btn btn-danger btn-sm w-100">
                            <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script src="app.js"></script>
<?php include_once __DIR__ . '/footer.php'; ?>
