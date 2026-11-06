```php
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

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/koneksi.php';

// ---------- Validasi ID artikel ----------
$id_artikel = valid_positive_int($_GET['id'] ?? null);

if ($id_artikel === null) {
    header('Location: index.php');
    exit;
}

// ---------- Validasi session pengguna ----------
$id_login = valid_positive_int($_SESSION['id_user'] ?? null);
$role_login = $_SESSION['role'] ?? '';

$role_valid = in_array($role_login, ['admin', 'penulis', 'pembaca'], true);

if ($id_login === null || !$role_valid) {
    unset($_SESSION['id_user'], $_SESSION['role'], $_SESSION['nama_lengkap']);
    session_regenerate_id(true);

    $sudah_login = false;
    $id_login = 0;
    $is_admin = false;
} else {
    $sudah_login = true;
    $is_admin = ($role_login === 'admin');
}

$link_artikel = 'detail.php?id=' . $id_artikel;

// Simpan tujuan kembali setelah login.
// Tidak menggunakan #komentar agar sesuai dengan validasi redirect login.php.
if (!$sudah_login && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['after_login'] = $link_artikel;
}

// ---------- Ambil artikel ----------
$sql_detail = "SELECT
        artikel.*,
        kategori.nama_kategori,
        users.nama_lengkap
    FROM artikel
    LEFT JOIN kategori ON artikel.id_kategori = kategori.id_kategori
    LEFT JOIN users ON artikel.id_user = users.id_user
    WHERE artikel.id_artikel = ? AND artikel.status = 'terbit'
    LIMIT 1";

$stmt = mysqli_prepare($koneksi, $sql_detail);

if (!$stmt) {
    error_log('WargaNet gagal menyiapkan query detail artikel: ' . mysqli_error($koneksi));
    http_response_code(500);
    exit('Terjadi kesalahan saat memuat artikel. Silakan coba kembali.');
}

mysqli_stmt_bind_param($stmt, 'i', $id_artikel);

if (!mysqli_stmt_execute($stmt)) {
    error_log('WargaNet gagal menjalankan query detail artikel: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    http_response_code(500);
    exit('Terjadi kesalahan saat memuat artikel. Silakan coba kembali.');
}

$query_detail = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

if (!$query_detail || mysqli_num_rows($query_detail) === 0) {
    if ($query_detail) {
        mysqli_free_result($query_detail);
    }

    http_response_code(404);
    echo "<script>alert('Artikel tidak ditemukan atau belum diterbitkan.'); window.location='index.php';</script>";
    exit;
}

$data = mysqli_fetch_assoc($query_detail);
mysqli_free_result($query_detail);

// ---------- Fungsi bantuan ----------
function hitung_panjang_teks(string $teks): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($teks, 'UTF-8');
    }

    $jumlah = preg_match_all('/./us', $teks, $hasil);

    return ($jumlah !== false) ? $jumlah : strlen($teks);
}

function inisial_nama(string $nama): string
{
    $nama = trim($nama);

    if ($nama === '') {
        return '?';
    }

    if (function_exists('mb_substr')) {
        $inisial = mb_substr($nama, 0, 1, 'UTF-8');

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($inisial, 'UTF-8')
            : strtoupper($inisial);
    }

    if (preg_match('/^./us', $nama, $hasil)) {
        return strtoupper($hasil[0]);
    }

    return strtoupper(substr($nama, 0, 1));
}

function gambar_artikel_tersedia(?string $filename): ?string
{
    if ($filename === null || trim($filename) === '') {
        return null;
    }

    $nama_file = basename($filename);
    $direktori = realpath(__DIR__ . '/uploads');

    if ($direktori === false) {
        return null;
    }

    $path_file = realpath($direktori . DIRECTORY_SEPARATOR . $nama_file);

    if (
        $path_file === false ||
        strpos($path_file, $direktori . DIRECTORY_SEPARATOR) !== 0 ||
        !is_file($path_file)
    ) {
        return null;
    }

    return 'uploads/' . rawurlencode($nama_file);
}

$gambar_artikel = gambar_artikel_tersedia($data['gambar'] ?? null);

// ---------- Proses komentar ----------
$error_komentar = '';
$isi_lama = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!$sudah_login) {
        header('Location: login.php');
        exit;
    }

    // Kirim komentar
    if (isset($_POST['kirim_komentar'])) {
        $isi = trim((string) ($_POST['isi_komentar'] ?? ''));
        $isi = preg_replace("/\r\n?/", "\n", $isi);

        if ($isi === null) {
            $isi = '';
        }

        $panjang = hitung_panjang_teks($isi);

        $terakhir = $_SESSION['last_comment'] ?? 0;
        $terakhir = is_numeric($terakhir) ? (int) $terakhir : 0;

        $isi_lama = $isi;

        if ($panjang < 2) {
            $error_komentar = 'Komentar terlalu pendek.';
        } elseif ($panjang > 1000) {
            $error_komentar = 'Komentar maksimal 1000 karakter.';
        } elseif (time() - $terakhir < 10) {
            $error_komentar = 'Tunggu beberapa detik sebelum mengirim komentar lagi.';
        } else {
            $ins = mysqli_prepare(
                $koneksi,
                'INSERT INTO komentar (id_artikel, id_user, isi_komentar) VALUES (?, ?, ?)'
            );

            if ($ins) {
                mysqli_stmt_bind_param($ins, 'iis', $id_artikel, $id_login, $isi);

                $ok = mysqli_stmt_execute($ins);

                if (!$ok) {
                    error_log('WargaNet gagal menyimpan komentar: ' . mysqli_stmt_error($ins));
                }

                mysqli_stmt_close($ins);

                if ($ok) {
                    $_SESSION['last_comment'] = time();

                    header('Location: ' . $link_artikel . '&k=ok#komentar');
                    exit;
                }
            } else {
                error_log('WargaNet gagal menyiapkan query komentar: ' . mysqli_error($koneksi));
            }

            $error_komentar = 'Komentar gagal disimpan. Silakan coba lagi.';
        }
    }

    // Hapus komentar
    elseif (isset($_POST['hapus_komentar'])) {
        $id_komentar = valid_positive_int($_POST['id_komentar'] ?? null);
        $berhasil_hapus = false;

        if ($id_komentar !== null) {
            if ($is_admin) {
                // Admin boleh menghapus komentar pada artikel ini.
                $del = mysqli_prepare(
                    $koneksi,
                    'DELETE FROM komentar WHERE id_komentar = ? AND id_artikel = ?'
                );

                if ($del) {
                    mysqli_stmt_bind_param($del, 'ii', $id_komentar, $id_artikel);
                }
            } else {
                // Pengguna biasa hanya boleh menghapus komentarnya sendiri.
                $del = mysqli_prepare(
                    $koneksi,
                    'DELETE FROM komentar WHERE id_komentar = ? AND id_artikel = ? AND id_user = ?'
                );

                if ($del) {
                    mysqli_stmt_bind_param($del, 'iii', $id_komentar, $id_artikel, $id_login);
                }
            }

            if (isset($del) && $del) {
                $ok = mysqli_stmt_execute($del);

                if ($ok && mysqli_stmt_affected_rows($del) === 1) {
                    $berhasil_hapus = true;
                } elseif (!$ok) {
                    error_log('WargaNet gagal menghapus komentar: ' . mysqli_stmt_error($del));
                }

                mysqli_stmt_close($del);
            } else {
                error_log('WargaNet gagal menyiapkan query hapus komentar: ' . mysqli_error($koneksi));
            }
        }

        $status_hapus = $berhasil_hapus ? 'hapus' : 'error';

        header('Location: ' . $link_artikel . '&k=' . $status_hapus . '#komentar');
        exit;
    }
}

// ---------- Hitung views ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!isset($_SESSION['viewed']) || !is_array($_SESSION['viewed'])) {
        $_SESSION['viewed'] = [];
    }

    if (empty($_SESSION['viewed'][$id_artikel])) {
        $up = mysqli_prepare(
            $koneksi,
            'UPDATE artikel SET views = views + 1 WHERE id_artikel = ? AND status = ?'
        );

        if ($up) {
            $status_terbit = 'terbit';

            mysqli_stmt_bind_param($up, 'is', $id_artikel, $status_terbit);

            $ok = mysqli_stmt_execute($up);
            $jumlah_diubah = mysqli_stmt_affected_rows($up);

            if ($ok && $jumlah_diubah === 1) {
                $_SESSION['viewed'][$id_artikel] = time();
                $data['views'] = (int) $data['views'] + 1;
            } elseif (!$ok) {
                error_log('WargaNet gagal memperbarui views: ' . mysqli_stmt_error($up));
            }

            mysqli_stmt_close($up);
        } else {
            error_log('WargaNet gagal menyiapkan query views: ' . mysqli_error($koneksi));
        }
    }
}

// ---------- Ambil daftar komentar ----------
$sql_komentar = "SELECT
        komentar.id_komentar,
        komentar.id_user,
        komentar.isi_komentar,
        komentar.tanggal_komentar,
        users.nama_lengkap,
        users.role
    FROM komentar
    LEFT JOIN users ON komentar.id_user = users.id_user
    WHERE komentar.id_artikel = ?
    ORDER BY komentar.tanggal_komentar DESC, komentar.id_komentar DESC
    LIMIT 100";

$stmt_k = mysqli_prepare($koneksi, $sql_komentar);

$query_komentar = false;
$jumlah_komentar = 0;

if ($stmt_k) {
    mysqli_stmt_bind_param($stmt_k, 'i', $id_artikel);

    if (mysqli_stmt_execute($stmt_k)) {
        $query_komentar = mysqli_stmt_get_result($stmt_k);

        if ($query_komentar) {
            $jumlah_komentar = mysqli_num_rows($query_komentar);
        } else {
            error_log('WargaNet gagal mengambil hasil query komentar.');
        }
    } else {
        error_log('WargaNet gagal menjalankan query komentar: ' . mysqli_stmt_error($stmt_k));
    }

    mysqli_stmt_close($stmt_k);
} else {
    error_log('WargaNet gagal menyiapkan query daftar komentar: ' . mysqli_error($koneksi));
}

$flash = $_GET['k'] ?? '';
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($data['judul']); ?> - WargaNet</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/warganet.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/warganet.css'); ?>">

    <script>document.documentElement.classList.add('js')</script>
</head>
<body class="wn-public">

    <!-- NAVBAR PUBLIC -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-glass sticky-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-info" href="index.php">
                <i class="bi bi-activity me-1"></i> WargaNet
            </a>

            <div class="d-flex ms-auto">
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="bi bi-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
    </nav>

    <!-- KONTEN ARTIKEL -->
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- Header Artikel -->
                <div class="mb-4 text-center">
                    <span class="badge bg-primary mb-3 px-3 py-2 rounded-pill">
                        <?= e($data['nama_kategori'] ?? 'Umum'); ?>
                    </span>

                    <h1 class="fw-bold text-white mb-3" style="line-height: 1.3;">
                        <?= e($data['judul']); ?>
                    </h1>

                    <div class="text-secondary d-flex justify-content-center flex-wrap gap-2 gap-md-4">
                        <span>
                            <i class="bi bi-person-circle me-1"></i>
                            <?= e($data['nama_lengkap'] ?? 'Penulis tidak tersedia'); ?>
                        </span>

                        <span>
                            <i class="bi bi-calendar3 me-1"></i>
                            <?= e(date('d M Y', strtotime($data['tanggal_dibuat']))); ?>
                        </span>

                        <span title="Jumlah pembaca">
                            <i class="bi bi-eye me-1"></i>
                            <?= number_format((int) $data['views'], 0, ',', '.'); ?> dilihat
                        </span>

                        <a href="#komentar" class="text-secondary text-decoration-none">
                            <i class="bi bi-chat-dots me-1"></i>
                            <?= (int) $jumlah_komentar; ?> komentar
                        </a>
                    </div>
                </div>

                <!-- Gambar Utama -->
                <?php if ($gambar_artikel !== null) : ?>
                    <img
                        src="<?= e($gambar_artikel); ?>"
                        class="article-img shadow-lg mb-5"
                        alt="<?= e($data['judul']); ?>"
                    >
                <?php else : ?>
                    <div class="article-img shadow-lg mb-5 d-flex align-items-center justify-content-center bg-secondary bg-opacity-25">
                        <div class="text-center text-secondary">
                            <i class="bi bi-image fs-1"></i>
                            <p class="mb-0 mt-2">Gambar artikel tidak tersedia.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Teks Isi Artikel -->
                <article class="content-text mb-5">
                    <?= nl2br(e($data['isi_artikel'])); ?>
                </article>

                <!-- Footer Bagikan -->
                <div class="border-top border-secondary pt-4 mt-5 d-flex justify-content-between align-items-center">
                    <p class="mb-0 text-secondary">Bagikan cerita ini:</p>

                    <div class="gap-2 d-flex">
                        <button type="button" class="btn btn-outline-info rounded-circle" aria-label="Bagikan ke Twitter">
                            <i class="bi bi-twitter-x"></i>
                        </button>

                        <button type="button" class="btn btn-outline-primary rounded-circle" aria-label="Bagikan ke Facebook">
                            <i class="bi bi-facebook"></i>
                        </button>

                        <button type="button" class="btn btn-outline-success rounded-circle" aria-label="Bagikan ke WhatsApp">
                            <i class="bi bi-whatsapp"></i>
                        </button>
                    </div>
                </div>

                <!-- ===================== KOMENTAR ===================== -->
                <section id="komentar" class="wn-comments mt-5 pt-2">

                    <h4 class="fw-bold mb-4">
                        <i class="bi bi-chat-square-text me-2 text-info"></i>
                        Komentar
                        <span class="wn-count-pill"><?= (int) $jumlah_komentar; ?></span>
                    </h4>

                    <?php if ($flash === 'ok') : ?>
                        <div class="alert alert-success bg-transparent border-success text-success py-2">
                            <i class="bi bi-check-circle me-2"></i>
                            Komentar Anda berhasil dikirim.
                        </div>

                    <?php elseif ($flash === 'hapus') : ?>
                        <div class="alert alert-info bg-transparent py-2">
                            <i class="bi bi-trash3 me-2"></i>
                            Komentar berhasil dihapus.
                        </div>

                    <?php elseif ($flash === 'error') : ?>
                        <div class="alert alert-danger bg-transparent border-danger text-danger py-2">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Komentar gagal dihapus atau tidak ditemukan.
                        </div>
                    <?php endif; ?>

                    <!-- Form komentar -->
                    <?php if ($sudah_login) : ?>

                        <form method="POST" action="<?= e($link_artikel); ?>#komentar" class="wn-comment-form mb-5">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                            <div class="d-flex gap-3">
                                <span class="wn-c-avatar">
                                    <?= e(inisial_nama((string) ($_SESSION['nama_lengkap'] ?? '?'))); ?>
                                </span>

                                <div class="flex-grow-1">

                                    <?php if ($error_komentar !== '') : ?>
                                        <div class="alert alert-danger py-2 small">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            <?= e($error_komentar); ?>
                                        </div>
                                    <?php endif; ?>

                                    <textarea
                                        name="isi_komentar"
                                        class="form-control"
                                        rows="3"
                                        maxlength="1000"
                                        required
                                        placeholder="Tulis komentar sebagai <?= e($_SESSION['nama_lengkap'] ?? ''); ?>..."
                                    ><?= e($isi_lama); ?></textarea>

                                    <div class="d-flex justify-content-end mt-2">
                                        <button type="submit" name="kirim_komentar" class="btn btn-primary px-4">
                                            <i class="bi bi-send me-2"></i>
                                            Kirim komentar
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </form>

                    <?php else : ?>

                        <div class="wn-login-cta mb-5">
                            <i class="bi bi-lock"></i>

                            <div class="flex-grow-1">
                                <strong>Ingin ikut berkomentar?</strong>
                                <div class="text-secondary small">
                                    Masuk atau daftar akun untuk menulis komentar.
                                </div>
                            </div>

                            <a href="login.php" class="btn btn-primary rounded-pill px-4">Masuk</a>
                            <a href="register.php" class="btn btn-outline-secondary rounded-pill px-4">Daftar</a>
                        </div>

                    <?php endif; ?>

                    <!-- Daftar komentar -->
                    <?php if ($jumlah_komentar > 0 && $query_komentar) : ?>

                        <div class="d-flex flex-column gap-3">

                            <?php while ($k = mysqli_fetch_assoc($query_komentar)) : ?>

                                <?php
                                    $nama_k = (string) ($k['nama_lengkap'] ?? 'Pengguna dihapus');

                                    $bisa_hapus = $sudah_login &&
                                        (
                                            $is_admin ||
                                            (int) $k['id_user'] === $id_login
                                        );
                                ?>

                                <div class="wn-comment">

                                    <span class="wn-c-avatar">
                                        <?= e(inisial_nama($nama_k)); ?>
                                    </span>

                                    <div class="flex-grow-1 min-w-0">

                                        <div class="wn-c-head">
                                            <strong><?= e($nama_k); ?></strong>

                                            <?php if (($k['role'] ?? '') === 'admin') : ?>
                                                <span class="badge bg-danger">Admin</span>

                                            <?php elseif (($k['role'] ?? '') === 'penulis') : ?>
                                                <span class="badge bg-primary">Penulis</span>
                                            <?php endif; ?>

                                            <small class="text-secondary">
                                                <?= e(date('d M Y, H:i', strtotime($k['tanggal_komentar']))); ?>
                                            </small>
                                        </div>

                                        <div class="wn-c-body">
                                            <?= nl2br(e($k['isi_komentar'])); ?>
                                        </div>

                                    </div>

                                    <?php if ($bisa_hapus) : ?>

                                        <form
                                            method="POST"
                                            action="<?= e($link_artikel); ?>#komentar"
                                            class="wn-c-del-form"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e(csrf_token()); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_komentar"
                                                value="<?= (int) $k['id_komentar']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="hapus_komentar"
                                                class="wn-c-del"
                                                title="Hapus komentar"
                                                onclick="return confirm('Hapus komentar ini?')"
                                            >
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                </div>

                            <?php endwhile; ?>

                        </div>

                    <?php elseif ($jumlah_komentar === 0) : ?>

                        <div class="wn-empty">
                            <i class="bi bi-chat-heart"></i>
                            <div>Belum ada komentar. Jadilah yang pertama!</div>
                        </div>

                    <?php else : ?>

                        <div class="alert alert-warning bg-transparent">
                            Daftar komentar belum dapat dimuat. Silakan coba kembali nanti.
                        </div>

                    <?php endif; ?>

                    <?php
                    if ($query_komentar) {
                        mysqli_free_result($query_komentar);
                    }
                    ?>

                </section>

            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="wn-footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">

            <span class="brand">
                <i class="bi bi-activity me-1"></i>WargaNet
            </span>

            <span>
                &copy; <?= date('Y'); ?> WargaNet &mdash; Suara warga, inspirasi bersama.
            </span>

            <nav class="d-flex gap-3">
                <a href="index.php">Beranda</a>
                <a href="kategori.php">Kategori</a>
                <a href="tentang.php">Tentang</a>
            </nav>

        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="assets/js/warganet.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/warganet.js'); ?>"></script>

</body>
</html>
```