<?php
// admin.php içinden include edilen form alanları
// $editVideo: düzenleme modunda mevcut veri, ekleme modunda null
$fTitle = htmlspecialchars($editVideo['Title'] ?? '', ENT_QUOTES, 'UTF-8');
$fDesc  = htmlspecialchars($editVideo['Description'] ?? '', ENT_QUOTES, 'UTF-8');
$fUrl   = htmlspecialchars($editVideo['VideoURL'] ?? '', ENT_QUOTES, 'UTF-8');
$fThumb = htmlspecialchars($editVideo['ThumbnailURL'] ?? '', ENT_QUOTES, 'UTF-8');
$fCatId = (int)($editVideo['CategoryID'] ?? 0);

$isEdit = !empty($editVideo);
$isLocal = $isEdit && preg_match('/\.(mp4|webm|ogg)$/i', $editVideo['VideoURL'] ?? '');
?>
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label fw-semibold">Başlık <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control"
               value="<?= $fTitle ?>" placeholder="Video başlığı" required>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Kategori / Tür</label>
        <select name="category_id" class="form-select">
            <option value="">— Kategori Seçin —</option>
            <?php foreach ($cats as $c): ?>
                <option value="<?= $c['CategoryID'] ?>"
                    <?= $fCatId === (int)$c['CategoryID'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['CategoryName'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12">
        <div class="admin-upload-tabs">
            <label class="form-label fw-semibold d-block mb-2">Video Kaynağı</label>
            <div class="form-check form-check-inline">
                <input class="form-check-input video-source-radio" type="radio" name="video_source" id="source_youtube" value="youtube" <?= !$isLocal ? 'checked' : '' ?>>
                <label class="form-check-label" for="source_youtube">YouTube embed linki</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input video-source-radio" type="radio" name="video_source" id="source_file" value="file" <?= $isLocal ? 'checked' : '' ?>>
                <label class="form-check-label" for="source_file">Bilgisayardan video yükle</label>
            </div>
        </div>
    </div>

    <div class="col-12 video-source-box" id="youtube-box">
        <label class="form-label fw-semibold">
            Video URL
            <small class="text-muted fw-normal">— Örn: https://www.youtube.com/embed/VIDEO_ID</small>
        </label>
        <input type="url" name="video_url" class="form-control"
               value="<?= !$isLocal ? $fUrl : '' ?>"
               placeholder="https://www.youtube.com/embed/VIDEO_ID">
        <?php if ($isEdit && $isLocal): ?>
            <div class="form-text">Mevcut video yerel dosya: <?= $fUrl ?></div>
        <?php endif; ?>
    </div>

    <div class="col-12 video-source-box" id="file-box">
        <label class="form-label fw-semibold">
            Video Dosyası
            <small class="text-muted fw-normal">
                <?= $isEdit ? '— boş bırakırsanız mevcut video korunur' : '— MP4, WEBM veya OGG' ?>
            </small>
        </label>
        <input type="file" name="video_file" id="admin_video_file" class="form-control" accept="video/mp4,video/webm,video/ogg">
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">
            Thumbnail URL
            <small class="text-muted fw-normal">— YouTube için kullanılabilir</small>
        </label>
        <input type="url" name="thumb_url" class="form-control"
               value="<?= $fThumb ?>"
               placeholder="https://img.youtube.com/vi/VIDEO_ID/maxresdefault.jpg">
    </div>

    <div class="col-md-7">
        <label class="form-label fw-semibold">
            Thumbnail Dosyası
            <small class="text-muted fw-normal">— isteğe bağlı</small>
        </label>
        <input type="file" name="thumbnail_file" id="admin_thumbnail_file" class="form-control" accept="image/jpeg,image/png,image/webp">
        <input type="hidden" name="generated_thumbnail" id="admin_generated_thumbnail">
        <div class="form-text">Thumbnail seçmezseniz, yerel video yüklenirken videonun ilk karesi otomatik alınır. Düzenlemede boş bırakırsanız mevcut thumbnail korunur.</div>
    </div>

    <div class="col-md-5">
        <label class="form-label fw-semibold">Thumbnail Önizleme</label>
        <div class="thumbnail-preview-box">
            <img id="admin_thumbnail_preview"
                 src="<?= $fThumb ?>"
                 alt="Thumbnail önizleme"
                 style="<?= $fThumb ? '' : 'display:none;' ?> max-width:220px; border-radius:12px;">
            <span id="admin_thumbnail_preview_text" class="text-muted">
                <?= $fThumb ? 'Mevcut thumbnail.' : 'Henüz thumbnail yok.' ?>
            </span>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Açıklama</label>
        <textarea name="description" class="form-control" rows="3"
                  placeholder="Video hakkında kısa açıklama..."><?= $fDesc ?></textarea>
    </div>
</div>

<script>
(function () {
    function refreshSourceBoxes() {
        const selected = document.querySelector('input[name="video_source"]:checked')?.value || 'youtube';
        document.getElementById('youtube-box').style.display = selected === 'youtube' ? '' : 'none';
        document.getElementById('file-box').style.display = selected === 'file' ? '' : 'none';
    }

    document.querySelectorAll('.video-source-radio').forEach(function (radio) {
        radio.addEventListener('change', refreshSourceBoxes);
    });
    refreshSourceBoxes();

    const videoInput = document.getElementById('admin_video_file');
    const thumbInput = document.getElementById('admin_thumbnail_file');
    const generatedInput = document.getElementById('admin_generated_thumbnail');
    const preview = document.getElementById('admin_thumbnail_preview');
    const previewText = document.getElementById('admin_thumbnail_preview_text');

    function showPreview(src, text) {
        if (src) {
            preview.src = src;
            preview.style.display = 'block';
            previewText.textContent = text || '';
        } else {
            preview.removeAttribute('src');
            preview.style.display = 'none';
            previewText.textContent = text || 'Henüz thumbnail yok.';
        }
    }

    thumbInput?.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;
        generatedInput.value = '';
        showPreview(URL.createObjectURL(file), 'Seçilen thumbnail kullanılacak.');
    });

    videoInput?.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file || (thumbInput.files && thumbInput.files[0])) return;

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
