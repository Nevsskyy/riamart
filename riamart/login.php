<?php
require 'includes/functions.php';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $kunci_percobaan = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $stmt = $conn->prepare('SELECT hits, expires_at FROM login_attempts WHERE attempt_key = ?');
    $stmt->execute([$kunci_percobaan]);
    $percobaan = $stmt->fetch();

    if ($percobaan && $percobaan['expires_at'] > time() && $percobaan['hits'] >= 10) {
        $error = 'Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.';
    } else {
        $stmt = $conn->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $pengguna = $stmt->fetch();

        if ($pengguna && password_verify($password, $pengguna['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $pengguna['id'];
            gabung_keranjang_tamu($pengguna['id']);
            $stmt = $conn->prepare('DELETE FROM login_attempts WHERE attempt_key = ?');
            $stmt->execute([$kunci_percobaan]);
            pesan('Selamat datang, ' . $pengguna['name'] . '.');
            pindah($pengguna['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php');
        }
        $jumlah_gagal =
            $percobaan && $percobaan['expires_at'] > time() ? $percobaan['hits'] + 1 : 1;
        $stmt = $conn->prepare('INSERT INTO login_attempts (attempt_key, hits, expires_at) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE hits = VALUES(hits), expires_at = VALUES(expires_at)');
        $stmt->execute([$kunci_percobaan, $jumlah_gagal, time() + 900]);
        $error = 'Email atau kata sandi salah.';
    }
}

$judul = 'Masuk';
require 'includes/header.php';
?>
<section class="auth-card">
    <p class="label-section">AKUN RIA MART</p>
    <h1>Masuk ke akun</h1>
    <p class="text-muted">Lanjutkan belanja dan lihat pesanan Anda.</p>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_input() ?>
        <label>
            Email
            <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
        </label>
        <label>
            Kata sandi
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="btn btn-block" type="submit">Masuk</button>
    </form>
    <p>
        Belum punya akun?
        <a class="text-red" href="register.php">Daftar di sini</a>
        .
    </p>
    <a class="text-muted" href="setup_admin.php">Penyiapan admin pertama</a>
</section>
<?php require 'includes/footer.php'; ?>
