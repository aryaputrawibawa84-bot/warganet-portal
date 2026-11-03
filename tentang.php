<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $https,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
require 'includes/security.php';
require 'koneksi.php';

// Ambil satu angka dari query statistik (query ini tidak memakai input pengguna)
function ambil_angka($koneksi, $sql) {
    $hasil = mysqli_query($koneksi, $sql);
    $baris = $hasil ? mysqli_fetch_row($hasil) : null;
    return $baris ? (int) $baris[0] : 0;
}

$stat_artikel  = ambil_angka($koneksi, "SELECT COUNT(*) FROM artikel WHERE status = 'terbit'");
$stat_penulis  = ambil_angka($koneksi, "SELECT COUNT(DISTINCT id_user) FROM artikel WHERE status = 'terbit'");
$stat_komentar = ambil_angka($koneksi, "SELECT COUNT(*) FROM komentar");
$stat_views    = ambil_angka($koneksi, "SELECT COALESCE(SUM(views), 0) FROM artikel WHERE status = 'terbit'");

$sudah_login = isset($_SESSION['id_user']);
$role = $_SESSION['role'] ?? '';
$link_dashboard = $role === 'admin' ? 'admin/index.php' : ($role === 'penulis' ? 'penulis/index.php' : '');
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - WargaNet</title>
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
            <a class="navbar-brand fw-bold text-info" href="index.php"><i class="bi bi-activity me-1"></i> WargaNet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori.php">Kategori</a></li>
                    <li class="nav-item"><a class="nav-link active text-white" href="tentang.php">Tentang Kami</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <?php if ($sudah_login) : ?>
                        <?php if ($link_dashboard !== '') : ?>
                            <a href="<?= e($link_dashboard); ?>" class="btn btn-outline-secondary rounded-pill px-4">Dashboard</a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn btn-outline-danger rounded-pill px-4">Logout</a>
                    <?php else : ?>
                        <a href="login.php" class="btn btn-primary rounded-pill px-4 fw-bold">Masuk / Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <header class="wn-intro">
        <div class="container text-center">
            <span class="wn-pill"><span class="wn-dot"></span> Tentang WargaNet</span>
            <h1 class="wn-title">Satu tempat untuk<br><span class="wn-grad-text">suara warga.</span></h1>
            <p class="wn-lead">WargaNet adalah portal berita dan blog komunitas. Informasi dari lingkungan sekitar dikumpulkan, dirapikan per kategori, dan bisa dibaca kapan saja, tanpa tercecer di grup chat.</p>

            <div class="wn-stats wn-stats--four">
                <div class="wn-stat"><b data-count="<?= $stat_artikel; ?>">0</b><span>Berita terbit</span></div>
                <div class="wn-stat"><b data-count="<?= $stat_penulis; ?>">0</b><span>Penulis aktif</span></div>
                <div class="wn-stat"><b data-count="<?= $stat_komentar; ?>">0</b><span>Komentar</span></div>
                <div class="wn-stat"><b data-count="<?= $stat_views; ?>">0</b><span>Total dibaca</span></div>
            </div>
        </div>
    </header>

    <div class="container my-5">

        <!-- LATAR BELAKANG -->
        <section class="about-card p-4 p-md-5 mb-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-5">
                    <span class="wn-eyebrow">Latar belakang</span>
                    <h2 class="fw-bold mt-2 mb-0">Kenapa WargaNet dibuat?</h2>
                </div>
                <div class="col-lg-7 text-secondary" style="line-height: 1.8;">
                    <p class="mb-3">Pengumuman RT/RW, kabar sekolah, dan cerita organisasi biasanya dibagikan lewat grup chat. Pesannya cepat tenggelam, sulit dicari lagi, dan tidak semua orang ikut grupnya.</p>
                    <p class="mb-0">WargaNet menjadi wadah digital satu pintu: berita, pengumuman, dan opini diarsipkan rapi per kategori, bisa dibaca siapa saja, dan setiap warga bisa ikut berdiskusi lewat kolom komentar.</p>
                </div>
            </div>
        </section>

        <!-- CARA KERJA -->
        <div class="text-center mb-4">
            <span class="wn-eyebrow">Alur</span>
            <h2 class="fw-bold mt-2">Bagaimana cara kerjanya?</h2>
        </div>
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card card-dark wn-step h-100 p-4">
                    <span class="wn-step-no">1</span>
                    <i class="bi bi-pencil-square wn-step-icon"></i>
                    <h5 class="fw-bold">Warga menulis</h5>
                    <p class="text-secondary mb-0">Penulis membuat artikel lengkap dengan gambar sampul dan memilih kategorinya.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-dark wn-step h-100 p-4">
                    <span class="wn-step-no">2</span>
                    <i class="bi bi-shield-check wn-step-icon"></i>
                    <h5 class="fw-bold">Admin meninjau</h5>
                    <p class="text-secondary mb-0">Setiap artikel berstatus <em>pending</em> sampai admin menyetujuinya, supaya isinya tetap terjaga.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-dark wn-step h-100 p-4">
                    <span class="wn-step-no">3</span>
                    <i class="bi bi-chat-heart wn-step-icon"></i>
                    <h5 class="fw-bold">Terbit &amp; didiskusikan</h5>
                    <p class="text-secondary mb-0">Artikel yang terbit tampil di beranda dan kategori. Pembaca bisa berkomentar setelah masuk.</p>
                </div>
            </div>
        </div>

        <!-- PERAN PENGGUNA -->
        <div class="text-center mb-4">
            <span class="wn-eyebrow">Peran</span>
            <h2 class="fw-bold mt-2">Siapa bisa melakukan apa?</h2>
        </div>
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card card-dark h-100 p-4">
                    <div class="wn-role-icon"><i class="bi bi-book"></i></div>
                    <h5 class="fw-bold">Pembaca</h5>
                    <ul class="wn-check text-secondary">
                        <li>Membaca semua berita</li>
                        <li>Menjelajah per kategori</li>
                        <li>Berkomentar &amp; menghapus komentar sendiri</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-dark h-100 p-4">
                    <div class="wn-role-icon"><i class="bi bi-vector-pen"></i></div>
                    <h5 class="fw-bold">Penulis</h5>
                    <ul class="wn-check text-secondary">
                        <li>Menulis artikel dengan gambar sampul</li>
                        <li>Mengelola artikel milik sendiri</li>
                        <li>Semua yang bisa dilakukan pembaca</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-dark h-100 p-4">
                    <div class="wn-role-icon"><i class="bi bi-gear"></i></div>
                    <h5 class="fw-bold">Admin</h5>
                    <ul class="wn-check text-secondary">
                        <li>Meninjau &amp; menerbitkan artikel</li>
                        <li>Mengelola kategori dan pengguna</li>
                        <li>Menghapus komentar yang tidak pantas</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- TEKNOLOGI -->
        <section class="about-card p-4 p-md-5 mb-5 text-center">
            <span class="wn-eyebrow">Teknologi</span>
            <h2 class="fw-bold mt-2 mb-4">Dibangun dengan</h2>
            <div class="wn-chips">
                <a href="#" tabindex="-1" style="pointer-events:none"><i class="bi bi-filetype-php me-1"></i> PHP Native</a>
                <a href="#" tabindex="-1" style="pointer-events:none"><i class="bi bi-database me-1"></i> MySQL</a>
                <a href="#" tabindex="-1" style="pointer-events:none"><i class="bi bi-bootstrap me-1"></i> Bootstrap 5</a>
                <a href="#" tabindex="-1" style="pointer-events:none"><i class="bi bi-filetype-js me-1"></i> JavaScript</a>
                <a href="#" tabindex="-1" style="pointer-events:none"><i class="bi bi-shield-lock me-1"></i> Prepared statement &amp; CSRF</a>
            </div>
        </section>

        <!-- FAQ -->
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8">
                <div class="text-center mb-4">
                    <span class="wn-eyebrow">FAQ</span>
                    <h2 class="fw-bold mt-2">Pertanyaan yang sering diajukan</h2>
                </div>
                <div class="accordion wn-faq" id="faq">
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#f1">Apakah saya perlu akun untuk membaca berita?</button>
                        </h3>
                        <div id="f1" class="accordion-collapse collapse show" data-bs-parent="#faq">
                            <div class="accordion-body">Tidak. Semua berita yang sudah terbit bisa dibaca tanpa akun. Akun hanya dibutuhkan untuk menulis komentar.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#f2">Bagaimana cara menjadi penulis?</button>
                        </h3>
                        <div id="f2" class="accordion-collapse collapse" data-bs-parent="#faq">
                            <div class="accordion-body">Daftar akun terlebih dahulu. Akun baru berperan sebagai pembaca; admin dapat mengubahnya menjadi penulis lewat menu Kelola Pengguna.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#f3">Kenapa artikel saya belum tampil?</button>
                        </h3>
                        <div id="f3" class="accordion-collapse collapse" data-bs-parent="#faq">
                            <div class="accordion-body">Artikel baru berstatus <em>pending</em> dan baru tampil di beranda setelah admin menyetujuinya.</div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#f4">Apakah ada batasan untuk gambar sampul?</button>
                        </h3>
                        <div id="f4" class="accordion-collapse collapse" data-bs-parent="#faq">
                            <div class="accordion-body">Gambar harus berformat JPG atau PNG dengan ukuran maksimal 2MB.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <?php if (!$sudah_login) : ?>
        <section class="wn-cta text-center">
            <h2 class="fw-bold mb-2">Jadi bagian dari suara warga.</h2>
            <p class="text-secondary mb-4">Buat akun gratis untuk ikut berkomentar dan berdiskusi.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="register.php" class="btn btn-primary btn-lg rounded-pill px-5">Daftar sekarang</a>
                <a href="kategori.php" class="btn btn-outline-secondary btn-lg rounded-pill px-5">Jelajahi berita</a>
            </div>
        </section>
        <?php endif; ?>

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