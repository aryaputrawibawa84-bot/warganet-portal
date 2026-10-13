<?php
session_start();
require '../koneksi.php';

// Proteksi halaman admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$pesan = "";

// Logika Tambah Kategori
if (isset($_POST['tambah'])) {
    $nama_kategori = mysqli_real_escape_string($koneksi, $_POST['nama_kategori']);
    $insert = mysqli_query($koneksi, "INSERT INTO kategori (nama_kategori) VALUES ('$nama_kategori')");
    
    if ($insert) {
        $pesan = "<div class='alert alert-success py-2'>Kategori berhasil ditambahkan!</div>";
    } else {
        $pesan = "<div class='alert alert-danger py-2'>Gagal menambahkan kategori.</div>";
    }
}

// Logika Hapus Kategori
if (isset($_GET['hapus'])) {
    $id_hapus = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    // Catatan: Karena di database kita setting ON DELETE SET NULL, 
    // menghapus kategori tidak akan menghapus artikelnya (kategori artikel akan menjadi kosong/null).
    mysqli_query($koneksi, "DELETE FROM kategori WHERE id_kategori = '$id_hapus'");
    header("Location: kategori.php");
    exit;
}

// Ambil data kategori
$query_kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY id_kategori DESC");
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Kelola Kategori - WargaNet Panel</title>
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
            <!-- Sidebar -->
            <div class="col-md-2">
                <div class="list-group list-group-flush border-end border-secondary h-100">
                    <a href="index.php" class="list-group-item list-group-item-action bg-transparent text-light">Kelola Artikel</a>
                    <a href="kategori.php" class="list-group-item list-group-item-action bg-secondary text-white">Kelola Kategori</a>
                    <a href="pengguna.php" class="list-group-item list-group-item-action bg-transparent text-light">Kelola Pengguna</a>
                    <a href="../logout.php" class="list-group-item list-group-item-action text-danger bg-transparent mt-5">Logout</a>
                </div>
            </div>

            <!-- Konten Utama -->
            <div class="col-md-10 px-4">
                <h3 class="mb-4">Kelola Kategori Berita</h3>
                <?= $pesan; ?>

                <div class="row">
                    <!-- Form Tambah Kategori -->
                    <div class="col-md-4 mb-4">
                        <div class="card card-dark shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title border-bottom border-secondary pb-2 mb-3">Tambah Kategori</h5>
                                <form action="" method="POST">
                                    <div class="mb-3">
                                        <label class="form-label text-secondary">Nama Kategori</label>
                                        <input type="text" name="nama_kategori" class="form-control bg-dark text-white border-secondary" required placeholder="Contoh: Olahraga">
                                    </div>
                                    <button type="submit" name="tambah" class="btn btn-primary w-100">Simpan Kategori</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Daftar Kategori -->
                    <div class="col-md-8">
                        <div class="card card-dark shadow-sm">
                            <div class="card-body p-0">
                                <table class="table table-dark table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th width="10%">No</th>
                                            <th>Nama Kategori</th>
                                            <th width="20%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; while ($row = mysqli_fetch_assoc($query_kategori)) : ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= $row['nama_kategori']; ?></td>
                                            <td>
                                                <a href="kategori.php?hapus=<?= $row['id_kategori']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus kategori ini?');">Hapus</a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>