<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $https,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
require_once '../includes/security.php';
require '../koneksi.php';

// Proteksi halaman, hanya admin yang boleh masuk
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$pesan = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (isset($_POST['ubah_role'])) {
        $id_target = valid_positive_int($_POST['id_user'] ?? null);
        $role_baru = (string)($_POST['role_baru'] ?? '');
        $allowed_roles = ['pembaca', 'penulis', 'admin'];
        if ($id_target === null || !in_array($role_baru, $allowed_roles, true)) {
            $pesan = "<div class='alert alert-danger py-2'>Data perubahan tidak valid.</div>";
        } elseif ($id_target === (int)$_SESSION['id_user'] && $role_baru !== 'admin') {
            $pesan = "<div class='alert alert-warning py-2'>Peran akun admin yang sedang digunakan tidak dapat diturunkan dari halaman ini.</div>";
        } else {
            $stmt = mysqli_prepare($koneksi, 'UPDATE users SET role = ? WHERE id_user = ?');
            mysqli_stmt_bind_param($stmt, 'si', $role_baru, $id_target);
            $ok = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            $pesan = $ok ? "<div class='alert alert-success py-2'>Hak akses pengguna berhasil diperbarui!</div>" : "<div class='alert alert-danger py-2'>Gagal memperbarui data.</div>";
        }
    } elseif (isset($_POST['hapus_user'])) {
        $id_hapus = valid_positive_int($_POST['id_user'] ?? null);
        if ($id_hapus === null || $id_hapus === (int)$_SESSION['id_user']) {
            $pesan = "<div class='alert alert-warning py-2'>Akun tidak valid atau tidak dapat menghapus akun sendiri.</div>";
        } else {
            $stmt = mysqli_prepare($koneksi, 'DELETE FROM users WHERE id_user = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id_hapus);
            $ok = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            header('Location: pengguna.php'); exit;
        }
    }
}

// Ambil semua data pengguna kecuali admin yang sedang login
$idAktif = (int)$_SESSION['id_user'];
$stmtUsers = mysqli_prepare($koneksi, 'SELECT id_user, nama_lengkap, username, role FROM users WHERE id_user != ? ORDER BY role ASC');
mysqli_stmt_bind_param($stmtUsers, 'i', $idAktif);
mysqli_stmt_execute($stmtUsers);
$query_users = mysqli_stmt_get_result($stmtUsers);
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - WargaNet Panel</title>
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
            <a class="navbar-brand fw-bold" href="#">WargaNet Panel</a>
            <span class="navbar-text">Halo, <?= e($_SESSION['nama_lengkap'] ?? 'Admin'); ?>!</span>
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
                                    <td class="align-middle"><?= e($row['nama_lengkap']); ?></td>
                                    <td class="align-middle">@<?= e($row['username']); ?></td>
                                    <td class="align-middle">
                                        <?php if($row['role'] == 'admin') { echo '<span class="badge bg-danger">Admin</span>'; } 
                                        else if($row['role'] == 'penulis') { echo '<span class="badge bg-primary">Penulis</span>'; } 
                                        else { echo '<span class="badge bg-secondary">Pembaca</span>'; } ?>
                                    </td>
                                    <td>
                                        <!-- Form Kecil untuk Ubah Role Langsung di Tabel -->
                                        <form action="" method="POST" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="id_user" value="<?= $row['id_user']; ?>">
                                            <select name="role_baru" class="form-select form-select-sm bg-dark text-white border-secondary">
                                                <option value="pembaca" <?= ($row['role'] == 'pembaca') ? 'selected' : ''; ?>>Pembaca</option>
                                                <option value="penulis" <?= ($row['role'] == 'penulis') ? 'selected' : ''; ?>>Penulis</option>
                                                <option value="admin" <?= ($row['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                            <button type="submit" name="ubah_role" class="btn btn-sm btn-success">Simpan</button>
                                            <button type="submit" name="hapus_user" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus akun ini permanen?');">Hapus</button>
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
    <script src="../assets/js/warganet.js"></script>
</body>
</html>