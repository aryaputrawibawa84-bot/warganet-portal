<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $https,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
require 'includes/security.php';
require 'koneksi.php';

// Kategori yang dipilih dari URL (harus angka positif, selain itu dianggap "Semua Berita")
$id_pilih = valid_positive_int($_GET['id'] ?? null);

// Ambil semua daftar kategori untuk tombol filter
$query_kategori = mysqli_query($koneksi, "SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori");

// Ambil berita (prepared statement kalau ada kategori yang dipilih)
$sql = "SELECT artikel.*, kategori.nama_kategori, users.nama_lengkap
        FROM artikel
        LEFT JOIN kategori ON artikel.id_kategori = kategori.id_kategori
        LEFT JOIN users ON artikel.id_user = users.id_user
        WHERE artikel.status = 'terbit'";
if ($id_pilih !== null) {
    $sql .= " AND artikel.id_kategori = ?";
}
$sql .= " ORDER BY artikel.tanggal_dibuat DESC";

$stmt = mysqli_prepare($koneksi, $sql);
if ($id_pilih !== null) {
    mysqli_stmt_bind_param($stmt, 'i', $id_pilih);
}
mysqli_stmt_execute($stmt);
$query_berita = mysqli_stmt_get_result($stmt);
$jumlah_berita = $query_berita ? mysqli_num_rows($query_berita) : 0;

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
    <title>Kategori Berita - WargaNet</title>
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
                    <li class="nav-item"><a class="nav-link active text-white" href="kategori.php">Kategori</a></li>
                    <li class="nav-item"><a class="nav-link" href="tentang.php">Tentang Kami</a></li>
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

    <div class="container my-5">
        <h2 class="fw-bold mb-4 text-center">Jelajahi Topik</h2>

        <!-- Filter Tombol Kategori -->
        <div class="d-flex justify-content-center flex-wrap gap-2 mb-4">
            <a href="kategori.php" class="btn <?= $id_pilih === null ? 'btn-primary' : 'btn-outline-secondary text-white'; ?> rounded-pill px-4">Semua Berita</a>
            <?php if ($query_kategori) : while ($kat = mysqli_fetch_assoc($query_kategori)) : ?>
                <a href="kategori.php?id=<?= (int) $kat['id_kategori']; ?>"
                   class="btn <?= ($id_pilih === (int) $kat['id_kategori']) ? 'btn-primary' : 'btn-outline-secondary text-white'; ?> rounded-pill px-4">
                    <?= e($kat['nama_kategori']); ?>
                </a>
            <?php endwhile; endif; ?>
        </div>

        <p class="text-center text-secondary mb-5"><?= (int) $jumlah_berita; ?> berita ditemukan</p>

        <!-- Grid Berita -->
        <div class="row g-4">
            <?php if ($jumlah_berita > 0) : ?>
                <?php while ($row = mysqli_fetch_assoc($query_berita)) : ?>
                <div class="col-md-4">
                    <div class="card card-dark h-100 rounded-4 overflow-hidden">
                        <img src="uploads/<?= rawurlencode(basename((string) $row['gambar'])); ?>" class="card-img-top" alt="<?= e($row['judul']); ?>">
                        <div class="card-body p-4">
                            <span class="text-info small fw-bold text-uppercase"><?= e($row['nama_kategori'] ?? 'Umum'); ?></span>
                            <h5 class="card-title fw-bold mt-2 mb-3">
                                <a href="detail.php?id=<?= (int) $row['id_artikel']; ?>" class="text-white text-decoration-none stretched-link"><?= e($row['judul']); ?></a>
                            </h5>
                            <p class="card-text text-secondary small text-truncate-3"><?= e(strip_tags((string) $row['isi_artikel'])); ?></p>
                        </div>
                        <div class="card-footer bg-transparent border-secondary pt-0 pb-3 px-4">
                            <small class="text-secondary d-flex justify-content-between flex-wrap gap-1">
                                <span><i class="bi bi-pen"></i> <?= e($row['nama_lengkap']); ?></span>
                                <span>
                                    <i class="bi bi-eye"></i> <?= number_format((int) $row['views'], 0, ',', '.'); ?>
                                    &bull; <?= date('d M Y', strtotime($row['tanggal_dibuat'])); ?>
                                </span>
                            </small>
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
    <script src="assets/js/warganet.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/warganet.js'); ?>"></script>
</body>
</html>