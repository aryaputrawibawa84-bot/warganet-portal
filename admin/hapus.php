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

$gambar = null;
$berhasil = false;

try {
    // Mulai transaksi database
    if (!mysqli_begin_transaction($koneksi)) {
        throw new RuntimeException('Gagal memulai transaksi database.');
    }

    // Ambil nama gambar artikel
    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT gambar FROM artikel WHERE id_artikel = ? LIMIT 1'
    );

    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan query SELECT artikel.');
    }

    if (!mysqli_stmt_bind_param($stmt, 'i', $id)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Gagal mengikat parameter SELECT artikel.');
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Gagal mengambil data artikel.');
    }

    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Gagal memperoleh hasil query artikel.');
    }

    $row = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    // Pastikan artikel ditemukan
    if (!$row) {
        mysqli_rollback($koneksi);
        header('Location: index.php');
        exit;
    }

    $gambar = $row['gambar'] ?? null;

    // Hapus artikel dari database
    $del = mysqli_prepare(
        $koneksi,
        'DELETE FROM artikel WHERE id_artikel = ?'
    );

    if (!$del) {
        throw new RuntimeException('Gagal menyiapkan query DELETE artikel.');
    }

    if (!mysqli_stmt_bind_param($del, 'i', $id)) {
        mysqli_stmt_close($del);
        throw new RuntimeException('Gagal mengikat parameter DELETE artikel.');
    }

    if (!mysqli_stmt_execute($del)) {
        mysqli_stmt_close($del);
        throw new RuntimeException('Gagal menghapus artikel dari database.');
    }

    // Pastikan tepat satu artikel terhapus
    $affected_rows = mysqli_stmt_affected_rows($del);

    mysqli_stmt_close($del);

    if ($affected_rows !== 1) {
        throw new RuntimeException('Jumlah artikel yang terhapus tidak sesuai.');
    }

    // Simpan perubahan database
    if (!mysqli_commit($koneksi)) {
        throw new RuntimeException('Gagal menyimpan transaksi penghapusan.');
    }

    $berhasil = true;

} catch (Throwable $e) {
    // Batalkan transaksi jika masih berjalan
    mysqli_rollback($koneksi);

    error_log(
        'WargaNet admin/hapus.php: ' . $e->getMessage()
    );
}

// Hapus gambar setelah artikel berhasil dihapus dari database
if ($berhasil && !empty($gambar)) {
    $upload_dir = __DIR__ . '/../uploads';

    delete_uploaded_image($gambar, $upload_dir);
}

// Kembali ke halaman daftar artikel
header('Location: index.php');
exit;