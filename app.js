document.addEventListener('DOMContentLoaded', function () {
    const likeBtn = document.getElementById('like-btn');
    if (!likeBtn) return;

    likeBtn.addEventListener('click', function () {
        const videoId = this.dataset.videoId;

        fetch('like.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'video_id=' + encodeURIComponent(videoId)
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.error) { console.error(data.error); return; }

            document.getElementById('like-count').textContent = data.count;

            if (data.action === 'added') {
                likeBtn.classList.remove('btn-outline-danger');
                likeBtn.classList.add('btn-danger');
            } else {
                likeBtn.classList.remove('btn-danger');
                likeBtn.classList.add('btn-outline-danger');
            }
        })
        .catch(function (err) { console.error('Like hatası:', err); });
    });
});
