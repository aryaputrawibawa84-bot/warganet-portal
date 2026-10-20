<?php
session_start();
require 'koneksi.php';

// Cek apakah pengunjung sedang memilih kategori tertentu dari URL
$id_pilih = isset($_GET['id']) ? mysqli_real_escape_string($koneksi, $_GET['id']) : null;

// Ambil semua daftar kategori untuk tombol filter
$query_kategori = mysqli_query($koneksi, "SELECT * FROM kategori");

// Atur query berita berdasarkan apakah ada kategori yang dipilih atau tidak
if ($id_pilih) {
    $kondisi = "WHERE artikel.id_kategori = '$id_pilih' AND artikel.status = 'terbit'";
} else {
    $kondisi = "WHERE artikel.status = 'terbit'";
}

$query_berita = mysqli_query($koneksi, "
    SELECT artikel.*, kategori.nama_kategori, users.nama_lengkap 
    FROM artikel 
    LEFT JOIN kategori ON artikel.id_kategori = kategori.id_kategori 
    LEFT JOIN users ON artikel.id_user = users.id_user
    $kondisi
    ORDER BY artikel.tanggal_dibuat DESC
");
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Berita - WargaNet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/warganet.css">
    <script>document.documentElement.classList.add('js')</script>
</head>
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
                    <li class="nav-item"><a class="nav-link active text-white" href="kategori.php">Kategori</a></li>
                    <li class="nav-item"><a class="nav-link" href="tentang.php">Tentang Kami</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h2 class="fw-bold mb-4 text-center">Jelajahi Topik</h2>
        
        <!-- Filter Tombol Kategori -->
        <div class="d-flex justify-content-center flex-wrap gap-2 mb-5">
            <a href="kategori.php" class="btn <?= !$id_pilih ? 'btn-primary' : 'btn-outline-secondary text-white'; ?> rounded-pill px-4">Semua Berita</a>
            <?php while($kat = mysqli_fetch_assoc($query_kategori)) : ?>
                <a href="kategori.php?id=<?= $kat['id_kategori']; ?>" class="btn <?= ($id_pilih == $kat['id_kategori']) ? 'btn-primary' : 'btn-outline-secondary text-white'; ?> rounded-pill px-4">
                    <?= $kat['nama_kategori']; ?>
                </a>
            <?php endwhile; ?>
        </div>

        <!-- Grid Berita -->
        <div class="row g-4">
            <?php if (mysqli_num_rows($query_berita) > 0) : ?>
                <?php while($row = mysqli_fetch_assoc($query_berita)) : ?>
                <div class="col-md-4">
                    <div class="card card-dark h-100 rounded-4 overflow-hidden">
                        <img src="uploads/<?= $row['gambar']; ?>" class="card-img-top" alt="...">
                        <div class="card-body p-4">
                            <span class="text-info small fw-bold text-uppercase"><?= $row['nama_kategori']; ?></span>
                            <h5 class="card-title fw-bold mt-2 mb-3">
                                <a href="detail.php?id=<?= $row['id_artikel']; ?>" class="text-white text-decoration-none stretched-link"><?= $row['judul']; ?></a>
                            </h5>
                            <p class="card-text text-secondary small text-truncate-3"><?= strip_tags($row['isi_artikel']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else : ?>
                <div class="col-12 text-center py-5">
                    <h5 class="text-secondary">Belum ada berita di kategori ini.</h5>
                </div>
            <?php endif; ?>
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
    <script src="assets/js/warganet.js"></script>
</body>
</html>