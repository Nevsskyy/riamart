<?php
// Fungsi yang dipakai bersama oleh halaman pelanggan dan admin.
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e($teks)
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka)
{
    return 'Rp' . number_format((float) $angka, 0, ',', '.');
}

function pindah($halaman)
{
    header('Location: ' . $halaman);
    exit();
}

function pesan($teks)
{
    $_SESSION['pesan'] = $teks;
}

function csrf_input()
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function cek_csrf()
{
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Sesi formulir tidak valid. Kembali ke halaman sebelumnya lalu muat ulang.');
    }
}

function ambil_pengguna()
{
    global $conn;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $conn->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function harus_login($prefix = '')
{
    $pengguna = ambil_pengguna();
    if (!$pengguna) {
        pindah($prefix . 'login.php');
    }
    return $pengguna;
}

function harus_admin()
{
    global $conn;
    if (!$conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch()) {
        pindah('../setup_admin.php');
    }
    $pengguna = harus_login('../');
    if ($pengguna['role'] !== 'admin') {
        http_response_code(403);
        exit('Halaman ini hanya dapat diakses oleh admin.');
    }
    return $pengguna;
}

function buat_id()
{
    $id = bin2hex(random_bytes(16));
    return substr($id, 0, 8) .
        '-' .
        substr($id, 8, 4) .
        '-' .
        substr($id, 12, 4) .
        '-' .
        substr($id, 16, 4) .
        '-' .
        substr($id, 20);
}

function kunci_keranjang()
{
    return 'cart_' . ($_SESSION['user_id'] ?? 'tamu');
}

function isi_keranjang()
{
    global $conn;
    $keranjang = $_SESSION[kunci_keranjang()] ?? [];
    if (!$keranjang) {
        return [];
    }
    $tanda = implode(',', array_fill(0, count($keranjang), '?'));
    $stmt = $conn->prepare(
        "SELECT * FROM products WHERE active = 1 AND id IN ($tanda) ORDER BY id"
    );
    $stmt->execute(array_keys($keranjang));
    $produk = $stmt->fetchAll();
    foreach ($produk as &$barang) {
        $barang['jumlah'] = $keranjang[$barang['id']];
        $barang['subtotal'] = $barang['price'] * $barang['jumlah'];
    }
    return $produk;
}

function gabung_keranjang_tamu($user_id)
{
    global $conn;
    $kunci = 'cart_' . $user_id;
    foreach ($_SESSION['cart_tamu'] ?? [] as $id => $jumlah) {
        $stmt = $conn->prepare('SELECT stock FROM products WHERE id = ? AND active = 1');
        $stmt->execute([$id]);
        $produk = $stmt->fetch();
        if ($produk && $produk['stock'] > 0) {
            $_SESSION[$kunci][$id] = min($produk['stock'], ($_SESSION[$kunci][$id] ?? 0) + $jumlah);
        }
    }
    unset($_SESSION['cart_tamu']);
}

function gambar_produk($lokasi, $prefix = '')
{
    if (strpos($lokasi, 'data:image/') === 0) {
        return $lokasi;
    }
    return $prefix . ltrim($lokasi, '/');
}

function unggah_gambar($nama_input, $awalan)
{
    if (empty($_FILES[$nama_input]) || $_FILES[$nama_input]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    $file = $_FILES[$nama_input];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 1048576) {
        throw new Exception('Unggah gambar maksimal 1 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $format = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($format[$info['mime']])) {
        throw new Exception('Gambar harus berupa JPG, PNG, atau WEBP.');
    }
    $nama_file = $awalan . '_' . bin2hex(random_bytes(12)) . '.' . $format[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $nama_file)) {
        throw new Exception('Gambar gagal disimpan. Pastikan folder uploads dapat ditulis.');
    }
    return 'uploads/' . $nama_file;
}

function status_berikutnya($status)
{
    $urutan = ['Menunggu Pembayaran' => ['Diproses', 'Dibatalkan'],
        'Menunggu Verifikasi' => ['Diproses', 'Dibatalkan'],
        'Diproses' => ['Siap Diambil', 'Dibatalkan'],
        'Siap Diambil' => ['Selesai', 'Dibatalkan'],
        'Selesai' => [],
        'Dibatalkan' => [],
    ];
    return $urutan[$status] ?? [];
}

function waktu_pesanan($waktu)
{
    $tanggal = new DateTime($waktu, new DateTimeZone('UTC'));
    $tanggal->setTimezone(new DateTimeZone('Asia/Jakarta'));
    return $tanggal->format('d/m/Y H:i') . ' WIB';
}

$kategori_produk = ['Sembako',
    'Makanan',
    'Minuman',
    'Rumah Tangga',
    'Perawatan Diri',
    'Ibu & Anak',
];
