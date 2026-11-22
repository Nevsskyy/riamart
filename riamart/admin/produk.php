<?php
require '../includes/functions.php';
$admin = harus_admin();
$error = '';
$id = (int) ($_POST['id'] ?? ($_GET['edit'] ?? 0));
$form = ['name' => '',
    'category' => 'Sembako',
    'unit' => '',
    'price' => '',
    'old_price' => 0,
    'brand' => '',
    'badge' => '',
    'stock' => 0,
    'description' => '',
    'image' => 'assets/product-2.svg',
];
if ($id) {
    $stmt = $conn->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
    $stmt->execute([$id]);
    $form = $stmt->fetch();
    if (!$form) {
        pesan('Produk tidak ditemukan.');
        pindah('produk.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    if (($_POST['aksi'] ?? '') === 'arsip') {
        $stmt = $conn->prepare('UPDATE products SET active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        pesan('Produk diarsipkan. Riwayat pesanan tetap tersimpan.');
        pindah('produk.php');
    }
    $gambar_baru = '';
    foreach (['name', 'category', 'unit', 'brand', 'badge', 'description'] as $kolom) {
        $form[$kolom] = trim($_POST[$kolom] ?? '');
    }
    $form['price'] = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_INT);
    $form['old_price'] = filter_var($_POST['old_price'] ?? 0, FILTER_VALIDATE_INT);
    $form['stock'] = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);
    try {
        if (
            !$form['name'] ||
            mb_strlen($form['name']) > 120 ||
            !$form['unit'] ||
            mb_strlen($form['unit']) > 60 ||
            !in_array($form['category'], $kategori_produk, true)
        ) {
            throw new Exception('Lengkapi nama, kategori, dan kemasan produk.');
        }
        if (
            $form['price'] === false ||
            $form['price'] < 1 ||
            $form['price'] > 1000000000 ||
            $form['stock'] === false ||
            $form['stock'] < 0 ||
            $form['stock'] > 1000000
        ) {
            throw new Exception('Harga dan stok harus berupa bilangan bulat yang valid.');
        }
        if (
            $form['old_price'] === false ||
            $form['old_price'] < 0 ||
            $form['old_price'] > 1000000000 ||
            ($form['old_price'] > 0 && $form['old_price'] <= $form['price'])
        ) {
            throw new Exception(
                'Harga sebelum diskon harus lebih besar dari harga jual, atau isi 0.'
            );
        }
        if (
            !in_array($form['badge'], ['', 'Promo', 'Terlaris', 'Hemat', 'Segar'], true) ||
            mb_strlen($form['brand']) > 80 ||
            mb_strlen($form['description']) > 1000
        ) {
            throw new Exception('Periksa label, merek, dan deskripsi produk.');
        }
        $stmt = $conn->prepare(
            'SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND active = 1 AND id <> ?'
        );
        $stmt->execute([$form['name'], $id]);
        if ($stmt->fetch()) {
            throw new Exception('Nama produk sudah digunakan.');
        }
        $gambar_baru = unggah_gambar('gambar', 'PRODUK');
        $gambar = $gambar_baru ?: $form['image'];
        $parameter = [
            $form['name'],
            $form['category'],
            $form['unit'],
            $form['price'],
            $form['old_price'],
            $form['brand'],
            $gambar,
            $form['badge'],
            $form['stock'],
            $form['description'],
        ];
        if ($id) {
            $stmt = $conn->prepare('UPDATE products SET name = ?, category = ?, unit = ?, price = ?, old_price = ?,
                brand = ?, image = ?, badge = ?, stock = ?, description = ? WHERE id = ?');
            $parameter[] = $id;
        } else {
            $stmt = $conn->prepare('INSERT INTO products (name, category, unit, price, old_price, brand, image, badge, stock, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        }
        $stmt->execute($parameter);
        pesan('Data produk berhasil disimpan.');
        pindah('produk.php');
    } catch (Exception $e) {
        if ($gambar_baru) {
            @unlink(__DIR__ . '/../' . $gambar_baru);
        }
        $error = $e instanceof PDOException ? 'Produk belum berhasil disimpan.' : $e->getMessage();
    }
}
$cari = trim($_GET['cari'] ?? '');
$stmt = $conn->prepare(
    'SELECT * FROM products WHERE active = 1 AND (name LIKE ? OR category LIKE ?) ORDER BY id DESC'
);
$stmt->execute(['%' . $cari . '%', '%' . $cari . '%']);
$produk = $stmt->fetchAll();
$judul = 'Kelola Produk';
$prefix = '../';
require '../includes/header.php';
?>
<section class="section">
    <div class="section-heading">
        <div>
            <p class="label-section">RUANG PENGELOLA</p>
            <h1>Produk & Stok</h1>
        </div>
        <a class="btn btn-outline" href="../index.php">Lihat Website</a>
    </div>
    <nav class="admin-nav">
        <a href="dashboard.php">Pesanan</a>
        <a class="aktif" href="produk.php">Produk & Stok</a>
        <a href="pembayaran.php">Pembayaran</a>
    </nav>
    <div class="panel" id="formProduk">
        <h2><?= $id ? 'Edit Produk' : 'Tambah Produk' ?></h2>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="aksi" value="simpan">
            <div class="form-grid">
                <label>
                    Nama produk
                    <input name="name" value="<?= e($form['name']) ?>" maxlength="120" required>
                </label>
                <label>
                    Merek
                    <input name="brand" value="<?= e($form['brand']) ?>" maxlength="80">
                </label>
                <label>
                    Kategori
                    <select name="category">
                        <?php foreach ($kategori_produk as $kategori): ?>
                            <option <?= $form['category'] === $kategori ? 'selected' : '' ?>> <?= e($kategori) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Kemasan / satuan
                    <input name="unit" value="<?= e($form['unit']) ?>" maxlength="60" placeholder="Contoh: 1 liter" required>
                </label>
                <label>
                    Harga jual (Rp)
                    <input type="number" name="price" min="1" max="1000000000" value="<?= e($form['price']) ?>" required>
                </label>
                <label>
                    Harga sebelum diskon (Rp)
                    <input type="number" name="old_price" min="0" max="1000000000" value="<?= e($form['old_price']) ?>">
                    <small>Isi 0 bila tidak ada diskon.</small>
                </label>
                <label>
                    Stok kemasan
                    <input type="number" name="stock" min="0" max="1000000" value="<?= e($form['stock']) ?>" required>
                </label>
                <label>
                    Label produk
                    <select name="badge">
                        <?php foreach (['', 'Promo', 'Terlaris', 'Hemat', 'Segar'] as $label): ?>
                            <option value="<?= e($label) ?>" <?= $form['badge'] === $label ? 'selected' : '' ?>>
                                <?= $label ?: 'Tanpa label' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>
                Deskripsi<textarea name="description" maxlength="1000"><?= e($form['description']) ?></textarea>
            </label>
            <label>
                Foto produk
                <input type="file" name="gambar" accept="image/jpeg,image/png,image/webp">
                <small>Opsional. JPG/PNG/WEBP maksimal 1 MB. Kosongkan untuk mempertahankan gambar.</small>
            </label>
            <?php if ($id): ?>
                <img class="preview-produk" src="<?= e(gambar_produk($form['image'], '../')) ?>" alt="Gambar produk saat ini">
            <?php endif; ?>
            <div class="form-inline">
                <button class="btn">Simpan Produk</button>
                <a href="produk.php" class="btn btn-outline">Batal / Kosongkan</a>
            </div>
        </form>
    </div>
    <div class="section-heading">
        <h2>Daftar Produk</h2>
        <span class="text-muted"><?= count($produk) ?> produk</span>
    </div>
    <form method="get" class="filter-bar">
        <input name="cari" value="<?= e($cari) ?>" placeholder="Cari produk / kategori" aria-label="Cari produk admin">
        <button class="btn">Cari</button>
        <a href="produk.php" class="btn btn-outline">Reset</a>
    </form>
    <div class="panel table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Produk</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produk as $barang): ?>
                    <tr>
                        <td>
                            <img class="foto-tabel" src="<?= e(gambar_produk($barang['image'], '../')) ?>" alt="<?= e($barang['name']) ?>">
                        </td>
                        <td>
                            <strong><?= e($barang['name']) ?></strong>
                            <br>
                            <small class="text-muted"><?= e($barang['unit']) ?></small>
                        </td>
                        <td><?= e($barang['category']) ?></td>
                        <td><?= rupiah($barang['price']) ?></td>
                        <td><?= $barang['stock'] ?></td>
                        <td>
                            <div class="form-inline">
                                <a href="produk.php?edit=<?= $barang['id'] ?>#formProduk" class="btn btn-small btn-outline">
                                    Edit
                                </a>
                                <form method="post" data-konfirmasi="Arsipkan produk ini? Riwayat pesanan tetap tersimpan.">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="id" value="<?= $barang['id'] ?>">
                                    <input type="hidden" name="aksi" value="arsip">
                                    <button class="btn btn-small btn-danger">Arsipkan</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ( !$produk ): ?>
                    <tr>
                        <td colspan="6">Produk tidak ditemukan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require '../includes/footer.php'; ?>
