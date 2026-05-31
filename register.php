<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Tüm alanlar zorunludur.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Kullanıcı adı 3–50 karakter arasında olmalıdır.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi girin.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
    } elseif ($password !== $password2) {
        $error = 'Şifreler eşleşmiyor.';
    } else {
        $check = $conn->prepare("SELECT UserID FROM Users WHERE Email = ? OR Username = ?");
        $check->bind_param('ss', $email, $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Bu e-posta veya kullanıcı adı zaten kullanılıyor.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $conn->prepare("INSERT INTO Users (Username, Email, Password) VALUES (?, ?, ?)");
            $ins->bind_param('sss', $username, $email, $hash);
            $ins->execute();
            header('Location: login.php?registered=1');
            exit;
        }
    }
}

include_once __DIR__ . '/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white text-center py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-person-plus me-2"></i>Kayıt Ol
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" novalidate>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Kullanıcı Adı</label>
                            <input type="text" name="username" class="form-control"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="kullanici_adi" minlength="3" maxlength="50" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">E-posta</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="ornek@email.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Şifre <small class="text-muted fw-normal">(en az 6 karakter)</small>
                            </label>
                            <input type="password" name="password" class="form-control"
                                   placeholder="••••••" minlength="6" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Şifre Tekrar</label>
                            <input type="password" name="password2" class="form-control"
                                   placeholder="••••••" required>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-person-check"></i> Hesap Oluştur
                        </button>
                    </form>
                </div>
                <div class="card-footer text-center bg-transparent py-3">
                    <small class="text-muted">
                        Zaten hesabınız var mı?
                        <a href="login.php" class="text-danger fw-semibold">Giriş Yap</a>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/footer.php'; ?>
