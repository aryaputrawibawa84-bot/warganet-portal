<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $https,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
require_once '../includes/security.php';
require '../koneksi.php';

// Proteksi halaman admin
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$pesan = "";

// Semua perubahan data wajib menggunakan POST dan token CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (isset($_POST['tambah'])) {
        $nama_kategori = trim((string)($_POST['nama_kategori'] ?? ''));
        if ($nama_kategori === '' || mb_strlen($nama_kategori) > 100) {
            $pesan = "<div class='alert alert-danger py-2'>Nama kategori wajib diisi (maksimal 100 karakter).</div>";
        } else {
            $stmt = mysqli_prepare($koneksi, 'INSERT INTO kategori (nama_kategori) VALUES (?)');
            mysqli_stmt_bind_param($stmt, 's', $nama_kategori);
            $ok = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            $pesan = $ok ? "<div class='alert alert-success py-2'>Kategori berhasil ditambahkan!</div>" : "<div class='alert alert-danger py-2'>Gagal menambahkan kategori.</div>";
        }
    } elseif (isset($_POST['hapus_kategori'])) {
        $id_hapus = valid_positive_int($_POST['id_kategori'] ?? null);
        if ($id_hapus !== null) {
            $stmt = mysqli_prepare($koneksi, 'DELETE FROM kategori WHERE id_kategori = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id_hapus); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        header('Location: kategori.php'); exit;
    }
}

// Ambil data kategori
$query_kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY id_kategori DESC");
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - WargaNet Panel</title>
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
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
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
                                            <td><?= e($row['nama_kategori']); ?></td>
                                            <td>
                                                <form action="kategori.php" method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori ini?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="id_kategori" value="<?= (int)$row['id_kategori']; ?>"><button type="submit" name="hapus_kategori" class="btn btn-sm btn-danger">Hapus</button></form>
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
    <script src="../assets/js/warganet.js"></script>
</body>
</html>