
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

// 6. Ambil gambar artikel milik penulis yang sedang login
$stmt = mysqli_prepare(
    $koneksi,
    'SELECT gambar
     FROM artikel
     WHERE id_artikel = ? AND id_user = ?
     LIMIT 1'
);

if (!$stmt) {
    error_log('WargaNet delete select prepare failed: ' . mysqli_error($koneksi));
    header('Location: index.php');
    exit;
}

mysqli_stmt_bind_param($stmt, 'ii', $id, $userId);

if (!mysqli_stmt_execute($stmt)) {
    error_log('WargaNet delete select execute failed: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}

$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;

mysqli_stmt_close($stmt);

mysqli_stmt_close($stmt);

// 7. Jika artikel ditemukan, lakukan penghapusan
if ($row) {

    $del = mysqli_prepare(
        $koneksi,
        'DELETE FROM artikel
         WHERE id_artikel = ? AND id_user = ?'
    );

    if (!$del) {
        error_log('WargaNet delete prepare failed: ' . mysqli_error($koneksi));
        header('Location: index.php');
        exit;
    }

    mysqli_stmt_bind_param($del, 'ii', $id, $userId);

    $execute = mysqli_stmt_execute($del);

    // Pastikan tepat satu artikel berhasil dihapus
    $berhasil = $execute && mysqli_stmt_affected_rows($del) === 1;

    if (!$execute) {
        error_log('WargaNet delete execute failed: ' . mysqli_stmt_error($del));
    }

    mysqli_stmt_close($del);

    // 8. Hapus gambar hanya jika artikel berhasil dihapus
    if ($berhasil && !empty($row['gambar'])) {

        $nama_gambar = basename((string) $row['gambar']);

        $upload_dir = realpath(__DIR__ . '/../uploads');

        if ($upload_dir !== false) {

            $file = realpath(
                $upload_dir . DIRECTORY_SEPARATOR . $nama_gambar
            );

            // Pastikan file benar-benar berada di dalam folder uploads
            if (
                $file !== false &&
                strpos($file, $upload_dir . DIRECTORY_SEPARATOR) === 0 &&
                is_file($file)
            ) {
                if (!unlink($file)) {
                    error_log('WargaNet failed to delete image: ' . $nama_gambar);
                }
            }
        }
    }
}

// 9. Kembali ke halaman artikel penulis
header('Location: index.php');
exit;
