<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$cats = $conn->query("SELECT * FROM Categories ORDER BY CategoryName")->fetch_all(MYSQLI_ASSOC);
$message = '';

function save_uploaded_video_file(array $file, int $userId): array {
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

    $safeName = 'video_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [false, 'Dosya uploads/videos klasörüne taşınamadı. Klasör izinlerini kontrol edin.', ''];
    }

    return [true, '', 'uploads/videos/' . $safeName];
}

function save_thumbnail_upload_or_generated(?array $thumbFile, string $generatedData, int $userId): string {
    $thumbDir = __DIR__ . '/uploads/thumbnails/';
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0777, true);
    }

    // Kullanıcı elle thumbnail seçtiyse önce onu kullan
    if ($thumbFile && isset($thumbFile['error']) && $thumbFile['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) {
            $safeName = 'thumb_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($thumbFile['tmp_name'], $thumbDir . $safeName)) {
                return 'uploads/thumbnails/' . $safeName;
            }
        }
    }

    if ($generatedData !== '' && preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $generatedData)) {
        $data = preg_replace('/^data:image\/(jpeg|jpg|png|webp);base64,/', '', $generatedData);
        $binary = base64_decode($data, true);
        if ($binary !== false) {
            $safeName = 'thumb_auto_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
            if (file_put_contents($thumbDir . $safeName, $binary) !== false) {
                return 'uploads/thumbnails/' . $safeName;
            }
        }
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
    $generatedThumbData = $_POST['generated_thumbnail'] ?? '';

    if ($title === '') {
        $message = '<div class="alert alert-danger">Başlık zorunludur.</div>';
    } elseif (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        $message = '<div class="alert alert-danger">Video dosyası yüklenemedi.</div>';
    } else {
        [$ok, $err, $videoURL] = save_uploaded_video_file($_FILES['video_file'], (int)$_SESSION['user_id']);

        if (!$ok) {
            $message = '<div class="alert alert-danger">' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</div>';
        } else {
            $thumbURL = save_thumbnail_upload_or_generated($_FILES['thumbnail_file'] ?? null, $generatedThumbData, (int)$_SESSION['user_id']);

            if ($categoryId !== null) {
                $stmt = $conn->prepare("INSERT INTO Videos (Title, Description, VideoURL, ThumbnailURL, CategoryID, UploaderID) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssii', $title, $description, $videoURL, $thumbURL, $categoryId, $_SESSION['user_id']);
            } else {
                $stmt = $conn->prepare("INSERT INTO Videos (Title, Description, VideoURL, ThumbnailURL, CategoryID, UploaderID) VALUES (?, ?, ?, ?, NULL, ?)");
                $stmt->bind_param('ssssi', $title, $description, $videoURL, $thumbURL, $_SESSION['user_id']);
            }
            $stmt->execute();
            header('Location: video_detail.php?id=' . $stmt->insert_id . '&uploaded=1');
            exit;
        }
    }
}

include_once __DIR__ . '/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-danger text-white fw-semibold">
                    <i class="bi bi-cloud-upload"></i> Video Yükle
                </div>
                <div class="card-body">
                    <?= $message ?>
                    <form method="POST" enctype="multipart/form-data" id="user-upload-form">
                        <input type="hidden" name="generated_thumbnail" id="generated_thumbnail">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Başlık <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="Video başlığı">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Kategori</label>
                            <select name="category_id" class="form-select">
                                <option value="">— Kategori Seçin —</option>
                                <?php foreach ($cats as $c): ?>
                                    <option value="<?= $c['CategoryID'] ?>">
                                        <?= htmlspecialchars($c['CategoryName'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Video Dosyası <span class="text-danger">*</span></label>
                            <input type="file" name="video_file" id="video_file" class="form-control" accept="video/mp4,video/webm,video/ogg" required>
                            <div class="form-text">MP4, WEBM veya OGG — maksimum 200 MB.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Thumbnail / Kapak Görseli <small class="text-muted fw-normal">(isteğe bağlı)</small></label>
                            <input type="file" name="thumbnail_file" id="thumbnail_file" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Seçmezseniz videonun ilk karesi otomatik thumbnail olarak alınır.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Thumbnail Önizleme</label>
                            <div class="thumbnail-preview-box">
                                <img id="thumbnail_preview" src="" alt="Thumbnail önizleme" style="display:none; max-width:260px; border-radius:12px;">
                                <span id="thumbnail_preview_text" class="text-muted">Henüz thumbnail oluşturulmadı.</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Açıklama</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Video hakkında kısa açıklama..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-upload"></i> Yükle
                        </button>
                        <a href="index.php" class="btn btn-secondary ms-2">İptal</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const videoInput = document.getElementById('video_file');
    const thumbInput = document.getElementById('thumbnail_file');
    const generatedInput = document.getElementById('generated_thumbnail');
    const preview = document.getElementById('thumbnail_preview');
    const previewText = document.getElementById('thumbnail_preview_text');

    function showPreview(src, text) {
        if (src) {
            preview.src = src;
            preview.style.display = 'block';
            previewText.textContent = text || '';
        } else {
            preview.removeAttribute('src');
            preview.style.display = 'none';
            previewText.textContent = text || 'Henüz thumbnail oluşturulmadı.';
        }
    }

    thumbInput?.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) {
            return;
        }
        generatedInput.value = '';
        showPreview(URL.createObjectURL(file), 'Seçilen thumbnail kullanılacak.');
    });

    videoInput?.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file || (thumbInput.files && thumbInput.files[0])) {
            return;
        }

        showPreview('', 'Videonun ilk karesi hazırlanıyor...');

        const url = URL.createObjectURL(file);
        const video = document.createElement('video');
        video.preload = 'metadata';
        video.muted = true;
        video.playsInline = true;
        video.src = url;

        video.addEventListener('loadedmetadata', function () {
            video.currentTime = Math.min(0.2, Math.max(0, video.duration / 20));
        });

        video.addEventListener('seeked', function () {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 1280;
                canvas.height = Math.round(1280 * (video.videoHeight / video.videoWidth || 9 / 16));
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const data = canvas.toDataURL('image/jpeg', 0.82);
                generatedInput.value = data;
                showPreview(data, 'Otomatik thumbnail: videonun ilk karesi.');
            } catch (e) {
                showPreview('', 'Otomatik thumbnail alınamadı. İsterseniz görsel seçebilirsiniz.');
            } finally {
                URL.revokeObjectURL(url);
            }
        });

        video.addEventListener('error', function () {
            showPreview('', 'Otomatik thumbnail alınamadı. İsterseniz görsel seçebilirsiniz.');
            URL.revokeObjectURL(url);
        });
    });
})();
</script>

<?php include_once __DIR__ . '/footer.php'; ?>
