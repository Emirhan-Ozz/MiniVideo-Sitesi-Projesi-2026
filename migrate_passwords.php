<?php
/**
 * migrate_passwords.php
 * Seed verisi ile eklenen düz metin şifreleri bcrypt ile hashler.
 * Çalıştırdıktan sonra bu dosyayı silin.
 *
 * Kullanım: http://localhost/minivideo/migrate_passwords.php
 */
require_once __DIR__ . '/db.php';

$users = [
    ['email' => 'admin@mini.com',  'password' => 'admin123'],
    ['email' => 'ahmet@mini.com',  'password' => 'ahmet123'],
    ['email' => 'zeynep@mini.com', 'password' => 'zeynep123'],
    ['email' => 'mehmet@mini.com', 'password' => 'mehmet123'],
];

echo "<pre>\n";
$allOk = true;

foreach ($users as $u) {
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE Users SET Password = ? WHERE Email = ?");
    $stmt->bind_param('ss', $hash, $u['email']);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo "✓ {$u['email']} güncellendi.\n";
    } else {
        echo "✗ {$u['email']} bulunamadı veya güncellenemedi!\n";
        $allOk = false;
    }
}

if ($allOk) {
    echo "\nTüm şifreler başarıyla hashendi.\n";
    echo "Bu dosyayı şimdi silebilirsiniz.\n";
} else {
    echo "\nBazı işlemler başarısız oldu. schema.sql ve seed.sql doğru içe aktarıldı mı?\n";
}

echo "</pre>";
