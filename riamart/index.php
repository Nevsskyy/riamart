<?php
require 'includes/functions.php';
$pengguna = ambil_pengguna();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';
    $stmt = $conn->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
    $stmt->execute([$id]);
    $barang = $stmt->fetch();

    if (!$barang) {
        $error = 'Produk tidak ditemukan.';
    } elseif ($aksi === 'tambah') {
        $jumlah = filter_var($_POST['jumlah'] ?? 1, FILTER_VALIDATE_INT);
        $kunci = kunci_keranjang();
        $jumlah_lama = $_SESSION[$kunci][$id] ?? 0;
        if (!$jumlah || $jumlah < 1 || $jumlah > 999 || $jumlah_lama + $jumlah > $barang['stock']) {
            $error = 'Jumlah melebihi stok tersedia atau tidak valid.';
        } else {
            $_SESSION[$kunci][$id] = $jumlah_lama + $jumlah;
            pesan($barang['name'] . ' ditambahkan ke keranjang.');
            pindah('index.php?' . http_build_query($_GET));
        }
    } elseif ($aksi === 'favorit') {
        if (!$pengguna) {
            pindah('login.php');
        }
        $stmt = $conn->prepare(
            'SELECT product_id FROM favorites WHERE user_id = ? AND product_id = ?'
        );
        $stmt->execute([$pengguna['id'], $id]);
        if ($stmt->fetch()) {
            $stmt = $conn->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?');
        } else {
            $stmt = $conn->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)');
        }
        $stmt->execute([$pengguna['id'], $id]);
        pindah('index.php?' . http_build_query($_GET));
    }
}

$cari = trim($_GET['cari'] ?? '');
$kategori = $_GET['kategori'] ?? '';
$promo = isset($_GET['promo']);
$hanya_favorit = isset($_GET['favorit']);
$urut = $_GET['urut'] ?? 'terbaru';
if ($hanya_favorit && !$pengguna) {
    pindah('login.php');
}

$where = ['p.active = 1'];
$parameter = [];
if ($cari !== '') {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ?)';
    $parameter[] = '%' . $cari . '%';
    $parameter[] = '%' . $cari . '%';
}
if (in_array($kategori, $kategori_produk, true)) {
    $where[] = 'p.category = ?';
    $parameter[] = $kategori;
}
if ($promo) {
    $where[] = 'p.old_price > p.price';
}
if ($hanya_favorit) {
    $where[] = 'EXISTS (SELECT 1 FROM favorites f WHERE f.product_id = p.id AND f.user_id = ?)';
    $parameter[] = $pengguna['id'];
}
$pilihan_urut = ['terbaru' => 'p.id ASC',
    'murah' => 'p.price ASC',
    'mahal' => 'p.price DESC',
    'nama' => 'p.name ASC',
];
$order_by = $pilihan_urut[$urut] ?? $pilihan_urut['terbaru'];
$stmt = $conn->prepare(
    'SELECT p.* FROM products p WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order_by
);
$stmt->execute($parameter);
$produk = $stmt->fetchAll();
$favorit = [];
if ($pengguna) {
    $stmt = $conn->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$pengguna['id']]);
    $favorit = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
$judul = $hanya_favorit ? 'Favorit Saya' : ($promo ? 'Promo Hemat' : 'Katalog Produk');
require 'includes/header.php';
?>
<?php if (!$cari && !$kategori && !$promo && !$hanya_favorit): ?>
    <section class="hero">
        <div>
            <p class="label-section">MINIMARKET PILIHAN KELUARGA</p>
            <h1>
                Kebutuhan harian,
                <br>
                harga
                <span>bersahabat.</span>
            </h1>
            <p>Dari sembako sampai kebutuhan rumah, semua ada di Ria Mart.</p>
            <a href="#katalog" class="btn btn-white">Mulai Belanja →</a>
        </div>
        <div class="hero-produk" aria-hidden="true">
            <img src="assets/product-1.svg" alt="">
            <img src="assets/product-5.svg" alt="">
            <img src="assets/product-2.svg" alt="">
        </div>
    </section>
    <div class="keunggulan">
        <span>✓ Produk pilihan</span>
        <span>✓ Harga bersahabat</span>
        <span>✓ Ambil di toko</span>
        <span>✓ Stok terpantau</span>
    </div>
