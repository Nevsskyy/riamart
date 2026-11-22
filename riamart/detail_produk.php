<?php
require 'includes/functions.php';
$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
$stmt->execute([$id]);
$barang = $stmt->fetch();
if (!$barang) {
    http_response_code(404);
    exit('Produk tidak ditemukan.');
}
$judul = $barang['name'];
require 'includes/header.php';
?>
<section class="section">
    <a href="index.php" class="text-red">← Kembali ke katalog</a>
    <div class="detail-produk panel">
        <img src="<?= e(gambar_produk($barang['image'])) ?>" alt="<?= e($barang['name']) ?>">
        <div>
            <p class="label-section"><?= e($barang['category']) ?></p>
            <h1><?= e($barang['name']) ?></h1>
            <p class="text-muted"><?= e($barang['unit']) ?></p>
            <p><?= nl2br(e($barang['description'])) ?></p>
            <p class="harga harga-besar">
                <?= rupiah($barang['price']) ?>
                <?php if ( $barang['old_price'] > $barang['price'] ): ?>
                    <del>
                        <?= rupiah($barang['old_price']) ?>
                    </del>
                <?php endif; ?>
            </p>
            <p class="stok">
                Stok:
                <?= $barang['stock'] ?>
                kemasan
            </p>
            <form action="index.php" method="post" class="form-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= $barang['id'] ?>">
                <input type="hidden" name="aksi" value="tambah">
                <label>
                    Jumlah
                    <input type="number" name="jumlah" value="1" min="1" max="<?= min(999, $barang['stock']) ?>" required>
                </label>
                <button class="btn" <?= $barang['stock'] < 1 ? 'disabled' : '' ?>> Tambah ke Keranjang</button>
            </form>
            <p class="catatan">Gambar merupakan ilustrasi produk.</p>
        </div>
    </div>
</section>
<?php require 'includes/footer.php'; ?>
