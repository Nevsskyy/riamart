<?php
require '../includes/functions.php';
$admin = harus_admin();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $id = $_POST['id'] ?? '';
    $status_baru = $_POST['status'] ?? '';
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order || !in_array($status_baru, status_berikutnya($order['status']), true)) {
            throw new Exception('Perubahan status tidak diizinkan. Muat ulang daftar pesanan.');
        }
        // Pembatalan dan pengembalian stok harus berhasil bersamaan.
        if ($status_baru === 'Dibatalkan') {
            $stmt = $conn->prepare(
                'SELECT product_id, qty FROM order_items WHERE order_id = ? ORDER BY product_id'
            );
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $barang) {
                $update = $conn->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                $update->execute([$barang['qty'], $barang['product_id']]);
            }
        }
        $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status_baru, $id]);
        $conn->commit();
        pesan('Status pesanan berhasil diperbarui.');
        pindah('dashboard.php?' . http_build_query($_GET));
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e instanceof PDOException ? 'Status belum berhasil disimpan.' : $e->getMessage();
    }
}

$status_filter = $_GET['status'] ?? '';
$tanggal = $_GET['tanggal'] ?? '';
$cari = trim($_GET['cari'] ?? '');
$where = ['1 = 1'];
$parameter = [];
if (
    array_key_exists($status_filter, ['Menunggu Pembayaran' => 1,
        'Menunggu Verifikasi' => 1,
        'Diproses' => 1,
        'Siap Diambil' => 1,
        'Selesai' => 1,
        'Dibatalkan' => 1,
    ])
) {
    $where[] = 'o.status = ?';
    $parameter[] = $status_filter;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $tanggal)) {
    $where[] = 'DATE(DATE_ADD(o.created_at, INTERVAL 7 HOUR)) = ?';
    $parameter[] = $tanggal;
}
if ($cari !== '') {
    $where[] = '(o.code LIKE ? OR o.name LIKE ?)';
    $parameter[] = '%' . $cari . '%';
    $parameter[] = '%' . $cari . '%';
}
$stmt = $conn->prepare(
    'SELECT o.*, u.email FROM orders o JOIN users u ON u.id = o.user_id
    WHERE ' .
        implode(' AND ', $where) .
        ' ORDER BY o.created_at DESC, o.id DESC'
);
$stmt->execute($parameter);
$pesanan = $stmt->fetchAll();
$jumlah_pesanan = $conn->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$perlu_diproses = $conn
    ->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Selesai', 'Dibatalkan')")
    ->fetchColumn();
$pendapatan = $conn
    ->query(
        "SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('Diproses', 'Siap Diambil', 'Selesai')"
    )
    ->fetchColumn();
$stok_menipis = $conn
    ->query('SELECT COUNT(*) FROM products WHERE active = 1 AND stock < 10')
    ->fetchColumn();
$judul = 'Dashboard Admin';
$prefix = '../';
require '../includes/header.php';
?>
<section class="section">
    <div class="section-heading">
        <div>
            <p class="label-section">RUANG PENGELOLA</p>
            <h1>Dashboard Ria Mart</h1>
        </div>
        <a href="../index.php" class="btn btn-outline">Lihat Website</a>
    </div>
    <nav class="admin-nav">
        <a href="dashboard.php" class="aktif">Pesanan</a>
        <a href="produk.php">Produk & Stok</a>
        <a href="pembayaran.php">Pembayaran</a>
    </nav>
    <div class="ringkasan">
        <div class="panel">
            <small>Total pesanan</small>
            <strong><?= $jumlah_pesanan ?></strong>
        </div>
        <div class="panel">
            <small>Perlu ditindaklanjuti</small>
            <strong><?= $perlu_diproses ?></strong>
        </div>
        <div class="panel">
            <small>Pembayaran terkonfirmasi</small>
            <strong><?= rupiah($pendapatan) ?></strong>
        </div>
        <div class="panel">
            <small>Produk stok menipis</small>
            <strong><?= $stok_menipis ?></strong>
        </div>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="get" class="filter-bar">
        <input name="cari" placeholder="Kode / nama pemesan" value="<?= e($cari) ?>" aria-label="Cari pesanan">
        <input type="date" name="tanggal" value="<?= e($tanggal) ?>" aria-label="Tanggal pesanan WIB">
        <select name="status" aria-label="Status pesanan">
            <option value="">Semua Status</option>
            <?php foreach ( ['Menunggu Pembayaran', 'Menunggu Verifikasi', 'Diproses', 'Siap Diambil', 'Selesai', 'Dibatalkan', ] as $status ): ?>
                <option <?= $status_filter === $status ? 'selected' : '' ?>> <?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn">Tampilkan</button>
        <a href="dashboard.php" class="btn btn-outline">Reset</a>
    </form>
    <p class="text-muted">
        Menampilkan
        <?= count($pesanan) ?>
        pesanan. Ringkasan di atas mencakup seluruh pesanan.
    </p>
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
            <p>
                <strong><?= e($order['name']) ?></strong>
                ·
                <?= e($order['phone']) ?>
                ·
                <?= e($order['email']) ?>
            </p>
            <?php if ($order['note']): ?>
                <p class="text-muted">Catatan: <?= e($order['note']) ?></p>
            <?php endif; ?>
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
                                    (
                                    <?= e($barang['unit']) ?>
                                    )
                                </td>
                                <td><?= $barang['qty'] ?></td>
                                <td><?= rupiah($barang['qty'] * $barang['price']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="total-row">
                <span><?= e($order['method']) ?></span>
                <strong><?= rupiah($order['total']) ?></strong>
            </div>
            <?php if ($order['proof']): ?>
                <details class="bukti-bayar">
                    <summary>Lihat Bukti Pembayaran QRIS</summary>
                    <img src="<?= e(gambar_produk($order['proof'], '../')) ?>" alt="Bukti pembayaran <?= e($order['code']) ?>">
                </details>
            <?php endif; ?>
            <?php if (status_berikutnya($order['status'])): ?>
                <form method="post" class="form-inline form-status">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= e($order['id']) ?>">
                    <select name="status" aria-label="Ubah status <?= e($order['code']) ?>">
                        <?php foreach (status_berikutnya($order['status']) as $status): ?>
                            <option value="<?= e($status) ?>">
                                <?= $status === 'Diproses' ? 'Konfirmasi Lunas & Proses' : e($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn">Simpan Status</button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if ( !$pesanan ): ?>
        <div class="empty-state">Belum ada pesanan yang sesuai filter.</div>
    <?php endif; ?>
</section>
<?php require '../includes/footer.php'; ?>
