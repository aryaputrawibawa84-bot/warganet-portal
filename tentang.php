<?php session_start(); ?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Tentang Kami - WargaNet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #0F172A; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar-glass { background: rgba(15, 23, 42, 0.8) !important; backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .about-card { background-color: #1E293B; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.05); }
    </style>
</head>
<body>

    <!-- NAVBAR PUBLIC -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-glass sticky-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-info" href="index.php"><i class="bi bi-activity me-1"></i> WargaNet</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="kategori.php">Kategori</a></li>
                    <li class="nav-item"><a class="nav-link active text-white" href="tentang.php">Tentang Kami</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="about-card p-5 shadow-lg text-center">
                    <i class="bi bi-activity text-info mb-4" style="font-size: 4rem;"></i>
                    <h1 class="fw-bold mb-4 text-white">Suara Warga, Inspirasi Bersama.</h1>
                    <p class="text-secondary fs-5 mb-5" style="line-height: 1.8;">
                        WargaNet adalah ruang digital independen yang didedikasikan untuk menyuarakan kabar terkini dari berbagai sudut pandang. Kami memfasilitasi setiap warga untuk tidak hanya menjadi pembaca, tetapi juga kontributor aktif yang membagikan cerita otentik dari sekitarnya.
                    </p>
                    
                    <div class="border-top border-secondary pt-4 mt-4 text-start">
                        <h5 class="fw-bold text-light mb-3">Tentang Pengembang</h5>
                        <p class="text-secondary">
                            Platform portal berita ini dikembangkan secara penuh (Full-Stack) menggunakan PHP Native dan MySQL. Proyek ini dirancang sebagai implementasi nyata dari rekayasa perangkat lunak modern dengan standar antarmuka pengguna yang imersif.
                        </p>
                        <p class="text-secondary mb-0">
                            <strong>Dikembangkan oleh:</strong> Arya<br>
                            <strong>Institusi:</strong> RPL - SMKN 5 Surakarta
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>