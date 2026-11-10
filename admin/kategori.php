<?php
// ==========================================
// 1. PENGATURAN SESSION DAN KONEKSI
// ==========================================

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/tampilan.php';
require_once __DIR__ . '/../koneksi.php';


// ==========================================
// 2. PROTEKSI HALAMAN ADMIN
// ==========================================

$id_user = valid_positive_int($_SESSION['id_user'] ?? null);
$role = $_SESSION['role'] ?? '';

if ($id_user === null || $role !== 'admin') {
    header('Location: ../login.php');
    exit;
}


// ==========================================
// 3. VARIABEL PESAN
// ==========================================

$pesan = '';
$jenis_pesan = '';


// ==========================================
// 4. PROSES TAMBAH DAN HAPUS KATEGORI
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf();

    // --------------------------------------
    // TAMBAH KATEGORI
    // --------------------------------------

    if (isset($_POST['tambah'])) {

        $nama_kategori = trim((string)($_POST['nama_kategori'] ?? ''));

        $panjang_nama = function_exists('mb_strlen')
            ? mb_strlen($nama_kategori, 'UTF-8')
            : strlen($nama_kategori);

        if ($nama_kategori === '' || $panjang_nama > 100) {

            $pesan = 'Nama kategori wajib diisi dan maksimal 100 karakter.';
            $jenis_pesan = 'danger';

        } else {

            // Periksa kategori duplikat.
            $cek = mysqli_prepare(
                $koneksi,
                'SELECT id_kategori
                 FROM kategori
                 WHERE LOWER(nama_kategori) = LOWER(?)
                 LIMIT 1'
            );

            if ($cek) {

                mysqli_stmt_bind_param($cek, 's', $nama_kategori);

                if (mysqli_stmt_execute($cek)) {

                    $hasil = mysqli_stmt_get_result($cek);
                    $kategori_ada = $hasil
                        ? mysqli_fetch_assoc($hasil)
                        : null;

                    mysqli_stmt_close($cek);

                    if ($kategori_ada) {

                        $pesan = 'Kategori dengan nama tersebut sudah tersedia.';
                        $jenis_pesan = 'warning';

                    } else {

                        // Tambahkan kategori jika belum tersedia.
                        $stmt = mysqli_prepare(
                            $koneksi,
                            'INSERT INTO kategori (nama_kategori) VALUES (?)'
                        );

                        if ($stmt) {

                            mysqli_stmt_bind_param(
                                $stmt,
                                's',
                                $nama_kategori
                            );

                            if (mysqli_stmt_execute($stmt)) {

                                $pesan = 'Kategori berhasil ditambahkan!';
                                $jenis_pesan = 'success';

                            } else {

                                error_log(
                                    'WargaNet add category failed: ' .
                                    mysqli_stmt_error($stmt)
                                );

                                $pesan = 'Gagal menambahkan kategori.';
                                $jenis_pesan = 'danger';
                            }

                            mysqli_stmt_close($stmt);

                        } else {

                            error_log(
                                'WargaNet category insert prepare failed: ' .
                                mysqli_error($koneksi)
                            );

                            $pesan = 'Terjadi kesalahan pada database.';
                            $jenis_pesan = 'danger';
                        }
                    }

                } else {

                    error_log(
                        'WargaNet category duplicate check failed: ' .
                        mysqli_stmt_error($cek)
                    );

                    mysqli_stmt_close($cek);

                    $pesan = 'Gagal memeriksa nama kategori.';
                    $jenis_pesan = 'danger';
                }

            } else {

                error_log(
                    'WargaNet category check prepare failed: ' .
                    mysqli_error($koneksi)
                );

                $pesan = 'Terjadi kesalahan pada database.';
                $jenis_pesan = 'danger';
            }
        }
    }


    // --------------------------------------
    // HAPUS KATEGORI
    // --------------------------------------

    elseif (isset($_POST['hapus_kategori'])) {

        $id_hapus = valid_positive_int($_POST['id_kategori'] ?? null);

        if ($id_hapus === null) {

            $pesan = 'ID kategori tidak valid.';
            $jenis_pesan = 'danger';

        } else {

            // Periksa apakah kategori masih digunakan artikel.
            $cek = mysqli_prepare(
                $koneksi,
                'SELECT COUNT(*) AS jumlah
                 FROM artikel
                 WHERE id_kategori = ?'
            );

            if ($cek) {

                mysqli_stmt_bind_param($cek, 'i', $id_hapus);

                if (mysqli_stmt_execute($cek)) {

                    $hasil = mysqli_stmt_get_result($cek);
                    $data = $hasil ? mysqli_fetch_assoc($hasil) : null;

                    mysqli_stmt_close($cek);

                    if (!$data) {

                        $pesan = 'Gagal memeriksa penggunaan kategori.';
                        $jenis_pesan = 'danger';

                    } elseif ((int)$data['jumlah'] > 0) {

                        $pesan = 'Kategori tidak dapat dihapus karena masih digunakan oleh artikel.';
                        $jenis_pesan = 'warning';

                    } else {

                        // Hapus kategori jika tidak digunakan.
                        $stmt = mysqli_prepare(
                            $koneksi,
                            'DELETE FROM kategori WHERE id_kategori = ?'
                        );

                        if ($stmt) {

                            mysqli_stmt_bind_param(
                                $stmt,
                                'i',
                                $id_hapus
                            );

                            if (mysqli_stmt_execute($stmt)) {

                                if (mysqli_stmt_affected_rows($stmt) === 1) {
                                    $pesan = 'Kategori berhasil dihapus.';
                                    $jenis_pesan = 'success';
                                } else {
                                    $pesan = 'Kategori tidak ditemukan.';
                                    $jenis_pesan = 'warning';
                                }

                            } else {

                                error_log(
                                    'WargaNet delete category failed: ' .
                                    mysqli_stmt_error($stmt)
                                );

                                $pesan = 'Kategori gagal dihapus. Silakan periksa kembali data kategori.';
                                $jenis_pesan = 'danger';
                            }

                            mysqli_stmt_close($stmt);

                        } else {

                            error_log(
                                'WargaNet category delete prepare failed: ' .
                                mysqli_error($koneksi)
                            );

                            $pesan = 'Terjadi kesalahan pada database.';
                            $jenis_pesan = 'danger';
                        }
                    }

                } else {

                    error_log(
                        'WargaNet category usage check failed: ' .
                        mysqli_stmt_error($cek)
                    );

                    mysqli_stmt_close($cek);

                    $pesan = 'Gagal memeriksa penggunaan kategori.';
                    $jenis_pesan = 'danger';
                }

            } else {

                error_log(
                    'WargaNet category usage prepare failed: ' .
                    mysqli_error($koneksi)
                );

                $pesan = 'Terjadi kesalahan pada database.';
                $jenis_pesan = 'danger';
            }
        }
    }
}


