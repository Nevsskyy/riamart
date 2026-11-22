<?php
$prefix = $prefix ?? '';
$pengguna_nav = ambil_pengguna();
$jumlah_keranjang = array_sum($_SESSION[kunci_keranjang()] ?? []);
?>
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($judul ?? 'Katalog') ?> - Ria Mart</title>
        <link rel="icon" href="<?= $prefix ?>assets/favicon.svg" type="image/svg+xml">
        <link rel="stylesheet" href="<?= $prefix ?>style.css">
    </head>
    <body>
        <div class="info-bar">Ria Mart · Belanja harian, harga bersahabat.</div>
        <header class="navbar">
            <div class="container nav-utama">
                <a class="logo" href="<?= $prefix ?>index.php">ria<span>mart</span></a>
                <form action="<?= $prefix ?>index.php" method="get" class="pencarian">
                    <input type="search" name="cari" value="<?= e($_GET['cari'] ?? '') ?>" placeholder="Cari kebutuhan harian..." aria-label="Cari produk">
                    <button class="btn" type="submit">Cari</button>
                </form>
                <a href="<?= $prefix ?>cart.php" class="link-keranjang">
                    Keranjang
                    <b><?= $jumlah_keranjang ?></b>
                </a>
                <?php if ($pengguna_nav): ?>
                    <span class="nama-pengguna">Halo, <?= e($pengguna_nav['name']) ?></span>
                    <form action="<?= $prefix ?>logout.php" method="post">
                        <?= csrf_input() ?>
                        <button class="btn btn-outline" type="submit">Keluar</button>
                    </form>
                <?php else: ?>
                    <a href="<?= $prefix ?>login.php" class="btn btn-outline"> Masuk / Daftar</a>
                <?php endif; ?>
            </div>
            <nav class="container menu-nav" aria-label="Navigasi utama">
                <a href="<?= $prefix ?>index.php"> Katalog Produk</a>
                <a href="<?= $prefix ?>index.php?promo=1"> Promo Hemat</a>
                <a href="<?= $prefix ?>index.php?favorit=1"> Favorit Saya</a>
                <a href="<?= $prefix ?>order_status.php"> Pesanan Saya</a>
                <a href="<?= $prefix ?>admin/dashboard.php"> Kelola Toko</a>
                <span>Pesan online, ambil di toko</span>
            </nav>
        </header>
        <main class="container">
            <?php if (!empty($_SESSION['pesan'])): ?>
                <div class="alert alert-success" role="status"><?= e($_SESSION['pesan']) ?></div>
                <?php unset($_SESSION['pesan']); ?>
            <?php endif; ?>
