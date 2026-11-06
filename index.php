
<?php
// =====================================================
// 1. PENGATURAN SESSION
// =====================================================

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


// =====================================================
// 2. VALIDASI SESSION PENGGUNA
// =====================================================

$id_user = valid_positive_int($_SESSION['id_user'] ?? null);
$role = $_SESSION['role'] ?? '';

$role_valid = in_array($role, ['admin', 'penulis', 'pembaca'], true);

if ($id_user === null || !$role_valid) {
    // Hapus sesi yang tidak valid
    $_SESSION = [];
    session_regenerate_id(true);

    $sudah_login = false;
    $role = '';
} else {
    $sudah_login = true;
}

$link_dashboard = '';

if ($sudah_login) {
    if ($role === 'admin') {
        $link_dashboard = 'admin/index.php';
    } elseif ($role === 'penulis') {
        $link_dashboard = 'penulis/index.php';
    }
}


// =====================================================
// 3. FUNGSI MENGAMBIL ANGKA STATISTIK
// =====================================================

function ambil_angka(mysqli $koneksi, string $sql): ?int
{
    $hasil = mysqli_query($koneksi, $sql);

    if (!$hasil) {
        error_log('WargaNet statistics query failed: ' . mysqli_error($koneksi));
        return null;
    }

    $baris = mysqli_fetch_row($hasil);

    mysqli_free_result($hasil);

    return $baris ? (int) $baris[0] : 0;
}


// =====================================================
// 4. FUNGSI MEMERIKSA GAMBAR ARTIKEL
// =====================================================

function gambar_artikel(?string $filename): ?string
{
    if ($filename === null || trim($filename) === '') {
        return null;
    }

    $nama_file = basename($filename);

    if ($nama_file === '.' || $nama_file === '') {
        return null;
    }

    $direktori_upload = realpath(__DIR__ . '/uploads');

    if ($direktori_upload === false) {
        return null;
    }

    $path_gambar = realpath(
        $direktori_upload . DIRECTORY_SEPARATOR . $nama_file
    );

    if (
        $path_gambar === false ||
        !is_file($path_gambar) ||
        strpos($path_gambar, $direktori_upload . DIRECTORY_SEPARATOR) !== 0
    ) {
        return null;
    }

    return 'uploads/' . rawurlencode($nama_file);
}


// =====================================================
// 5. AMBIL ARTIKEL TERBARU UNTUK HERO BANNER
// =====================================================

$query_hero = mysqli_query(
    $koneksi,
    "SELECT
        artikel.id_artikel,
        artikel.judul,
        artikel.gambar,
        artikel.tanggal_dibuat,
        artikel.views,
        kategori.nama_kategori,
        users.nama_lengkap
    FROM artikel
    LEFT JOIN kategori
        ON artikel.id_kategori = kategori.id_kategori
    LEFT JOIN users
        ON artikel.id_user = users.id_user
    WHERE artikel.status = 'terbit'
    ORDER BY artikel.tanggal_dibuat DESC, artikel.id_artikel DESC
    LIMIT 1"
);

$hero = null;

if ($query_hero) {
    $hero = mysqli_fetch_assoc($query_hero);
    mysqli_free_result($query_hero);
} else {
    error_log('WargaNet hero query failed: ' . mysqli_error($koneksi));
}


// =====================================================
// 6. AMBIL DATA STATISTIK
// =====================================================

$stat_artikel = ambil_angka(
    $koneksi,
    "SELECT COUNT(*) FROM artikel WHERE status = 'terbit'"
);

$stat_kategori = ambil_angka(
    $koneksi,
    "SELECT COUNT(*) FROM kategori"
);

$stat_penulis = ambil_angka(
    $koneksi,
    "SELECT COUNT(DISTINCT id_user) FROM artikel WHERE status = 'terbit'"
);


// =====================================================
// 7. AMBIL DAFTAR KATEGORI
// =====================================================

$query_chip = mysqli_query(
    $koneksi,
    "SELECT id_kategori, nama_kategori
     FROM kategori
     ORDER BY nama_kategori
     LIMIT 8"
);

if (!$query_chip) {
    error_log('WargaNet category query failed: ' . mysqli_error($koneksi));
}


// =====================================================
// 8. AMBIL ENAM ARTIKEL LAINNYA
// =====================================================

$query_berita = mysqli_query(
    $koneksi,
    "SELECT
        artikel.id_artikel,
        artikel.judul,
        artikel.isi_artikel,
        artikel.gambar,
        artikel.tanggal_dibuat,
        artikel.views,
        kategori.nama_kategori,
        users.nama_lengkap
    FROM artikel
    LEFT JOIN kategori
        ON artikel.id_kategori = kategori.id_kategori
    LEFT JOIN users
        ON artikel.id_user = users.id_user
    WHERE artikel.status = 'terbit'
    ORDER BY artikel.tanggal_dibuat DESC, artikel.id_artikel DESC
    LIMIT 6 OFFSET 1"
);

