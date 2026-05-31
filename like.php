<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$videoId = (int)($_POST['video_id'] ?? 0);
if ($videoId <= 0) {
    echo json_encode(['error' => 'Geçersiz video ID']);
    exit;
}

// Video var mı kontrol et
$vCheck = $conn->prepare("SELECT VideoID FROM Videos WHERE VideoID = ?");
$vCheck->bind_param('i', $videoId);
$vCheck->execute();
if ($vCheck->get_result()->num_rows === 0) {
    echo json_encode(['error' => 'Video bulunamadı']);
    exit;
}

$userId  = $_SESSION['user_id'] ?? null;
$guestIp = $_SERVER['REMOTE_ADDR'];

// Daha önce like bırakmış mı?
if ($userId !== null) {
    $check = $conn->prepare("SELECT LikeID FROM Likes WHERE VideoID = ? AND UserID = ?");
    $check->bind_param('ii', $videoId, $userId);
} else {
    $check = $conn->prepare("SELECT LikeID FROM Likes WHERE VideoID = ? AND GuestIP = ?");
    $check->bind_param('is', $videoId, $guestIp);
}
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    // Toggle: like varsa kaldır
    $del = $conn->prepare("DELETE FROM Likes WHERE LikeID = ?");
    $del->bind_param('i', $existing['LikeID']);
    $del->execute();
    $action = 'removed';
} else {
    // Like yoksa ekle
    if ($userId !== null) {
        $ins = $conn->prepare("INSERT INTO Likes (VideoID, UserID, GuestIP) VALUES (?, ?, NULL)");
        $ins->bind_param('ii', $videoId, $userId);
    } else {
        $ins = $conn->prepare("INSERT INTO Likes (VideoID, UserID, GuestIP) VALUES (?, NULL, ?)");
        $ins->bind_param('is', $videoId, $guestIp);
    }
    $ins->execute();
    $action = 'added';
}

// Güncel like sayısını döndür
$count = (int)$conn->query("SELECT COUNT(*) FROM Likes WHERE VideoID = $videoId")->fetch_row()[0];
echo json_encode(['action' => $action, 'count' => $count]);