<?php endif; ?>
<section id="katalog" class="section">
    <div class="section-heading">
        <div>
            <p class="label-section">BELANJA SESUAI KEBUTUHAN</p>
            <h1><?= e($judul) ?></h1>
        </div>
        <span class="text-muted"><?= count($produk) ?> produk ditemukan</span>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="get" class="filter-bar">
        <input type="hidden" name="cari" value="<?= e($cari) ?>">
        <?php if ($promo): ?>
            <input type="hidden" name="promo" value="1">
        <?php endif; ?>
        <?php if ($hanya_favorit): ?>
            <input type="hidden" name="favorit" value="1">
        <?php endif; ?>
        <select name="kategori" aria-label="Kategori produk">
            <option value="">Semua Kategori</option>
            <?php foreach ($kategori_produk as $nama_kategori): ?>
                <option value="<?= e($nama_kategori) ?>" <?= $kategori === $nama_kategori ? 'selected' : '' ?>>
                    <?= e($nama_kategori) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="urut" aria-label="Urutkan produk">
            <?php foreach ( ['terbaru' => 'Rekomendasi', 'murah' => 'Harga terendah', 'mahal' => 'Harga tertinggi', 'nama' => 'Nama A–Z', ] as $nilai => $label ): ?>
                <option value="<?= $nilai ?>" <?= $urut === $nilai ? 'selected' : '' ?>> <?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Tampilkan</button>
        <a class="btn btn-outline" href="index.php">Reset</a>
    </form>
    <div class="product-grid">
        <?php foreach ($produk as $barang): ?>
            <article class="product-card">
                <a class="product-image" href="detail_produk.php?id=<?= $barang['id'] ?>">
                    <img src="<?= e(gambar_produk($barang['image'])) ?>" alt="<?= e($barang['name']) ?>" loading="lazy">
                    <?php if ( $barang['old_price'] > $barang['price'] ): ?>
                        <span class="badge">
                            Hemat
                            <?= (int) floor((1 - $barang['price'] / $barang['old_price']) * 100) ?>
                            %
                        </span>
                    <?php endif; ?>
                </a>
                <div class="product-info">
                    <small class="text-muted"><?= e($barang['category']) ?></small>
                    <h2>
                        <a href="detail_produk.php?id=<?= $barang['id'] ?>"> <?= e($barang['name']) ?></a>
                    </h2>
                    <p class="text-muted"><?= e($barang['unit']) ?></p>
                    <p class="harga">
                        <?= rupiah($barang['price']) ?>
                        <?php if ( $barang['old_price'] > $barang['price'] ): ?>
                            <del>
                                <?= rupiah($barang['old_price']) ?>
                            </del>
                        <?php endif; ?>
                    </p>
                    <p class="stok"><?= $barang['stock'] > 0 ? 'Stok: ' . $barang['stock'] : 'Stok habis' ?></p>
                    <div class="product-actions">
                        <form method="post">
                            <?= csrf_input() ?>
                            <input type="hidden" name="id" value="<?= $barang['id'] ?>">
                            <input type="hidden" name="aksi" value="tambah">
                            <button class="btn btn-small" <?= $barang['stock'] < 1 ? 'disabled' : '' ?>>
                                + Keranjang
                            </button>
                        </form>
                        <form method="post">
                            <?= csrf_input() ?>
                            <input type="hidden" name="id" value="<?= $barang['id'] ?>">
                            <input type="hidden" name="aksi" value="favorit">
                            <button class="btn-favorit <?= in_array((int) $barang['id'], $favorit, true) ? 'aktif' : '' ?>" aria-label="Favorit <?= e($barang['name']) ?>" aria-pressed="<?= in_array((int) $barang['id'], $favorit, true) ? 'true' : 'false' ?>">
                                ♥
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ( !$produk ): ?>
        <div class="empty-state">
            <h2>Belum ada produk</h2>
            <p>Coba kata kunci atau kategori lain. Favorit dapat disimpan dengan tombol hati.</p>
        </div>
    <?php endif; ?>
    <p class="catatan">Harga dan stok awal adalah contoh dan dapat disesuaikan oleh admin toko.</p>
</section>
<?php require 'includes/footer.php'; ?>
