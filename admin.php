<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_auth.php';

$message = '';
$action  = $_GET['action'] ?? 'list';
$editId  = (int)($_GET['id'] ?? 0);

$cats = $conn->query("SELECT * FROM Categories ORDER BY CategoryName")->fetch_all(MYSQLI_ASSOC);

function admin_save_uploaded_video_file(array $file, int $userId): array {
    $maxSize = 200 * 1024 * 1024; // 200 MB
    $allowedExt = ['mp4', 'webm', 'ogg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'Video dosyası yüklenemedi.', ''];
    }
    if ($file['size'] > $maxSize) {
        return [false, 'Video en fazla 200 MB olabilir.', ''];
    }
    if (!in_array($ext, $allowedExt, true)) {
        return [false, 'Sadece MP4, WEBM veya OGG video yükleyebilirsiniz.', ''];
    }

    $uploadDir = __DIR__ . '/uploads/videos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $safeName = 'video_admin_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [false, 'Dosya uploads/videos klasörüne taşınamadı. Klasör izinlerini kontrol edin.', ''];
    }

    return [true, '', 'uploads/videos/' . $safeName];
}

function admin_save_thumbnail(?array $thumbFile, string $generatedData, int $userId): string {
    $thumbDir = __DIR__ . '/uploads/thumbnails/';
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0777, true);
    }

    if ($thumbFile && isset($thumbFile['error']) && $thumbFile['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) {
            $safeName = 'thumb_admin_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($thumbFile['tmp_name'], $thumbDir . $safeName)) {
                return 'uploads/thumbnails/' . $safeName;
            }
        }
    }

    if ($generatedData !== '' && preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $generatedData)) {
        $data = preg_replace('/^data:image\/(jpeg|jpg|png|webp);base64,/', '', $generatedData);
        $binary = base64_decode($data, true);
        if ($binary !== false) {
            $safeName = 'thumb_auto_admin_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
            if (file_put_contents($thumbDir . $safeName, $binary) !== false) {
                return 'uploads/thumbnails/' . $safeName;
            }
        }
    }

    return '';
}

