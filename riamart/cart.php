<?php
require 'includes/functions.php';
$pengguna = ambil_pengguna();
$error = '';
$kunci = kunci_keranjang();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($aksi === 'hapus') {
        unset($_SESSION[$kunci][$id]);
        pindah('cart.php');
    }
    if ($aksi === 'jumlah') {
        $jumlah = filter_var($_POST['jumlah'] ?? 0, FILTER_VALIDATE_INT);
        $stmt = $conn->prepare('SELECT stock FROM products WHERE id = ? AND active = 1');
        $stmt->execute([$id]);
        $barang = $stmt->fetch();
        if (!$barang || !$jumlah || $jumlah < 1 || $jumlah > 999 || $jumlah > $barang['stock']) {
            $error = 'Jumlah tidak valid atau melebihi stok tersedia.';
        } elseif (isset($_SESSION[$kunci][$id])) {
            $_SESSION[$kunci][$id] = $jumlah;
            pindah('cart.php');
        }
    }
    if ($aksi === 'checkout') {
        if (!$pengguna) {
            pesan('Silakan masuk untuk melanjutkan pembayaran. Keranjang Anda tetap tersimpan.');
            pindah('login.php');
        }
        $nama = trim($_POST['nama'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');
        $catatan = mb_substr(trim($_POST['catatan'] ?? ''), 0, 500);
        $metode = $_POST['metode'] ?? '';
        $kode_checkout = $_POST['kode_checkout'] ?? '';
        $bukti = '';

        try {
            // Kiriman ulang formulir diarahkan ke pesanan yang sudah dibuat.
            $stmt = $conn->prepare('SELECT code FROM orders WHERE user_id = ? AND request_key = ?');
            $stmt->execute([$pengguna['id'], $kode_checkout]);
            $pesanan_lama = $stmt->fetch();
            if ($pesanan_lama) {
                pindah('order_status.php?kode=' . urlencode($pesanan_lama['code']));
            }

            if (
                !is_string($kode_checkout) ||
                !hash_equals($_SESSION['kode_checkout'] ?? '', $kode_checkout)
            ) {
                throw new Exception('Formulir checkout sudah berakhir. Muat ulang halaman.');
            }
            if (
                mb_strlen($nama) < 2 ||
                mb_strlen($nama) > 80 ||
                !preg_match('/^\+?[0-9\s-]{9,18}$/D', $telepon)
            ) {
                throw new Exception('Isi nama dan nomor telepon yang valid.');
            }
            if (!in_array($metode, ['Tunai', 'QRIS'], true)) {
                throw new Exception('Pilih metode pembayaran.');
            }
            if ($metode === 'QRIS') {
                $qris_toko = $conn
                    ->query("SELECT setting_value FROM settings WHERE setting_key = 'qris'")
                    ->fetchColumn();
                if (!$qris_toko) {
                    throw new Exception('QRIS belum tersedia. Pilih pembayaran tunai.');
                }
                $bukti = unggah_gambar('bukti_bayar', 'BUKTI');
                if (!$bukti) {
                    throw new Exception('Unggah bukti pembayaran QRIS terlebih dahulu.');
                }
            }
            $keranjang = $_SESSION[$kunci] ?? [];
            if (!$keranjang || count($keranjang) > 50) {
                throw new Exception('Keranjang harus berisi 1–50 jenis produk.');
            }
            ksort($keranjang);
            $conn->beginTransaction();
            $detail = [];
            $total = 0;

            // Kunci stok selama checkout agar pembeli tidak mengambil stok yang sama.
            foreach ($keranjang as $produk_id => $jumlah) {
                $stmt = $conn->prepare(
                    'SELECT * FROM products WHERE id = ? AND active = 1 FOR UPDATE'
                );
                $stmt->execute([$produk_id]);
                $barang = $stmt->fetch();
                if (!$barang || $jumlah < 1 || $jumlah > 999 || $jumlah > $barang['stock']) {
                    throw new Exception(
                        'Stok ' . ($barang['name'] ?? 'produk') . ' tidak mencukupi.'
                    );
                }
                $barang['jumlah'] = $jumlah;
                $detail[] = $barang;
                $total += $barang['price'] * $jumlah;
            }
            $order_id = buat_id();
            $kode_pesanan = 'RIA-' . gmdate('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $status = $metode === 'QRIS' ? 'Menunggu Verifikasi' : 'Menunggu Pembayaran';
            $stmt = $conn->prepare('INSERT INTO orders
                (id, user_id, code, name, phone, note, method, total, status, proof, request_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $order_id,
                $pengguna['id'],
                $kode_pesanan,
                $nama,
                $telepon,
                $catatan,
                $metode,
                $total,
                $status,
                $bukti,
                $kode_checkout,
            ]);

            foreach ($detail as $barang) {
                $stmt = $conn->prepare(
                    'INSERT INTO order_items (order_id, product_id, name, unit, price, qty) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $order_id,
                    $barang['id'],
                    $barang['name'],
                    $barang['unit'],
                    $barang['price'],
                    $barang['jumlah'],
                ]);
                $stmt = $conn->prepare(
                    'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'
                );
                $stmt->execute([$barang['jumlah'], $barang['id'], $barang['jumlah']]);
                if ($stmt->rowCount() !== 1) {
                    throw new Exception('Stok berubah. Silakan periksa keranjang kembali.');
                }
            }
            $conn->commit();
            $_SESSION[$kunci] = [];
            unset($_SESSION['kode_checkout']);
            pesan(
                'Pesanan berhasil dibuat. Pantau status dan ambil belanjaan di toko saat sudah siap.'
            );
            pindah('order_status.php?kode=' . urlencode($kode_pesanan));
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            if ($bukti && strpos($bukti, 'uploads/BUKTI_') === 0) {
                @unlink(__DIR__ . '/' . $bukti);
            }
            $error =
                $e instanceof PDOException
                    ? 'Pesanan belum berhasil dibuat. Periksa riwayat sebelum mencoba kembali.'
                    : $e->getMessage();
        }
    }
}

$isi = isi_keranjang();
$total = array_sum(array_column($isi, 'subtotal'));
$qris = $conn
    ->query("SELECT setting_value FROM settings WHERE setting_key = 'qris'")
    ->fetchColumn();
if (empty($_SESSION['kode_checkout'])) {
    $_SESSION['kode_checkout'] = bin2hex(random_bytes(24));
}
$judul = 'Keranjang dan Checkout';
require 'includes/header.php';
?>
<section class="section">
    <div class="section-heading">
        <h1>Keranjang Belanja</h1>
        <a href="index.php" class="btn btn-outline">Tambah Produk</a>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if (!$isi): ?>
        <div class="empty-state">
            <h2>Keranjang masih kosong</h2>
            <p>Pilih kebutuhan Anda melalui katalog produk.</p>
            <a href="index.php" class="btn">Lihat Katalog</a>
        </div>
    <?php else: ?>
        <div class="checkout-layout">
            <div class="panel">
                <?php foreach ($isi as $barang): ?>
                    <div class="cart-item">
                        <img src="<?= e(gambar_produk($barang['image'])) ?>" alt="<?= e($barang['name']) ?>">
                        <div class="cart-info">
                            <h2><?= e($barang['name']) ?></h2>
                            <p class="text-muted">
                                <?= e($barang['unit']) ?>
                                ·
                                <?= rupiah($barang['price']) ?>
                            </p>
                            <form method="post" class="form-inline">
                                <?= csrf_input() ?>
                                <input type="hidden" name="id" value="<?= $barang['id'] ?>">
                                <input type="number" name="jumlah" value="<?= $barang['jumlah'] ?>" min="1" max="<?= min(999, $barang['stock']) ?>" aria-label="Jumlah <?= e($barang['name']) ?>" required>
                                <button class="btn btn-small btn-outline" name="aksi" value="jumlah">Ubah</button>
                                <button class="btn btn-small btn-danger" name="aksi" value="hapus" formnovalidate>
                                    Hapus
                                </button>
                            </form>
                            <?php if ( $barang['jumlah'] > $barang['stock'] ): ?>
                                <p class="text-red">
                                    Stok saat ini:
                                    <?= $barang['stock'] ?>
                                    . Sesuaikan jumlah atau hapus produk.
                                </p>
                            <?php endif; ?>
                        </div>
                        <strong><?= rupiah($barang['subtotal']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <div class="total-row">
                    <span>Total belanja</span>
                    <strong><?= rupiah($total) ?></strong>
                </div>
            </div>
            <div class="panel">
                <h2>Data Pengambilan</h2>
                <?php if (!$pengguna): ?>
                    <p>Masuk terlebih dahulu untuk membuat pesanan. Isi keranjang akan digabung ke akun Anda.</p>
                    <a class="btn btn-block" href="login.php">Masuk / Daftar</a>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_input() ?>
                        <input type="hidden" name="aksi" value="checkout">
                        <input type="hidden" name="kode_checkout" value="<?= e($_SESSION['kode_checkout']) ?>">
                        <label>
                            Nama pengambil
                            <input name="nama" value="<?= e($_POST['nama'] ?? $pengguna['name']) ?>" required minlength="2" maxlength="80" autocomplete="name">
                        </label>
                        <label>
                            Nomor telepon
                            <input name="telepon" type="tel" value="<?= e($_POST['telepon'] ?? '') ?>" required maxlength="24" placeholder="08xxxxxxxxxx" autocomplete="tel">
                        </label>
                        <label>
                            Catatan (opsional)<textarea name="catatan" maxlength="500"><?= e($_POST['catatan'] ?? '') ?></textarea>
                        </label>
                        <label>
                            Metode pembayaran
                            <select name="metode" id="metodeBayar">
                                <option value="Tunai">Tunai di toko</option>
                                <option value="QRIS" <?= ($_POST['metode'] ?? '') === 'QRIS' && $qris ? 'selected' : '' ?> <?= !$qris ? 'disabled' : '' ?>>
                                    QRIS
                                    <?= !$qris ? ' (belum tersedia)' : '' ?>
                                </option>
                            </select>
                        </label>
                        <div id="bagianQris">
                            <?php if ($qris): ?>
                                <img class="qris-image" src="<?= e(gambar_produk($qris)) ?>" alt="Kode QRIS Ria Mart">
                            <?php endif; ?>
                            <label>
                                Bukti pembayaran QRIS
                                <input type="file" name="bukti_bayar" accept="image/jpeg,image/png,image/webp">
                                <small>Wajib untuk QRIS. JPG/PNG/WEBP maksimal 1 MB.</small>
                            </label>
                        </div>
                        <p class="catatan">
                            Pembayaran QRIS diperiksa manual oleh admin. Pesanan diambil langsung di toko.
                        </p>
                        <button class="btn btn-block" type="submit">Buat Pesanan · <?= rupiah($total) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php require 'includes/footer.php'; ?>
