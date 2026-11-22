<?php
require 'includes/functions.php';
$pengguna = harus_login();
$kode = trim($_GET['kode'] ?? '');
$sql = 'SELECT * FROM orders WHERE user_id = ?';
$parameter = [$pengguna['id']];
if ($kode !== '') {
    $sql .= ' AND code = ?';
    $parameter[] = $kode;
}
$sql .= ' ORDER BY created_at DESC, id DESC';
$stmt = $conn->prepare($sql);
$stmt->execute($parameter);
$pesanan = $stmt->fetchAll();
$judul = 'Pesanan Saya';
require 'includes/header.php';
?>
<section class="section">
    <div class="section-heading">
        <div>
            <p class="label-section">RIWAYAT BELANJA</p>
            <h1>Pesanan Saya</h1>
        </div>
        <a href="order_status.php" class="btn btn-outline">Muat Ulang / Semua Pesanan</a>
    </div>
    <?php if ( !$pesanan ): ?>
        <div class="empty-state">
            <h2>Pesanan belum ada</h2>
            <p>Pesanan hanya ditampilkan kepada akun pemiliknya.</p>
            <a href="index.php" class="btn">Mulai Belanja</a>
        </div>
    <?php endif; ?>
    <?php foreach ($pesanan as $order): ?>
        <article class="panel order-card">
            <div class="section-heading">
                <div>
                    <h2><?= e($order['code']) ?></h2>
                    <small class="text-muted"><?= waktu_pesanan($order['created_at']) ?></small>
                </div>
                <span class="status <?= $order['status'] === 'Dibatalkan' ? 'status-batal' : '' ?>">
                    <?= e($order['status']) ?>
                </span>
            </div>
            <?php
            $stmt = $conn->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
            $stmt->execute([$order['id']]);
            $detail = $stmt->fetchAll();
            ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Jumlah</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail as $barang): ?>
                            <tr>
                                <td>
                                    <?= e($barang['name']) ?>
                                    <small class="text-muted">
                                        ·
                                        <?= e($barang['unit']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?= $barang['qty'] ?>
                                </td>
                                <td><?= rupiah($barang['qty'] * $barang['price']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="total-row">
                <span>
                    <?= e($order['method']) ?>
                    · Pengambil:
                    <?= e($order['name']) ?>
                </span>
                <strong><?= rupiah($order['total']) ?></strong>
            </div>
            <?php if ($order['note']): ?>
                <p class="text-muted">Catatan: <?= e($order['note']) ?></p>
            <?php endif; ?>
            <?php if ( $order['status'] === 'Menunggu Pembayaran' ): ?>
                <p class="alert">Silakan bayar tunai saat mengambil di toko. Admin akan mengonfirmasi pembayaran.</p>
            <?php elseif ( $order['status'] === 'Menunggu Verifikasi' ): ?>
                <p class="alert">Bukti QRIS menunggu pemeriksaan admin toko.</p>
            <?php elseif ( $order['status'] === 'Siap Diambil' ): ?>
                <p class="alert alert-success">Pesanan siap diambil. Tunjukkan kode pesanan kepada petugas toko.</p>
            <?php elseif ( $order['status'] === 'Diproses' ): ?>
                <p class="alert">Pembayaran dikonfirmasi. Belanjaan sedang disiapkan.</p>
            <?php elseif ( $order['status'] === 'Selesai' ): ?>
                <p class="text-muted">Pesanan selesai. Terima kasih telah berbelanja di Ria Mart.</p>
            <?php else: ?>
                <p class="text-muted">Pesanan dibatalkan. Hubungi toko apabila perlu pengembalian pembayaran.</p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php require 'includes/footer.php'; ?>
