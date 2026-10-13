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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $data['judul']; ?> - WargaNet</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { 
            background-color: #0F172A; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            color: #e2e8f0; 
        }
        
        .navbar-glass {
            background: rgba(15, 23, 42, 0.8) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .article-img {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            border-radius: 16px;
        }

        .content-text {
            font-size: 1.15rem;
            line-height: 1.8;
            color: #cbd5e1;
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

</body>
</html>