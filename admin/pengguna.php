<?php
session_start();
require '../koneksi.php';

// Proteksi halaman, hanya admin yang boleh masuk
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$pesan = "";

// Logika untuk mengubah Role (Naik pangkat / Turun pangkat)
if (isset($_POST['ubah_role'])) {
    $id_target = $_POST['id_user'];
    $role_baru = $_POST['role_baru'];
    
    $update = mysqli_query($koneksi, "UPDATE users SET role = '$role_baru' WHERE id_user = '$id_target'");
    if ($update) {
        $pesan = "<div class='alert alert-success py-2'>Hak akses pengguna berhasil diperbarui!</div>";
    } else {
        $pesan = "<div class='alert alert-danger py-2'>Gagal memperbarui data.</div>";
    }
}

// Logika untuk menghapus pengguna
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    // Cegah admin menghapus dirinya sendiri
    if ($id_hapus != $_SESSION['id_user']) {
        mysqli_query($koneksi, "DELETE FROM users WHERE id_user = '$id_hapus'");
        header("Location: pengguna.php");
        exit;
    }
}

// Ambil semua data pengguna kecuali admin yang sedang login
$query_users = mysqli_query($koneksi, "SELECT * FROM users WHERE id_user != '{$_SESSION['id_user']}' ORDER BY role ASC");
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Kelola Pengguna - WargaNet Panel</title>
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
                    <a href="index.php" class="list-group-item list-group-item-action bg-transparent">Kelola Artikel</a>
                    <a href="kategori.php" class="list-group-item list-group-item-action bg-transparent">Kelola Kategori</a>
                    <a href="pengguna.php" class="list-group-item list-group-item-action bg-secondary text-white">Kelola Pengguna</a>
                    <a href="../logout.php" class="list-group-item list-group-item-action text-danger bg-transparent mt-5">Logout</a>
                </div>
            </div>

            <!-- Area Konten -->
            <div class="col-md-10 px-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Daftar Warga Terdaftar</h3>
                </div>
                
                <?= $pesan; ?>

                <div class="card card-dark shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-dark table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Lengkap</th>
                                    <th>Username</th>
                                    <th>Status Hak Akses</th>
                                    <th width="250">Aksi (Ubah Akses)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; while ($row = mysqli_fetch_assoc($query_users)) : ?>
                                <tr>
                                    <td class="align-middle"><?= $no++; ?></td>
                                    <td class="align-middle"><?= $row['nama_lengkap']; ?></td>
                                    <td class="align-middle">@<?= $row['username']; ?></td>
                                    <td class="align-middle">
                                        <?php if($row['role'] == 'admin') { echo '<span class="badge bg-danger">Admin</span>'; } 
                                        else if($row['role'] == 'penulis') { echo '<span class="badge bg-primary">Penulis</span>'; } 
                                        else { echo '<span class="badge bg-secondary">Pembaca</span>'; } ?>
                                    </td>
                                    <td>
                                        <!-- Form Kecil untuk Ubah Role Langsung di Tabel -->
                                        <form action="" method="POST" class="d-flex gap-2">
                                            <input type="hidden" name="id_user" value="<?= $row['id_user']; ?>">
                                            <select name="role_baru" class="form-select form-select-sm bg-dark text-white border-secondary">
                                                <option value="pembaca" <?= ($row['role'] == 'pembaca') ? 'selected' : ''; ?>>Pembaca</option>
                                                <option value="penulis" <?= ($row['role'] == 'penulis') ? 'selected' : ''; ?>>Penulis</option>
                                                <option value="admin" <?= ($row['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                            <button type="submit" name="ubah_role" class="btn btn-sm btn-success">Simpan</button>
                                            <a href="pengguna.php?hapus=<?= $row['id_user']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus akun ini permanen?');">Hapus</a>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="alert alert-info mt-4 bg-transparent border-info text-info">
                    <strong>Tips Sistem:</strong> Jika ada pembaca yang meminta izin menjadi penulis berita, Anda dapat mencarinya di tabel ini dan mengubah opsi statusnya menjadi <strong>"Penulis"</strong> lalu klik Simpan.
                </div>

            </div>
        </div>
    </div>
</body>
</html>