function admin_action_people(mysqli $conn, int $videoId, string $type): array {
    if ($type === 'views') {
        $sql = "
            SELECT COALESCE(u.Username, 'Ziyaretçi') AS name, COUNT(*) AS total
            FROM Watch_History wh
            LEFT JOIN Users u ON wh.UserID = u.UserID
            WHERE wh.VideoID = ?
            GROUP BY name
            ORDER BY total DESC, name ASC
        ";
    } elseif ($type === 'likes') {
        $sql = "
            SELECT COALESCE(u.Username, CONCAT('Ziyaretçi ', l.GuestIP)) AS name, COUNT(*) AS total
            FROM Likes l
            LEFT JOIN Users u ON l.UserID = u.UserID
            WHERE l.VideoID = ?
            GROUP BY name
            ORDER BY total DESC, name ASC
        ";
    } else {
        $sql = "
            SELECT u.Username AS name, COUNT(*) AS total
            FROM Comments c
            JOIN Users u ON c.UserID = u.UserID
            WHERE c.VideoID = ?
            GROUP BY u.Username
            ORDER BY total DESC, u.Username ASC
        ";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $videoId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function render_people_popover(array $people, string $emptyText): string {
    if (empty($people)) {
        return '<span class="text-muted small">' . htmlspecialchars($emptyText, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $html = '<div class="action-users-mini">';
    foreach ($people as $idx => $p) {
        $extraClass = $idx >= 5 ? ' extra-user d-none' : '';
        $name = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
        $total = (int)$p['total'];
        $html .= '<div class="action-user-row' . $extraClass . '"><span>' . $name . '</span><strong>' . $total . '</strong></div>';
    }
    if (count($people) > 5) {
        $html .= '<button type="button" class="btn btn-link btn-sm p-0 mt-1 show-more-users">Daha fazla...</button>';
    }
    $html .= '</div>';
    return $html;
}


if ($action === 'delete' && $editId > 0) {
    $del = $conn->prepare("DELETE FROM Videos WHERE VideoID = ?");
    $del->bind_param('i', $editId);
    $del->execute();
    header('Location: admin.php?msg=deleted');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $videoURL   = trim($_POST['video_url'] ?? '');
    $thumbURL   = trim($_POST['thumb_url'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
    $postAction = $_POST['post_action'] ?? '';
    $videoSource = $_POST['video_source'] ?? 'youtube';
    $generatedThumbData = $_POST['generated_thumbnail'] ?? '';

    if (empty($title)) {
        $message = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Başlık zorunludur.</div>';
    } else {
        $vidId = (int)($_POST['video_id'] ?? 0);
        $oldVideo = null;

        if ($postAction === 'edit' && $vidId > 0) {
            $oldStmt = $conn->prepare("SELECT * FROM Videos WHERE VideoID = ?");
            $oldStmt->bind_param('i', $vidId);
            $oldStmt->execute();
            $oldVideo = $oldStmt->get_result()->fetch_assoc();
        }

        if ($videoSource === 'file') {
            $hasFile = isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK;
            if ($hasFile) {
                [$ok, $err, $newVideoURL] = admin_save_uploaded_video_file($_FILES['video_file'], (int)$_SESSION['user_id']);
                if (!$ok) {
                    $message = '<div class="alert alert-danger">' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</div>';
                } else {
                    $videoURL = $newVideoURL;
                }
            } elseif ($postAction === 'edit' && $oldVideo) {
                $videoURL = $oldVideo['VideoURL'];
            } else {
                $message = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Bilgisayardan video yüklemek için video dosyası seçmelisiniz.</div>';
            }
        }

        if ($message === '') {
            if ($videoSource === 'youtube' && empty($videoURL)) {
                $message = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> YouTube için Video URL zorunludur.</div>';
            } else {
                $newThumb = admin_save_thumbnail($_FILES['thumbnail_file'] ?? null, $generatedThumbData, (int)$_SESSION['user_id']);
                if ($newThumb !== '') {
                    $thumbURL = $newThumb;
                } elseif ($postAction === 'edit' && $oldVideo && $thumbURL === '') {
                    $thumbURL = $oldVideo['ThumbnailURL'];
                }

                if ($postAction === 'add') {
                    if ($categoryId !== null) {
                        $stmt = $conn->prepare("INSERT INTO Videos (Title, Description, VideoURL, ThumbnailURL, CategoryID, UploaderID) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param('ssssii', $title, $desc, $videoURL, $thumbURL, $categoryId, $_SESSION['user_id']);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO Videos (Title, Description, VideoURL, ThumbnailURL, CategoryID, UploaderID) VALUES (?, ?, ?, ?, NULL, ?)");
                        $stmt->bind_param('ssssi', $title, $desc, $videoURL, $thumbURL, $_SESSION['user_id']);
                    }
                    $stmt->execute();
                    header('Location: admin.php?msg=added');
                    exit;

                } elseif ($postAction === 'edit' && $vidId > 0) {
                    if ($categoryId !== null) {
                        $stmt = $conn->prepare("UPDATE Videos SET Title=?, Description=?, VideoURL=?, ThumbnailURL=?, CategoryID=? WHERE VideoID=?");
                        $stmt->bind_param('ssssii', $title, $desc, $videoURL, $thumbURL, $categoryId, $vidId);
                    } else {
                        $stmt = $conn->prepare("UPDATE Videos SET Title=?, Description=?, VideoURL=?, ThumbnailURL=?, CategoryID=NULL WHERE VideoID=?");
                        $stmt->bind_param('ssssi', $title, $desc, $videoURL, $thumbURL, $vidId);
                    }
                    $stmt->execute();
                    header('Location: admin.php?msg=updated');
                    exit;
                }
            }
        }
    }
}


$editVideo = null;
if ($action === 'edit' && $editId > 0) {
    $stmt = $conn->prepare("SELECT * FROM Videos WHERE VideoID = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editVideo = $stmt->get_result()->fetch_assoc();
    if (!$editVideo) {
        header('Location: admin.php');
        exit;
    }
}


$videos = $conn->query("
    SELECT v.*, c.CategoryName, u.Username,
           (SELECT COUNT(*) FROM Likes l WHERE l.VideoID = v.VideoID) AS LikeCount,
           (SELECT COUNT(*) FROM Comments cm WHERE cm.VideoID = v.VideoID) AS CommentCount
    FROM Videos v
    LEFT JOIN Categories c ON v.CategoryID = c.CategoryID
    JOIN Users u ON v.UploaderID = u.UserID
    ORDER BY v.CreatedAt DESC
")->fetch_all(MYSQLI_ASSOC);

include_once __DIR__ . '/admin_header.php';
?>

<?php if (isset($_GET['msg'])): ?>
    <?php $msgMap = ['added' => 'Video eklendi.', 'updated' => 'Video güncellendi.', 'deleted' => 'Video silindi.']; ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill"></i>
        <?= htmlspecialchars($msgMap[$_GET['msg']] ?? '', ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?= $message ?>

<div class="admin-hero mb-4">
    <div>
        <span class="admin-kicker">Mini Video Yönetim Paneli</span>
        <h2>Video yönetimi</h2>
        <p>Video ekleme, düzenleme, istatistik ve kullanıcı etkileşimlerini tek ekranda kontrol edin.</p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-house"></i> Siteye Dön
    </a>
</div>


<div class="card admin-glass-card mb-4">
    <?php if ($editVideo): ?>
        <div class="card-header admin-card-header warning d-flex justify-content-between align-items-center">
            <span><i class="bi bi-pencil-square"></i> Video Düzenle — #<?= $editVideo['VideoID'] ?></span>
            <a href="admin.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg"></i> İptal
            </a>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="post_action" value="edit">
                <input type="hidden" name="video_id" value="<?= $editVideo['VideoID'] ?>">
                <?php include __DIR__ . '/video_form_fields.php'; ?>
                <div class="mt-3">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save"></i> Güncelle
                    </button>
                    <a href="admin.php" class="btn btn-secondary ms-2">İptal</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="card-header admin-card-header success">
            <i class="bi bi-plus-circle"></i> Yeni Video Ekle
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="post_action" value="add">
                <?php $editVideo = null; include __DIR__ . '/video_form_fields.php'; ?>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Video Ekle
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>


<div class="card admin-glass-card">
    <div class="card-header admin-card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-collection-play"></i> Tüm Videolar
            <span class="badge bg-secondary ms-1"><?= count($videos) ?></span>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover admin-table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Başlık</th>
                        <th>Kategori</th>
                        <th>Yükleyen</th>
                        <th>İzlenme</th>
                        <th>Beğeni</th>
                        <th>Yorum</th>
                        <th>Tarih</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($videos)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Henüz video eklenmemiş.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($videos as $v): ?>
                            <?php
                                $viewPeople = admin_action_people($conn, (int)$v['VideoID'], 'views');
                                $likePeople = admin_action_people($conn, (int)$v['VideoID'], 'likes');
                                $commentPeople = admin_action_people($conn, (int)$v['VideoID'], 'comments');
                            ?>
                            <tr>
                                <td class="text-muted"><?= $v['VideoID'] ?></td>
                                <td>
                                    <a href="video_detail.php?id=<?= $v['VideoID'] ?>"
                                       target="_blank" class="text-decoration-none fw-semibold admin-video-title">
                                        <?= htmlspecialchars($v['Title'], ENT_QUOTES, 'UTF-8') ?>
                                        <i class="bi bi-box-arrow-up-right small text-muted"></i>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($v['CategoryName']): ?>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($v['CategoryName'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($v['Username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <div class="action-stat">
                                        <i class="bi bi-eye"></i> <?= number_format($v['ViewCount']) ?>
                                        <div class="action-popover">
                                            <strong>İzleyen kullanıcılar</strong>
                                            <?= render_people_popover($viewPeople, 'Henüz izleyen yok.') ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-stat">
                                        <i class="bi bi-heart-fill text-danger"></i> <?= number_format($v['LikeCount']) ?>
                                        <div class="action-popover">
                                            <strong>Beğenen kullanıcılar</strong>
                                            <?= render_people_popover($likePeople, 'Henüz beğeni yok.') ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-stat">
                                        <i class="bi bi-chat-left-text"></i> <?= number_format($v['CommentCount']) ?>
                                        <div class="action-popover">
                                            <strong>Yorum yapan kullanıcılar</strong>
                                            <?= render_people_popover($commentPeople, 'Henüz yorum yok.') ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted small"><?= date('d.m.Y', strtotime($v['CreatedAt'])) ?></td>
                                <td>
                                    <a href="admin.php?action=edit&id=<?= $v['VideoID'] ?>"
                                       class="btn btn-sm btn-outline-warning me-1" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="admin.php?action=delete&id=<?= $v['VideoID'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       title="Sil"
                                       onclick="return confirm('Bu videoyu silmek istediğinize emin misiniz?\nBu işlem geri alınamaz.')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('show-more-users')) return;
    const box = e.target.closest('.action-users-mini');
    box.querySelectorAll('.extra-user').forEach(function (row) {
        row.classList.remove('d-none');
    });
    e.target.remove();
});
</script>

</div>
<?php include_once __DIR__ . '/footer.php'; ?>
