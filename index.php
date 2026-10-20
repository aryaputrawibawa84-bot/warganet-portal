<?php
session_start();
require 'koneksi.php';

// Ambil 1 artikel terbaru yang berstatus 'terbit' untuk Hero Banner (Sorotan Utama)
$query_hero = mysqli_query($koneksi, "
    SELECT artikel.*, kategori.nama_kategori, users.nama_lengkap 
    FROM artikel 
    LEFT JOIN kategori ON artikel.id_kategori = kategori.id_kategori 
    LEFT JOIN users ON artikel.id_user = users.id_user
    WHERE artikel.status = 'terbit' 
    ORDER BY artikel.tanggal_dibuat DESC LIMIT 1
");
$hero = mysqli_fetch_assoc($query_hero);

// Statistik & daftar kategori untuk hero pembuka
$stat_artikel = (int) mysqli_fetch_row(mysqli_query($koneksi, "SELECT COUNT(*) FROM artikel WHERE status = 'terbit'"))[0];
$stat_kategori = (int) mysqli_fetch_row(mysqli_query($koneksi, "SELECT COUNT(*) FROM kategori"))[0];
$stat_penulis = (int) mysqli_fetch_row(mysqli_query($koneksi, "SELECT COUNT(DISTINCT id_user) FROM artikel WHERE status = 'terbit'"))[0];
$query_chip = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori LIMIT 8");

// Ambil 6 artikel lainnya untuk Grid Berita (Lewati 1 artikel yang sudah jadi Hero)
$query_berita = mysqli_query($koneksi, "
    SELECT artikel.*, kategori.nama_kategori, users.nama_lengkap 
    FROM artikel 
    LEFT JOIN kategori ON artikel.id_kategori = kategori.id_kategori 
    LEFT JOIN users ON artikel.id_user = users.id_user
    WHERE artikel.status = 'terbit' 
    ORDER BY artikel.tanggal_dibuat DESC LIMIT 6 OFFSET 1
");
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
    
<head>
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WargaNet - Portal Berita & Komunitas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/warganet.css">
    <script>document.documentElement.classList.add('js')</script>
</head>
<body class="wn-public">

    <!-- NAVBAR PUBLIC -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-glass sticky-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-info" href="index.php">
                <i class="bi bi-activity me-1"></i> WargaNet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori.php">Kategori</a></li>
                    <li class="nav-item"><a class="nav-link" href="tentang.php">Tentang Kami</a></li>
                </ul>
                <div class="d-flex">
                    <?php if(isset($_SESSION['id_user'])) : ?>
                        <!-- Jika sudah login, tampilkan tombol ke Dashboard -->
                        <a href="logout.php" class="btn btn-outline-danger rounded-pill px-4">Logout</a>
                    <?php else : ?>
                        <!-- Jika belum login, tampilkan tombol Masuk -->
                        <a href="login.php" class="btn btn-primary rounded-pill px-4 fw-bold">Masuk / Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- HERO PEMBUKA -->
    <header class="wn-intro">
        <div class="container text-center">
            <span class="wn-pill"><span class="wn-dot"></span> Portal berita &amp; komunitas warga</span>
            <h1 class="wn-title">Kabar dari warga,<br><span class="wn-grad-text">untuk semua warga.</span></h1>
            <p class="wn-lead">Satu pintu untuk berita, pengumuman, dan cerita dari lingkungan sekitar. Baca, bagikan, dan jadi bagian dari suara warga.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="kategori.php" class="btn btn-primary btn-lg rounded-pill px-5">Jelajahi berita <i class="bi bi-arrow-right ms-1"></i></a>
                <?php if(!isset($_SESSION['id_user'])) : ?>
                <a href="register.php" class="btn btn-outline-secondary btn-lg rounded-pill px-5">Jadi kontributor</a>
                <?php endif; ?>
            </div>
            <div class="wn-chips">
                <?php while($chip = mysqli_fetch_assoc($query_chip)) : ?>
                    <a href="kategori.php?id=<?= $chip['id_kategori']; ?>"># <?= htmlspecialchars($chip['nama_kategori']); ?></a>
                <?php endwhile; ?>
            </div>
            <div class="wn-stats">
                <div class="wn-stat"><b data-count="<?= $stat_artikel; ?>">0</b><span>Berita terbit</span></div>
                <div class="wn-stat"><b data-count="<?= $stat_kategori; ?>">0</b><span>Kategori</span></div>
                <div class="wn-stat"><b data-count="<?= $stat_penulis; ?>">0</b><span>Penulis aktif</span></div>
            </div>
        </div>
    </header>

    <div class="container mb-5 mt-4">
        
        <!-- SECTION 1: HERO BANNER (Artikel Terbaru) -->
        <?php if ($hero) : ?>
        <a href="detail.php?id=<?= $hero['id_artikel']; ?>" class="text-decoration-none">
            <div class="hero-banner shadow-lg mb-5">
                <img src="uploads/<?= $hero['gambar']; ?>" alt="<?= $hero['judul']; ?>" class="hero-img">
                <div class="hero-overlay"></div>
                <div class="hero-content text-white">
                    <span class="badge bg-primary mb-3 px-3 py-2 rounded-pill"><?= $hero['nama_kategori']; ?></span>
                    <h1 class="fw-bold mb-3 display-5"><?= $hero['judul']; ?></h1>
                    <div class="d-flex align-items-center text-secondary">
                        <i class="bi bi-person-circle me-2"></i> <span class="me-4 text-white"><?= $hero['nama_lengkap']; ?></span>
                        <i class="bi bi-calendar3 me-2"></i> <span><?= date('d M Y', strtotime($hero['tanggal_dibuat'])); ?></span>
                    </div>
                </div>
            </div>
        </a>
        <?php else : ?>
            <div class="alert alert-secondary bg-transparent border-secondary text-center py-5">
                <h4 class="text-secondary">Belum ada berita yang diterbitkan.</h4>
            </div>
        <?php endif; ?>


        <!-- SECTION 2: GRID BERITA TERKINI -->
        <?php if (mysqli_num_rows($query_berita) > 0) : ?>
        <h4 class="fw-bold mb-4 border-bottom border-secondary pb-2">Berita Terkini</h4>
        <div class="row g-4">
            <?php while($row = mysqli_fetch_assoc($query_berita)) : ?>
            <div class="col-md-4">
                <div class="card card-dark h-100 rounded-4 overflow-hidden">
                    <img src="uploads/<?= $row['gambar']; ?>" class="card-img-top" alt="...">
                    <div class="card-body p-4">
                        <span class="text-info small fw-bold text-uppercase"><?= $row['nama_kategori']; ?></span>
                        <h5 class="card-title fw-bold mt-2 mb-3">
                            <a href="detail.php?id=<?= $row['id_artikel']; ?>" class="text-white text-decoration-none stretched-link">
                                <?= $row['judul']; ?>
                            </a>
                        </h5>
                        <p class="card-text text-secondary small text-truncate-3">
                            <!-- Strip tags digunakan untuk menghilangkan kode HTML bawaan CKEditor/Textarea saat di-preview -->
                            <?= strip_tags($row['isi_artikel']); ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-secondary pt-0 pb-3 px-4">
                        <small class="text-secondary d-flex justify-content-between">
                            <span><i class="bi bi-pen"></i> <?= $row['nama_lengkap']; ?></span>
                            <span><?= date('d M Y', strtotime($row['tanggal_dibuat'])); ?></span>
                        </small>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
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
    <script src="assets/js/warganet.js"></script>
</body>
</html>