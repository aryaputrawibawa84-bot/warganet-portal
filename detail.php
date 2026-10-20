<?php
session_start();
require 'koneksi.php';

// Cek apakah ada ID artikel yang dikirim di URL
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id_artikel = mysqli_real_escape_string($koneksi, $_GET['id']);

// Ambil data artikel beserta nama penulis dan nama kategorinya
$query_detail = mysqli_query($koneksi, "
    SELECT artikel.*, kategori.nama_kategori, users.nama_lengkap 
    FROM artikel 
    LEFT JOIN kategori ON artikel.id_kategori = kategori.id_kategori 
    LEFT JOIN users ON artikel.id_user = users.id_user
    WHERE artikel.id_artikel = '$id_artikel' AND artikel.status = 'terbit'
");

// Jika artikel tidak ditemukan atau berstatus selain 'terbit'
if (mysqli_num_rows($query_detail) == 0) {
    echo "<script>alert('Artikel tidak ditemukan atau belum diterbitkan.'); window.location='index.php';</script>";
    exit;
}

$data = mysqli_fetch_assoc($query_detail);
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">  
<head>
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $data['judul']; ?> - WargaNet</title>
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
                    <span class="badge bg-primary mb-3 px-3 py-2 rounded-pill"><?= $data['nama_kategori'] ?? 'Umum'; ?></span>
                    <h1 class="fw-bold text-white mb-3" style="line-height: 1.3;"><?= $data['judul']; ?></h1>
                    <div class="text-secondary d-flex justify-content-center gap-4">
                        <span><i class="bi bi-person-circle me-1"></i> <?= $data['nama_lengkap']; ?></span>
                        <span><i class="bi bi-calendar3 me-1"></i> <?= date('d M Y', strtotime($data['tanggal_dibuat'])); ?></span>
                    </div>
                </div>

                <!-- Gambar Utama -->
                <img src="uploads/<?= $data['gambar']; ?>" class="article-img shadow-lg mb-5" alt="<?= $data['judul']; ?>">

                <!-- Teks Isi Artikel -->
                <article class="content-text mb-5">
                    <!-- nl2br digunakan agar baris baru (enter) dari textarea tetap terbaca sebagai baris baru di HTML -->
                    <?= nl2br($data['isi_artikel']); ?>
                </article>

                <!-- Footer Bagikan -->
                <div class="border-top border-secondary pt-4 mt-5 d-flex justify-content-between align-items-center">
                    <p class="mb-0 text-secondary">Bagikan cerita ini:</p>
                    <div class="gap-2 d-flex">
                        <button class="btn btn-outline-info rounded-circle"><i class="bi bi-twitter-x"></i></button>
                        <button class="btn btn-outline-primary rounded-circle"><i class="bi bi-facebook"></i></button>
                        <button class="btn btn-outline-success rounded-circle"><i class="bi bi-whatsapp"></i></button>
                    </div>
                </div>

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
    <script src="assets/js/warganet.js"></script>
</body>
</html>