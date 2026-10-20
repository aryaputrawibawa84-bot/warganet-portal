<?php
session_start();
require '../koneksi.php';

// Proteksi: Hanya yang berstatus 'penulis' yang boleh masuk
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'penulis') {
    header("Location: ../login.php");
    exit;
}

$id_user_aktif = $_SESSION['id_user'];
$keyword = "";
$kondisi_pencarian = "";

if (isset($_GET['cari'])) {
    $keyword = mysqli_real_escape_string($koneksi, $_GET['cari']);
    $kondisi_pencarian = "AND artikel.judul LIKE '%$keyword%'";
}

// Query HANYA mengambil artikel milik user yang sedang login
$query_artikel = "
    SELECT artikel.*, kategori.nama_kategori 
    FROM artikel 
    LEFT JOIN kategori ON artikel.id_kategori = kategori.id_kategori 
    WHERE artikel.id_user = '$id_user_aktif' $kondisi_pencarian
    ORDER BY artikel.tanggal_dibuat DESC
";
$result = mysqli_query($koneksi, $query_artikel);
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruang Redaksi - WargaNet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/warganet.css">
    <script>document.documentElement.classList.add('js')</script>
</head>
<body class="wn-panel">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 border-bottom border-secondary">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-info" href="#">WargaNet | Ruang Penulis</a>
            <span class="navbar-text">Halo, <?= $_SESSION['nama_lengkap']; ?>!</span>
        </div>
    </nav>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Kiri -->
            <div class="col-md-2">
                <div class="list-group list-group-flush border-end border-secondary h-100">
                    <a href="index.php" class="list-group-item list-group-item-action bg-secondary text-white">Artikel Saya</a>
                    <a href="../index.php" class="list-group-item list-group-item-action bg-transparent text-light" target="_blank">Lihat Web Publik</a>
                    <a href="../logout.php" class="list-group-item list-group-item-action text-danger bg-transparent mt-5">Logout</a>
                </div>
            </div>
            
            <div class="col-md-10 px-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Karya Tulisan Saya</h3>
                    <a href="tambah.php" class="btn btn-primary">+ Tulis Berita Baru</a>
                </div>
                <div class="alert alert-info bg-transparent border-info text-info py-2 mb-4">
                    <strong>Catatan:</strong> Artikel yang baru Anda tulis akan berstatus <em>Pending</em> hingga disetujui oleh Administrator.
                </div>
                <div class="card card-dark shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-dark table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Judul Artikel</th>
                                    <th>Kategori</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th width="150">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; if (mysqli_num_rows($result) > 0) : while ($row = mysqli_fetch_assoc($result)) : ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= $row['judul']; ?></td>
                                    <td><?= $row['nama_kategori'] ?? 'Tanpa Kategori'; ?></td>
                                    <td>
                                        <?php if($row['status'] == 'terbit') { echo '<span class="badge bg-success">Terbit</span>'; } 
                                        else if($row['status'] == 'pending') { echo '<span class="badge bg-warning text-dark">Pending</span>'; } 
                                        else { echo '<span class="badge bg-secondary">'.$row['status'].'</span>'; } ?>
                                    </td>
                                    <td><?= date('d M Y', strtotime($row['tanggal_dibuat'])); ?></td>
                                    <td>
                                        <a href="edit.php?id=<?= $row['id_artikel']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="hapus.php?id=<?= $row['id_artikel']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus karya ini?');">Hapus</a>
                                    </td>
                                </tr>
                                <?php endwhile; else : ?>
                                <tr><td colspan="6" class="text-center py-4">Anda belum menulis artikel.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/warganet.js"></script>
</body>
</html>