<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $https,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
require 'includes/security.php';
require 'koneksi.php';

// Cek apakah ada ID artikel yang dikirim di URL
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id_artikel = valid_positive_int($_GET['id']);
if ($id_artikel === null) {
    header('Location: index.php');
    exit;
}

// Ambil artikel dengan prepared statement agar ID URL tidak disisipkan ke SQL.
$stmt = mysqli_prepare($koneksi, "SELECT artikel.*, kategori.nama_kategori, users.nama_lengkap
    FROM artikel
    LEFT JOIN kategori ON artikel.id_kategori = kategori.id_kategori
    LEFT JOIN users ON artikel.id_user = users.id_user
    WHERE artikel.id_artikel = ? AND artikel.status = 'terbit' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id_artikel);
mysqli_stmt_execute($stmt);
$query_detail = mysqli_stmt_get_result($stmt);

// Jika artikel tidak ditemukan atau berstatus selain 'terbit'
if (!$query_detail || mysqli_num_rows($query_detail) == 0) {
    echo "<script>alert('Artikel tidak ditemukan atau belum diterbitkan.'); window.location='index.php';</script>";
    exit;
}

$data = mysqli_fetch_assoc($query_detail);
mysqli_stmt_close($stmt);

// ---------- Status pengunjung ----------
$sudah_login = isset($_SESSION['id_user']);
$id_login    = $sudah_login ? (int) $_SESSION['id_user'] : 0;
$is_admin    = $sudah_login && (($_SESSION['role'] ?? '') === 'admin');
$link_artikel = 'detail.php?id=' . $id_artikel;

// Simpan tujuan kembali, supaya setelah login pembaca langsung kembali ke artikel ini
if (!$sudah_login && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['after_login'] = $link_artikel . '#komentar';
}

// ---------- Proses komentar (kirim / hapus) ----------
$error_komentar = '';
$isi_lama = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!$sudah_login) {
        header('Location: login.php');
        exit;
    }

    if (isset($_POST['kirim_komentar'])) {
        $isi = trim((string) ($_POST['isi_komentar'] ?? ''));
        $isi = preg_replace("/\r\n?/", "\n", $isi);
        $panjang = function_exists('mb_strlen') ? mb_strlen($isi, 'UTF-8') : strlen($isi);
        $terakhir = (int) ($_SESSION['last_comment'] ?? 0);
        $isi_lama = $isi;

        if ($panjang < 2) {
            $error_komentar = 'Komentar terlalu pendek.';
        } elseif ($panjang > 1000) {
            $error_komentar = 'Komentar maksimal 1000 karakter.';
        } elseif (time() - $terakhir < 10) {
            $error_komentar = 'Tunggu beberapa detik sebelum mengirim komentar lagi.';
        } else {
            $ins = mysqli_prepare($koneksi, 'INSERT INTO komentar (id_artikel, id_user, isi_komentar) VALUES (?, ?, ?)');
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'iis', $id_artikel, $id_login, $isi);
                $ok = mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
                if ($ok) {
                    $_SESSION['last_comment'] = time();
                    header('Location: ' . $link_artikel . '&k=ok#komentar');
                    exit;
                }
            }
            error_log('WargaNet gagal menyimpan komentar.');
            $error_komentar = 'Komentar gagal disimpan. Silakan coba lagi.';
        }
    } elseif (isset($_POST['hapus_komentar'])) {
        $id_komentar = valid_positive_int($_POST['id_komentar'] ?? null);
        if ($id_komentar !== null) {
            if ($is_admin) {
                // Admin boleh menghapus komentar siapa pun pada artikel ini
                $del = mysqli_prepare($koneksi, 'DELETE FROM komentar WHERE id_komentar = ? AND id_artikel = ?');
                if ($del) { mysqli_stmt_bind_param($del, 'ii', $id_komentar, $id_artikel); }
            } else {
                // Pengguna biasa hanya boleh menghapus komentarnya sendiri
                $del = mysqli_prepare($koneksi, 'DELETE FROM komentar WHERE id_komentar = ? AND id_artikel = ? AND id_user = ?');
                if ($del) { mysqli_stmt_bind_param($del, 'iii', $id_komentar, $id_artikel, $id_login); }
            }
            if (!empty($del)) {
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);
            }
        }
        header('Location: ' . $link_artikel . '&k=hapus#komentar');
        exit;
    }
}