if (!$query_berita) {
    error_log('WargaNet news query failed: ' . mysqli_error($koneksi));
}

?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>WargaNet - Portal Berita &amp; Komunitas</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/warganet.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/warganet.css'); ?>"
    >

    <script>
        document.documentElement.classList.add('js');
    </script>
</head>

<body class="wn-public">

    <!-- NAVBAR PUBLIC -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-glass sticky-top py-3">
        <div class="container">

            <a class="navbar-brand fw-bold text-info" href="index.php">
                <i class="bi bi-activity me-1"></i> WargaNet
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav"
                aria-label="Menu"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">

                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Beranda</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="kategori.php">Kategori</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="tentang.php">Tentang Kami</a>
                    </li>
                </ul>

                <div class="d-flex gap-2">

                    <?php if ($sudah_login) : ?>

                        <?php if ($link_dashboard !== '') : ?>
                            <a
                                href="<?= e($link_dashboard); ?>"
                                class="btn btn-outline-secondary rounded-pill px-4"
                            >
                                Dashboard
                            </a>
                        <?php endif; ?>

                        <form action="logout.php" method="POST" class="d-inline">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(csrf_token()); ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-outline-danger rounded-pill px-4"
                            >
                                Logout
                            </button>
                        </form>

                    <?php else : ?>

                        <a
                            href="login.php"
                            class="btn btn-primary rounded-pill px-4 fw-bold"
                        >
                            Masuk / Daftar
                        </a>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </nav>


    <!-- HERO PEMBUKA -->
    <header class="wn-intro">
        <div class="container text-center">

            <span class="wn-pill">
                <span class="wn-dot"></span>
                Portal berita &amp; komunitas warga
            </span>

            <h1 class="wn-title">
                Kabar dari warga,<br>
                <span class="wn-grad-text">untuk semua warga.</span>
            </h1>

            <p class="wn-lead">
                Satu pintu untuk berita, pengumuman, dan cerita dari lingkungan sekitar.
                Baca, bagikan, dan jadi bagian dari suara warga.
            </p>

            <div class="d-flex gap-3 justify-content-center flex-wrap">

                <a
                    href="kategori.php"
                    class="btn btn-primary btn-lg rounded-pill px-5"
                >
                    Jelajahi berita
                    <i class="bi bi-arrow-right ms-1"></i>
                </a>

                <?php if (!$sudah_login) : ?>
                    <a
                        href="register.php"
                        class="btn btn-outline-secondary btn-lg rounded-pill px-5"
                    >
                        Jadi kontributor
                    </a>
                <?php endif; ?>

            </div>


            <!-- KATEGORI -->
            <div class="wn-chips">

                <?php if ($query_chip) : ?>

                    <?php while ($chip = mysqli_fetch_assoc($query_chip)) : ?>

                        <a href="kategori.php?id=<?= (int) $chip['id_kategori']; ?>">
                            # <?= e($chip['nama_kategori']); ?>
                        </a>

                    <?php endwhile; ?>

                    <?php mysqli_free_result($query_chip); ?>

                <?php endif; ?>

            </div>


            <!-- STATISTIK -->
            <div class="wn-stats">

                <div class="wn-stat">
                    <b data-count="<?= $stat_artikel ?? 0; ?>">
                        <?= $stat_artikel === null ? '-' : '0'; ?>
                    </b>
                    <span>Berita terbit</span>
                </div>

                <div class="wn-stat">
                    <b data-count="<?= $stat_kategori ?? 0; ?>">
                        <?= $stat_kategori === null ? '-' : '0'; ?>
                    </b>
                    <span>Kategori</span>
                </div>

                <div class="wn-stat">
                    <b data-count="<?= $stat_penulis ?? 0; ?>">
                        <?= $stat_penulis === null ? '-' : '0'; ?>
                    </b>
                    <span>Penulis aktif</span>
                </div>

            </div>

        </div>
    </header>


    <div class="container mb-5 mt-4">

        <!-- SECTION 1: HERO BANNER -->
        <?php if ($hero) : ?>

            <a
                href="detail.php?id=<?= (int) $hero['id_artikel']; ?>"
                class="text-decoration-none"
            >
                <div class="hero-banner shadow-lg mb-5">

                    <?php $gambar_hero = gambar_artikel($hero['gambar']); ?>

                    <?php if ($gambar_hero !== null) : ?>

                        <img
                            src="<?= e($gambar_hero); ?>"
                            alt="<?= e($hero['judul']); ?>"
                            class="hero-img"
                        >

                    <?php else : ?>

                        <div class="hero-img bg-secondary d-flex align-items-center justify-content-center">
                            <i class="bi bi-image fs-1 text-white"></i>
                        </div>

                    <?php endif; ?>

                    <div class="hero-overlay"></div>

                    <div class="hero-content text-white">

                        <span class="badge bg-primary mb-3 px-3 py-2 rounded-pill">
                            <?= e($hero['nama_kategori'] ?? 'Umum'); ?>
                        </span>

                        <h1 class="fw-bold mb-3 display-5">
                            <?= e($hero['judul']); ?>
                        </h1>

                        <div class="d-flex align-items-center flex-wrap gap-2 text-secondary">

                            <i class="bi bi-person-circle me-1"></i>

                            <span class="me-3 text-white">
                                <?= e($hero['nama_lengkap'] ?? 'Penulis'); ?>
                            </span>

                            <i class="bi bi-calendar3 me-1"></i>

                            <span class="me-3">
                                <?= e(date('d M Y', strtotime($hero['tanggal_dibuat']))); ?>
                            </span>

                            <i class="bi bi-eye me-1"></i>

                            <span>
                                <?= number_format((int) $hero['views'], 0, ',', '.'); ?> dilihat
                            </span>

                        </div>
                    </div>

                </div>
            </a>

        <?php else : ?>

            <div class="alert alert-secondary bg-transparent border-secondary text-center py-5">
                <h4 class="text-secondary">
                    Belum ada berita yang diterbitkan.
                </h4>
            </div>

        <?php endif; ?>


        <!-- SECTION 2: GRID BERITA TERKINI -->

        <?php if ($query_berita && mysqli_num_rows($query_berita) > 0) : ?>

            <h4 class="fw-bold mb-4 border-bottom border-secondary pb-2">
                Berita Terkini
            </h4>

            <div class="row g-4">

                <?php while ($row = mysqli_fetch_assoc($query_berita)) : ?>

                    <div class="col-md-4">

                        <div class="card card-dark h-100 rounded-4 overflow-hidden">

                            <?php $gambar_berita = gambar_artikel($row['gambar']); ?>

                            <?php if ($gambar_berita !== null) : ?>

                                <img
                                    src="<?= e($gambar_berita); ?>"
                                    class="card-img-top"
                                    alt="<?= e($row['judul']); ?>"
                                >

                            <?php else : ?>

                                <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="bi bi-image fs-1 text-white"></i>
                                </div>

                            <?php endif; ?>


                            <div class="card-body p-4">

                                <span class="text-info small fw-bold text-uppercase">
                                    <?= e($row['nama_kategori'] ?? 'Umum'); ?>
                                </span>

                                <h5 class="card-title fw-bold mt-2 mb-3">

                                    <a
                                        href="detail.php?id=<?= (int) $row['id_artikel']; ?>"
                                        class="text-white text-decoration-none stretched-link"
                                    >
                                        <?= e($row['judul']); ?>
                                    </a>

                                </h5>

                                <p class="card-text text-secondary small text-truncate-3">
                                    <?= e(strip_tags((string) $row['isi_artikel'])); ?>
                                </p>

                            </div>


                            <div class="card-footer bg-transparent border-secondary pt-0 pb-3 px-4">

                                <small class="text-secondary d-flex justify-content-between flex-wrap gap-1">

                                    <span>
                                        <i class="bi bi-pen"></i>
                                        <?= e($row['nama_lengkap'] ?? 'Penulis'); ?>
                                    </span>

                                    <span>
                                        <i class="bi bi-eye"></i>
                                        <?= number_format((int) $row['views'], 0, ',', '.'); ?>

                                        &bull;

                                        <?= e(date('d M Y', strtotime($row['tanggal_dibuat']))); ?>
                                    </span>

                                </small>

                            </div>

                        </div>

                    </div>

                <?php endwhile; ?>

            </div>

        <?php elseif ($query_berita) : ?>

            <?php if ($hero) : ?>
                <div class="text-center text-secondary py-4">
                    Belum ada berita lainnya.
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <?php
        if ($query_berita) {
            mysqli_free_result($query_berita);
        }
        ?>

    </div>


    <!-- FOOTER -->
    <footer class="wn-footer">

        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">

            <span class="brand">
                <i class="bi bi-activity me-1"></i>WargaNet
            </span>

            <span>
                &copy; <?= date('Y'); ?> WargaNet &mdash;
                Suara warga, inspirasi bersama.
            </span>

            <nav class="d-flex gap-3">
                <a href="index.php">Beranda</a>
                <a href="kategori.php">Kategori</a>
                <a href="tentang.php">Tentang</a>
            </nav>

        </div>

    </footer>


    <!-- JAVASCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="assets/js/warganet.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/warganet.js'); ?>"></script>

</body>
</html>
