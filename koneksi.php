<?php
mysqli_report(MYSQLI_REPORT_OFF);

// Untuk demonstrasi lokal, nilai default mengikuti konfigurasi project saat ini.
// Untuk hosting, atur variabel lingkungan DB_HOST, DB_USER, DB_PASS, dan DB_NAME.
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db_name = getenv('DB_NAME') ?: 'db_portal berita/blog komunitas';

$koneksi = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if (!$koneksi) {
    error_log('WargaNet database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    exit('Website sedang mengalami kendala koneksi. Silakan coba kembali.');
}

if (!mysqli_set_charset($koneksi, 'utf8mb4')) {
    error_log('WargaNet failed to set utf8mb4 charset.');
    http_response_code(500);
    exit('Website tidak dapat memproses data saat ini.');
}
