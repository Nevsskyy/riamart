<?php
require 'includes/functions.php';
if ($conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch()) {
    pesan('Admin sudah tersedia. Silakan masuk dengan akun admin Anda.');
    pindah('login.php');
}
$error = '';
$nama = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $nama = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    if (
        mb_strlen($nama) < 2 ||
        mb_strlen($nama) > 80 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 254
    ) {
        $error = 'Isi nama dan email dengan benar.';
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = 'Kata sandi harus terdiri dari 8–72 byte.';
    } elseif ($password !== ($_POST['konfirmasi'] ?? '')) {
        $error = 'Konfirmasi kata sandi tidak sama.';
    } else {
        try {
            $conn->beginTransaction();
            // Kunci penyiapan supaya dua permintaan tidak membuat dua admin pertama.
            $conn->query(
                "SELECT setting_value FROM settings WHERE setting_key = 'admin_setup' FOR UPDATE"
            );
            if ($conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch()) {
                throw new Exception('Admin sudah tersedia. Silakan masuk.');
            }
            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email sudah digunakan. Gunakan email lain untuk admin.');
            }
            $stmt = $conn->prepare(
                "INSERT INTO users (id, name, email, password_hash, role) VALUES (?, ?, ?, ?, 'admin')"
            );
            $stmt->execute([buat_id(), $nama, $email, password_hash($password, PASSWORD_BCRYPT)]);
            $conn->exec(
                "UPDATE settings SET setting_value = '1' WHERE setting_key = 'admin_setup'"
            );
            $conn->commit();
            pesan('Akun admin berhasil dibuat. Silakan masuk.');
            pindah('login.php');
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error =
                $e instanceof PDOException ? 'Akun admin belum berhasil dibuat.' : $e->getMessage();
        }
    }
}
$judul = 'Penyiapan Admin';
require 'includes/header.php';
?>
<section class="auth-card">
    <p class="label-section">PENYIAPAN TOKO</p>
    <h1>Buat admin pertama</h1>
    <p class="text-muted">Halaman ini hanya tersedia sebelum akun admin dibuat.</p>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_input() ?>
        <label>
            Nama admin
            <input name="nama" value="<?= e($nama) ?>" required maxlength="80" autocomplete="name">
        </label>
        <label>
            Email admin
            <input type="email" name="email" value="<?= e($email) ?>" required maxlength="254" autocomplete="email">
        </label>
        <label>
            Kata sandi
            <input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
        </label>
        <label>
            Ulangi kata sandi
            <input type="password" name="konfirmasi" required minlength="8" maxlength="72" autocomplete="new-password">
        </label>
        <button type="submit" class="btn btn-block">Buat Admin</button>
    </form>
</section>
<?php require 'includes/footer.php'; ?>
