<?php
require 'includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pindah('index.php');
}
cek_csrf();
unset($_SESSION['user_id']);
session_regenerate_id(true);
$_SESSION['csrf'] = bin2hex(random_bytes(32));
pesan('Anda sudah keluar dari akun.');
pindah('index.php');
