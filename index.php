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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WargaNet - Portal Berita & Komunitas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #0F172A; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        /* Glassmorphism Navbar */
        .navbar-glass {
            background: rgba(15, 23, 42, 0.8) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Hero Banner Styling */
        .hero-banner {
            position: relative;
            height: 500px;
            border-radius: 16px;
            overflow: hidden;
            background-color: #1E293B;
            display: flex;
            align-items: flex-end;
        }
        .hero-img {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover;
        }
        .hero-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to top, rgba(15, 23, 42, 1) 0%, rgba(15, 23, 42, 0.2) 100%);
        }
        .hero-content {
            position: relative;
            z-index: 2;
            padding: 40px;
            width: 100%;
        }

        /* Card Styling */
        .card-dark {
            background-color: #1E293B;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-dark:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
        }
        .card-img-top { height: 200px; object-fit: cover; }
        .text-truncate-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body>

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

    <div class="container my-5">
        
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>