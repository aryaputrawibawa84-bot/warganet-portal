
<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

require_once __DIR__ . '/../includes/security.php';
require __DIR__ . '/../koneksi.php';

// Pastikan pengguna adalah admin
$id_user = valid_positive_int($_SESSION['id_user'] ?? null);

if ($id_user === null || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Penghapusan hanya boleh melalui POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Validasi CSRF
require_csrf();

// Validasi ID artikel
$id = valid_positive_int($_POST['id_artikel'] ?? null);

if ($id === null) {
    header('Location: index.php');
    exit;
}

// Ambil nama gambar artikel
$stmt = mysqli_prepare(
    $koneksi,
    'SELECT gambar FROM artikel WHERE id_artikel = ? LIMIT 1'
);

if (!$stmt) {
    error_log('WargaNet: gagal menyiapkan query SELECT artikel.');
    header('Location: index.php');
    exit;
}

mysqli_stmt_bind_param($stmt, 'i', $id);

if (!mysqli_stmt_execute($stmt)) {
    error_log('WargaNet: gagal mengambil data gambar artikel.');
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}

$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;

mysqli_stmt_close($stmt);

// Artikel tidak ditemukan atau hasil query tidak tersedia
if (!$row) {
    header('Location: index.php');
    exit;
}

// Hapus data artikel dari database
$del = mysqli_prepare(
    $koneksi,
    'DELETE FROM artikel WHERE id_artikel = ?'
);

if (!$del) {
    error_log('WargaNet: gagal menyiapkan query DELETE artikel.');
    header('Location: index.php');
    exit;
}

mysqli_stmt_bind_param($del, 'i', $id);
$ok = mysqli_stmt_execute($del);

if (!$ok) {
    error_log('WargaNet: gagal menghapus artikel.');
    mysqli_stmt_close($del);
    header('Location: index.php');
    exit;
}

mysqli_stmt_close($del);

// Hapus gambar jika data artikel berhasil dihapus
$upload_dir = __DIR__ . '/../uploads';

if (!empty($row['gambar'])) {
    delete_uploaded_image($row['gambar'], $upload_dir);
}

header('Location: index.php');
exit;
