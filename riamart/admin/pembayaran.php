<?php
require '../includes/functions.php';
$admin = harus_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $gambar_baru = '';
    try {
        if (($_POST['aksi'] ?? '') === 'nonaktifkan') {
            $qris = '';
        } else {
            $gambar_baru = unggah_gambar('qris', 'QRIS');
            if (!$gambar_baru) {
                throw new Exception('Pilih gambar QRIS toko terlebih dahulu.');
            }
            $qris = $gambar_baru;
        }
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'qris'");
        $stmt->execute([$qris]);
        pesan('Pengaturan QRIS berhasil disimpan.');
        pindah('pembayaran.php');
    } catch (Exception $e) {
        if ($gambar_baru) {
            @unlink(__DIR__ . '/../' . $gambar_baru);
        }
        $error = $e instanceof PDOException ? 'QRIS belum berhasil disimpan.' : $e->getMessage();
    }
}
$qris = $conn
    ->query("SELECT setting_value FROM settings WHERE setting_key = 'qris'")
    ->fetchColumn();
$judul = 'Pengaturan Pembayaran';
$prefix = '../';
require '../includes/header.php';
?>
<section class="section">
    <div class="section-heading">
        <div>
            <p class="label-section">RUANG PENGELOLA</p>
            <h1>Pembayaran Toko</h1>
        </div>
    </div>
    <nav class="admin-nav">
        <a href="dashboard.php">Pesanan</a>
        <a href="produk.php">Produk & Stok</a>
        <a class="aktif" href="pembayaran.php">Pembayaran</a>
    </nav>
    <div class="panel panel-kecil">
        <h2>Kode QRIS</h2>
        <p>Pembayaran tunai selalu tersedia. Unggah kode QRIS resmi toko untuk mengaktifkan pilihan QRIS pada checkout.</p>
        <p class="alert">Bukti QRIS diperiksa manual oleh admin. Konfirmasi pembayaran hanya setelah dana diterima toko.</p>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($qris): ?>
            <img class="qris-image" src="<?= e(gambar_produk($qris, '../')) ?>" alt="Kode QRIS toko">
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <input type="hidden" name="aksi" value="simpan">
            <label>
                Gambar QRIS
                <input type="file" name="qris" accept="image/jpeg,image/png,image/webp" required>
                <small>JPG/PNG/WEBP maksimal 1 MB.</small>
            </label>
            <button class="btn">Simpan QRIS</button>
        </form>
        <?php if ($qris): ?>
            <form method="post" class="jarak-atas" data-konfirmasi="Nonaktifkan pembayaran QRIS?">
                <?= csrf_input() ?>
                <input type="hidden" name="aksi" value="nonaktifkan">
                <button class="btn btn-danger">Nonaktifkan QRIS</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php require '../includes/footer.php'; ?>
