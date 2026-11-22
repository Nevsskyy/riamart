<?php
require 'includes/functions.php';
$error = '';
$nama = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $nama = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';

    if (mb_strlen($nama) < 2 || mb_strlen($nama) > 80) {
        $error = 'Nama harus terdiri dari 2–80 karakter.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $error = 'Masukkan email yang valid.';
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = 'Kata sandi harus terdiri dari 8–72 byte.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi kata sandi tidak sama.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar.';
        } else {
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO users (id, name, email, password_hash, role) VALUES (?, ?, ?, ?, 'customer')"
                );
                $stmt->execute([
                    buat_id(),
                    $nama,
                    $email,
                    password_hash($password, PASSWORD_BCRYPT),
                ]);
                pesan('Pendaftaran berhasil. Silakan masuk menggunakan akun Anda.');
                pindah('login.php');
            } catch (PDOException $e) {
                $error = 'Pendaftaran belum berhasil. Email mungkin sudah digunakan.';
            }
        }
    }
}
$judul = 'Daftar';
require 'includes/header.php';
?>
<section class="auth-card">
    <p class="label-section">PELANGGAN RIA MART</p>
    <h1>Buat akun</h1>
    <p class="text-muted">Simpan produk favorit dan belanja lebih mudah.</p>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_input() ?>
        <label>
            Nama lengkap
            <input name="nama" value="<?= e($nama) ?>" required maxlength="80" autocomplete="name">
        </label>
        <label>
            Email
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
        <button class="btn btn-block" type="submit">Daftar</button>
    </form>
    <p>
        Sudah punya akun?
        <a class="text-red" href="login.php">Masuk di sini</a>
        .
    </p>
</section>
<?php require 'includes/footer.php'; ?>