// ---------- Hitung view (sekali per sesi untuk tiap artikel, refresh tidak menambah) ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!isset($_SESSION['viewed']) || !is_array($_SESSION['viewed'])) {
        $_SESSION['viewed'] = [];
    }
    if (empty($_SESSION['viewed'][$id_artikel])) {
        $up = mysqli_prepare($koneksi, 'UPDATE artikel SET views = views + 1 WHERE id_artikel = ?');
        if ($up) {
            mysqli_stmt_bind_param($up, 'i', $id_artikel);
            mysqli_stmt_execute($up);
            mysqli_stmt_close($up);
        }
        $_SESSION['viewed'][$id_artikel] = time();
        $data['views'] = (int) $data['views'] + 1;
    }
}

// ---------- Ambil daftar komentar ----------
$stmt_k = mysqli_prepare($koneksi, "SELECT komentar.id_komentar, komentar.id_user, komentar.isi_komentar, komentar.tanggal_komentar,
        users.nama_lengkap, users.role
    FROM komentar
    LEFT JOIN users ON komentar.id_user = users.id_user
    WHERE komentar.id_artikel = ?
    ORDER BY komentar.tanggal_komentar DESC, komentar.id_komentar DESC
    LIMIT 100");
mysqli_stmt_bind_param($stmt_k, 'i', $id_artikel);
mysqli_stmt_execute($stmt_k);
$query_komentar = mysqli_stmt_get_result($stmt_k);
$jumlah_komentar = $query_komentar ? mysqli_num_rows($query_komentar) : 0;

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
                    <span class="badge bg-primary mb-3 px-3 py-2 rounded-pill"><?= e($data['nama_kategori'] ?? 'Umum'); ?></span>
                    <h1 class="fw-bold text-white mb-3" style="line-height: 1.3;"><?= e($data['judul']); ?></h1>
                    <div class="text-secondary d-flex justify-content-center flex-wrap gap-2 gap-md-4">
                        <span><i class="bi bi-person-circle me-1"></i> <?= e($data['nama_lengkap']); ?></span>
                        <span><i class="bi bi-calendar3 me-1"></i> <?= date('d M Y', strtotime($data['tanggal_dibuat'])); ?></span>
                        <span title="Jumlah pembaca"><i class="bi bi-eye me-1"></i> <?= number_format((int) $data['views'], 0, ',', '.'); ?> dilihat</span>
                        <a href="#komentar" class="text-secondary text-decoration-none"><i class="bi bi-chat-dots me-1"></i> <?= (int) $jumlah_komentar; ?> komentar</a>
                    </div>
                </div>

                <!-- Gambar Utama -->
                <img src="uploads/<?= rawurlencode(basename((string)$data['gambar'])); ?>" class="article-img shadow-lg mb-5" alt="<?= e($data['judul']); ?>">

                <!-- Teks Isi Artikel -->
                <article class="content-text mb-5">
                    <!-- nl2br digunakan agar baris baru (enter) dari textarea tetap terbaca sebagai baris baru di HTML -->
                    <?= nl2br(e($data['isi_artikel'])); ?>
                </article>

                <!-- Footer Bagikan -->
                <div class="border-top border-secondary pt-4 mt-5 d-flex justify-content-between align-items-center">
                    <p class="mb-0 text-secondary">Bagikan cerita ini:</p>
                    <div class="gap-2 d-flex">
                        <button type="button" class="btn btn-outline-info rounded-circle"><i class="bi bi-twitter-x"></i></button>
                        <button type="button" class="btn btn-outline-primary rounded-circle"><i class="bi bi-facebook"></i></button>
                        <button type="button" class="btn btn-outline-success rounded-circle"><i class="bi bi-whatsapp"></i></button>
                    </div>
                </div>

                <!-- ===================== KOMENTAR ===================== -->
                <section id="komentar" class="wn-comments mt-5 pt-2">
                    <h4 class="fw-bold mb-4">
                        <i class="bi bi-chat-square-text me-2 text-info"></i>Komentar
                        <span class="wn-count-pill"><?= (int) $jumlah_komentar; ?></span>
                    </h4>

                    <?php if ($flash === 'ok') : ?>
                        <div class="alert alert-success bg-transparent border-success text-success py-2">
                            <i class="bi bi-check-circle me-2"></i>Komentar Anda berhasil dikirim.
                        </div>
                    <?php elseif ($flash === 'hapus') : ?>
                        <div class="alert alert-info bg-transparent py-2">
                            <i class="bi bi-trash3 me-2"></i>Komentar dihapus.
                        </div>
                    <?php endif; ?>

                    <!-- Form komentar -->
                    <?php if ($sudah_login) : ?>
                        <form method="POST" action="<?= e($link_artikel); ?>#komentar" class="wn-comment-form mb-5">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <div class="d-flex gap-3">
                                <span class="wn-c-avatar"><?= e(strtoupper(substr((string) ($_SESSION['nama_lengkap'] ?? '?'), 0, 1))); ?></span>
                                <div class="flex-grow-1">
                                    <?php if ($error_komentar !== '') : ?>
                                        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error_komentar); ?></div>
                                    <?php endif; ?>
                                    <textarea name="isi_komentar" class="form-control" rows="3" maxlength="1000" required
                                              placeholder="Tulis komentar sebagai <?= e($_SESSION['nama_lengkap'] ?? ''); ?>..."><?= e($isi_lama); ?></textarea>
                                    <div class="d-flex justify-content-end mt-2">
                                        <button type="submit" name="kirim_komentar" class="btn btn-primary px-4">
                                            <i class="bi bi-send me-2"></i>Kirim komentar
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
                                <div class="text-secondary small">Masuk atau daftar akun untuk menulis komentar.</div>
                            </div>
                            <a href="login.php" class="btn btn-primary rounded-pill px-4">Masuk</a>
                            <a href="register.php" class="btn btn-outline-secondary rounded-pill px-4">Daftar</a>
                        </div>
                    <?php endif; ?>

                    <!-- Daftar komentar -->
                    <?php if ($jumlah_komentar > 0) : ?>
                        <div class="d-flex flex-column gap-3">
                            <?php while ($k = mysqli_fetch_assoc($query_komentar)) : ?>
                                <?php
                                    $nama_k = (string) ($k['nama_lengkap'] ?? 'Pengguna dihapus');
                                    $bisa_hapus = $sudah_login && ($is_admin || (int) $k['id_user'] === $id_login);
                                ?>
                                <div class="wn-comment">
                                    <span class="wn-c-avatar"><?= e(strtoupper(substr($nama_k, 0, 1))); ?></span>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="wn-c-head">
                                            <strong><?= e($nama_k); ?></strong>
                                            <?php if (($k['role'] ?? '') === 'admin') : ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php elseif (($k['role'] ?? '') === 'penulis') : ?>
                                                <span class="badge bg-primary">Penulis</span>
                                            <?php endif; ?>
                                            <small class="text-secondary"><?= date('d M Y, H:i', strtotime($k['tanggal_komentar'])); ?></small>
                                        </div>
                                        <div class="wn-c-body"><?= nl2br(e($k['isi_komentar'])); ?></div>
                                    </div>
                                    <?php if ($bisa_hapus) : ?>
                                        <form method="POST" action="<?= e($link_artikel); ?>#komentar" class="wn-c-del-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="id_komentar" value="<?= (int) $k['id_komentar']; ?>">
                                            <button type="submit" name="hapus_komentar" class="wn-c-del" title="Hapus komentar"
                                                    onclick="return confirm('Hapus komentar ini?')">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <div class="wn-empty">
                            <i class="bi bi-chat-heart"></i>
                            <div>Belum ada komentar. Jadilah yang pertama!</div>
                        </div>
                    <?php endif; ?>
                </section>

            </div>
        </div>
    </div>

    <footer class="wn-footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <span class="brand"><i class="bi bi-activity me-1"></i>WargaNet</span>
            <span>&copy; <?= date('Y'); ?> WargaNet &mdash; Suara warga, inspirasi bersama.</span>
            <nav class="d-flex gap-3"><a href="index.php">Beranda</a><a href="kategori.php">Kategori</a><a href="tentang.php">Tentang</a></nav>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/warganet.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/warganet.js'); ?>"></script>
</body>
</html>