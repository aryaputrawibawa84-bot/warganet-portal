<?php
// 1. Pengaturan session cookie
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
require_once __DIR__ . '/../includes/tampilan.php';
require_once __DIR__ . '/../koneksi.php';

// 2. Pastikan hanya penulis yang dapat mengakses
$userId = valid_positive_int($_SESSION['id_user'] ?? null);

if (
    $userId === null ||
    ($_SESSION['role'] ?? '') !== 'penulis'
) {
    header('Location: ../login.php');
    exit;
}

// 3. Penghapusan hanya boleh melalui POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// 4. Validasi CSRF
require_csrf();

// 5. Validasi ID artikel
$id = valid_positive_int($_POST['id_artikel'] ?? null);

if ($id === null) {
    header('Location: index.php');
    exit;
}

$gambar_lama = null;
$berhasil = false;

// 6. Mulai transaksi database
if (!mysqli_begin_transaction($koneksi)) {
    error_log(
        'WargaNet delete transaction begin failed: ' .
        mysqli_error($koneksi)
    );

    header('Location: index.php');
    exit;
}

try {

    // 7. Ambil gambar artikel sekaligus memastikan kepemilikan
    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT gambar
         FROM artikel
         WHERE id_artikel = ? AND id_user = ?
         LIMIT 1'
    );

    if (!$stmt) {
        throw new RuntimeException(
            'Delete select prepare failed: ' . mysqli_error($koneksi)
        );
    }

    if (!mysqli_stmt_bind_param($stmt, 'ii', $id, $userId)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        throw new RuntimeException(
            'Delete select bind failed: ' . $error
        );
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        throw new RuntimeException(
            'Delete select execute failed: ' . $error
        );
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;

    mysqli_stmt_close($stmt);

    // Artikel tidak ditemukan atau bukan milik penulis
    if (!$row) {
        mysqli_rollback($koneksi);
        header('Location: index.php');
        exit;
    }

    $gambar_lama = (string) ($row['gambar'] ?? '');

    // 8. Hapus artikel dengan pemeriksaan kepemilikan
    $del = mysqli_prepare(
        $koneksi,
        'DELETE FROM artikel
         WHERE id_artikel = ? AND id_user = ?'
    );

    if (!$del) {
        throw new RuntimeException(
            'Delete prepare failed: ' . mysqli_error($koneksi)
        );
    }

    if (!mysqli_stmt_bind_param($del, 'ii', $id, $userId)) {
        $error = mysqli_stmt_error($del);
        mysqli_stmt_close($del);

        throw new RuntimeException(
            'Delete bind failed: ' . $error
        );
    }

    if (!mysqli_stmt_execute($del)) {
        $error = mysqli_stmt_error($del);
        mysqli_stmt_close($del);

        throw new RuntimeException(
            'Delete execute failed: ' . $error
        );
    }

    // Pastikan tepat satu artikel berhasil dihapus
    $jumlah_dihapus = mysqli_stmt_affected_rows($del);

    mysqli_stmt_close($del);

    if ($jumlah_dihapus !== 1) {
        throw new RuntimeException(
            'Delete affected rows tidak sesuai: ' . $jumlah_dihapus
        );
    }

    // 9. Simpan perubahan database
    if (!mysqli_commit($koneksi)) {
        throw new RuntimeException(
            'Delete commit failed: ' . mysqli_error($koneksi)
        );
    }

    $berhasil = true;

} catch (Throwable $e) {

    mysqli_rollback($koneksi);

    error_log(
        'WargaNet delete article error: ' . $e->getMessage()
    );
}

// 10. Hapus gambar hanya jika artikel berhasil dihapus
if ($berhasil && $gambar_lama !== null && $gambar_lama !== '') {
    delete_uploaded_image(
        $gambar_lama,
        __DIR__ . '/../uploads'
    );
}

// 11. Kembali ke halaman artikel penulis
header('Location: index.php');
exit;