// ==========================================
// 5. AMBIL DATA KATEGORI
// ==========================================

$query_kategori = mysqli_query(
    $koneksi,
    'SELECT id_kategori, nama_kategori
     FROM kategori
     ORDER BY id_kategori DESC'
);

if (!$query_kategori) {
    error_log(
        'WargaNet category list failed: ' .
        mysqli_error($koneksi)
    );
}

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

    <link rel="stylesheet" href="../assets/css/warganet.css">

    <script>
        document.documentElement.classList.add('js')
    </script>
</head>

<body class="wn-panel">

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 border-bottom border-secondary">

        <div class="container-fluid">

            <a class="navbar-brand fw-bold" href="#">
                WargaNet Panel
            </a>

            <span class="navbar-text">
                Halo, <?= e($_SESSION['nama_lengkap'] ?? 'Admin'); ?>!
            </span>

        </div>

    </nav>


    <div class="container-fluid">

        <div class="row">

            <!-- SIDEBAR -->
            <div class="col-md-2">

                <div class="list-group list-group-flush border-end border-secondary h-100">

                    <a href="index.php"
                       class="list-group-item list-group-item-action bg-transparent text-light">
                        Kelola Artikel
                    </a>

                    <a href="kategori.php"
                       class="list-group-item list-group-item-action bg-secondary text-white">
                        Kelola Kategori
                    </a>

                    <a href="pengguna.php"
                       class="list-group-item list-group-item-action bg-transparent text-light">
                        Kelola Pengguna
                    </a>

                    <!-- LOGOUT POST + CSRF -->
                    <form action="../logout.php" method="POST" class="mt-5">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= e(csrf_token()); ?>">

                        <button type="submit"
                                class="list-group-item list-group-item-action text-danger bg-transparent w-100 text-start border-0">
                            Logout
                        </button>

                    </form>

                </div>

            </div>


            <!-- KONTEN UTAMA -->
            <div class="col-md-10 px-4">

                <h3 class="mb-4">
                    Kelola Kategori Berita
                </h3>


                <!-- PESAN NOTIFIKASI -->
                <?php if ($pesan !== '') : ?>

                    <div class="alert alert-<?= e($jenis_pesan); ?> py-2">
                        <?= e($pesan); ?>
                    </div>

                <?php endif; ?>


                <div class="row">

                    <!-- FORM TAMBAH KATEGORI -->
                    <div class="col-md-4 mb-4">

                        <div class="card card-dark shadow-sm">

                            <div class="card-body">

                                <h5 class="card-title border-bottom border-secondary pb-2 mb-3">
                                    Tambah Kategori
                                </h5>

                                <form action="kategori.php" method="POST">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?= e(csrf_token()); ?>">

                                    <div class="mb-3">

                                        <label class="form-label text-secondary">
                                            Nama Kategori
                                        </label>

                                        <input type="text"
                                               name="nama_kategori"
                                               class="form-control bg-dark text-white border-secondary"
                                               maxlength="100"
                                               required
                                               placeholder="Contoh: Olahraga">

                                    </div>

                                    <button type="submit"
                                            name="tambah"
                                            class="btn btn-primary w-100">
                                        Simpan Kategori
                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>


                    <!-- TABEL DAFTAR KATEGORI -->
                    <div class="col-md-8">

                        <div class="card card-dark shadow-sm">

                            <div class="card-body p-0">

                                <div class="table-responsive">

                                    <table class="table table-dark table-hover table-striped mb-0">

                                        <thead>

                                            <tr>
                                                <th width="10%">No</th>
                                                <th>Nama Kategori</th>
                                                <th width="20%">Aksi</th>
                                            </tr>

                                        </thead>

                                        <tbody>

                                            <?php if ($query_kategori && mysqli_num_rows($query_kategori) > 0) : ?>

                                                <?php $no = 1; ?>

                                                <?php while ($row = mysqli_fetch_assoc($query_kategori)) : ?>

                                                    <tr>

                                                        <td>
                                                            <?= $no++; ?>
                                                        </td>

                                                        <td>
                                                            <?= e($row['nama_kategori']); ?>
                                                        </td>

                                                        <td>

                                                            <form action="kategori.php"
                                                                  method="POST"
                                                                  class="d-inline"
                                                                  onsubmit="return confirm('Hapus kategori ini?');">

                                                                <input type="hidden"
                                                                       name="csrf_token"
                                                                       value="<?= e(csrf_token()); ?>">

                                                                <input type="hidden"
                                                                       name="id_kategori"
                                                                       value="<?= (int)$row['id_kategori']; ?>">

                                                                <button type="submit"
                                                                        name="hapus_kategori"
                                                                        class="btn btn-sm btn-danger">
                                                                    Hapus
                                                                </button>

                                                            </form>

                                                        </td>

                                                    </tr>

                                                <?php endwhile; ?>

                                            <?php else : ?>

                                                <tr>

                                                    <td colspan="3" class="text-center py-4 text-secondary">

                                                        <?php if ($query_kategori === false) : ?>
                                                            Data kategori belum dapat dimuat.
                                                        <?php else : ?>
                                                            Belum ada kategori tersedia.
                                                        <?php endif; ?>

                                                    </td>

                                                </tr>

                                            <?php endif; ?>

                                        </tbody>

                                    </table>

                                </div>

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
