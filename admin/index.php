<?php
session_start();
// Tambahkan 2 baris ini jika masih butuh debugging error
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require '../koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$keyword = "";
$kondisi_pencarian = "";

if (isset($_GET['cari'])) {
    $keyword = mysqli_real_escape_string($koneksi, $_GET['cari']);
    $kondisi_pencarian = "WHERE artikel.judul LIKE '%$keyword%'";
}

$query_artikel = "
    SELECT artikel.*, kategori.nama_kategori, users.nama_lengkap 
    FROM artikel 
    LEFT JOIN kategori ON artikel.id_kategori = kategori.id_kategori 
    LEFT JOIN users ON artikel.id_user = users.id_user
    $kondisi_pencarian
    ORDER BY artikel.tanggal_dibuat DESC
";
$result = mysqli_query($koneksi, $query_artikel);
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Kelola Artikel - WargaNet Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #0F172A; }
        .card-dark { background-color: #1E293B; border: none; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 border-bottom border-secondary">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">WargaNet Panel</a>
            <span class="navbar-text">Halo, <?= $_SESSION['nama_lengkap']; ?>!</span>
        </div>
    </nav>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Kiri -->
            <div class="col-md-2">
                <div class="list-group list-group-flush border-end border-secondary h-100">
                    <a href="index.php" class="list-group-item list-group-item-action bg-secondary text-white">Kelola Artikel</a>
                    <a href="kategori.php" class="list-group-item list-group-item-action bg-transparent text-light">Kelola Kategori</a>
                    <a href="pengguna.php" class="list-group-item list-group-item-action bg-transparent text-light">Kelola Pengguna</a>
                    <a href="logout.php" class="list-group-item list-group-item-action text-danger bg-transparent mt-5">Logout</a>
                </div>
            </div>
            
            <div class="col-md-10 px-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Daftar Artikel</h3>
                    <a href="tambah.php" class="btn btn-primary">+ Tulis Artikel Baru</a>
                </div>
                <form action="" method="GET" class="mb-4 d-flex w-50">
                    <input type="text" name="cari" class="form-control bg-dark text-white border-secondary me-2" placeholder="Cari judul..." value="<?= $keyword; ?>">
                    <button type="submit" class="btn btn-outline-secondary">Cari</button>
                    <?php if ($keyword != "") : ?>
                        <a href="index.php" class="btn btn-outline-danger ms-2">Reset</a>
                    <?php endif; ?>
                </form>
                <div class="card card-dark shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-dark table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Judul Artikel</th>
                                    <th>Kategori</th>
                                    <th>Penulis</th>
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
                                    <td><?= $row['nama_lengkap']; ?></td>
                                    <td>
                                        <?php if($row['status'] == 'terbit') { echo '<span class="badge bg-success">Terbit</span>'; } 
                                        else if($row['status'] == 'pending') { echo '<span class="badge bg-warning text-dark">Pending</span>'; } 
                                        else { echo '<span class="badge bg-secondary">'.$row['status'].'</span>'; } ?>
                                    </td>
                                    <td><?= date('d M Y', strtotime($row['tanggal_dibuat'])); ?></td>
                                    <td>
                                        <a href="edit.php?id=<?= $row['id_artikel']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="hapus.php?id=<?= $row['id_artikel']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus artikel?');">Hapus</a>
                                    </td>
                                </tr>
                                <?php endwhile; else : ?>
                                <tr><td colspan="7" class="text-center py-4">Belum ada data artikel.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>