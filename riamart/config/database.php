<?php
$host = getenv('RIAMART_DB_HOST') ?: '127.0.0.1';
$port = getenv('RIAMART_DB_PORT') ?: '3306';
$nama_database = getenv('RIAMART_DB_NAME') ?: 'riamart';
$username = getenv('RIAMART_DB_USER') ?: 'root';
$password = getenv('RIAMART_DB_PASSWORD') ?: '';

try {
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$nama_database;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $conn->exec("SET time_zone = '+00:00'");
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(503);
    exit(
        'Koneksi database gagal. Periksa config/database.php dan pastikan database riamart sudah diimpor.'
    );
}
