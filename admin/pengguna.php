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
require_once __DIR__ . '/../koneksi.php';


// ==========================================
// 2. PROTEKSI HALAMAN ADMIN
// ==========================================

$id_admin = valid_positive_int($_SESSION['id_user'] ?? null);
$role_admin = $_SESSION['role'] ?? '';

if ($id_admin === null || $role_admin !== 'admin') {
    header('Location: ../login.php');
    exit;
}


// ==========================================
// 3. VARIABEL PESAN
// ==========================================

$pesan = '';
$jenis_pesan = '';


// ==========================================
// 4. PROSES PERUBAHAN DATA PENGGUNA
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf();

    // --------------------------------------
    // UBAH HAK AKSES PENGGUNA
    // --------------------------------------

    if (isset($_POST['ubah_role'])) {

        $id_target = valid_positive_int($_POST['id_user'] ?? null);
        $role_baru = (string)($_POST['role_baru'] ?? '');

        $allowed_roles = ['pembaca', 'penulis', 'admin'];

        if (
            $id_target === null ||
            !in_array($role_baru, $allowed_roles, true)
        ) {

            $pesan = 'Data perubahan hak akses tidak valid.';
            $jenis_pesan = 'danger';

        } elseif ($id_target === $id_admin) {

            $pesan = 'Hak akses akun admin yang sedang digunakan tidak dapat diubah dari halaman ini.';
            $jenis_pesan = 'warning';

        } else {

            // Periksa apakah pengguna tersedia dan ambil role sebelumnya.
            $cek = mysqli_prepare(
                $koneksi,
                'SELECT role FROM users WHERE id_user = ? LIMIT 1'
            );

            if ($cek) {

                mysqli_stmt_bind_param($cek, 'i', $id_target);

                if (mysqli_stmt_execute($cek)) {

                    $hasil = mysqli_stmt_get_result($cek);
                    $data_user = $hasil ? mysqli_fetch_assoc($hasil) : null;

                    mysqli_stmt_close($cek);

                    if (!$data_user) {

                        $pesan = 'Pengguna tidak ditemukan.';
                        $jenis_pesan = 'warning';

                    } elseif ($data_user['role'] === $role_baru) {

                        $pesan = 'Pengguna tersebut sudah memiliki hak akses yang dipilih.';
                        $jenis_pesan = 'info';

                    } else {

                        // Jalankan perubahan hak akses.
                        $stmt = mysqli_prepare(
                            $koneksi,
                            'UPDATE users SET role = ? WHERE id_user = ?'
                        );

                        if ($stmt) {

                            mysqli_stmt_bind_param(
                                $stmt,
                                'si',
                                $role_baru,
                                $id_target
                            );

                            if (mysqli_stmt_execute($stmt)) {

                                if (mysqli_stmt_affected_rows($stmt) === 1) {
                                    $pesan = 'Hak akses pengguna berhasil diperbarui.';
                                    $jenis_pesan = 'success';
                                } else {
                                    $pesan = 'Hak akses tidak berhasil diperbarui. Silakan coba kembali.';
                                    $jenis_pesan = 'warning';
                                }

                            } else {

                                error_log(
                                    'WargaNet update user role failed: ' .
                                    mysqli_stmt_error($stmt)
                                );

                                $pesan = 'Gagal memperbarui hak akses pengguna.';
                                $jenis_pesan = 'danger';
                            }

                            mysqli_stmt_close($stmt);

                        } else {

                            error_log(
                                'WargaNet update role prepare failed: ' .
                                mysqli_error($koneksi)
                            );

                            $pesan = 'Terjadi kesalahan pada database.';
                            $jenis_pesan = 'danger';
                        }
                    }

                } else {

                    error_log(
                        'WargaNet check user role failed: ' .
                        mysqli_stmt_error($cek)
                    );

                    mysqli_stmt_close($cek);

                    $pesan = 'Gagal memeriksa data pengguna.';
                    $jenis_pesan = 'danger';
                }

            } else {

                error_log(
                    'WargaNet check user prepare failed: ' .
                    mysqli_error($koneksi)
                );

                $pesan = 'Terjadi kesalahan pada database.';
                $jenis_pesan = 'danger';
            }
        }
    }


    // --------------------------------------
    // HAPUS AKUN PENGGUNA
    // --------------------------------------

    elseif (isset($_POST['hapus_user'])) {

        $id_hapus = valid_positive_int($_POST['id_user'] ?? null);

        if ($id_hapus === null) {

            $pesan = 'ID pengguna tidak valid.';
            $jenis_pesan = 'danger';

        } elseif ($id_hapus === $id_admin) {

            $pesan = 'Kamu tidak dapat menghapus akun yang sedang digunakan.';
            $jenis_pesan = 'warning';

        } else {

            $stmt = mysqli_prepare(
                $koneksi,
                'DELETE FROM users WHERE id_user = ?'
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    'i',
                    $id_hapus
                );

                if (mysqli_stmt_execute($stmt)) {

                    if (mysqli_stmt_affected_rows($stmt) === 1) {
                        $pesan = 'Akun pengguna berhasil dihapus.';
                        $jenis_pesan = 'success';
                    } else {
                        $pesan = 'Akun pengguna tidak ditemukan.';
                        $jenis_pesan = 'warning';
                    }

                } else {

                    error_log(
                        'WargaNet delete user failed: ' .
                        mysqli_stmt_error($stmt)
                    );

                    $pesan = 'Akun gagal dihapus. Pastikan akun tersebut tidak masih digunakan oleh data lain.';
                    $jenis_pesan = 'danger';
                }

                mysqli_stmt_close($stmt);

            } else {

                error_log(
                    'WargaNet delete user prepare failed: ' .
                    mysqli_error($koneksi)
                );

                $pesan = 'Terjadi kesalahan pada database.';
                $jenis_pesan = 'danger';
            }
        }
    }
}


// ==========================================
// 5. AMBIL DATA PENGGUNA
// ==========================================

$stmtUsers = mysqli_prepare(
    $koneksi,
    'SELECT id_user, nama_lengkap, username, role
     FROM users
     WHERE id_user != ?
     ORDER BY role ASC, nama_lengkap ASC'
);

$query_users = false;

if ($stmtUsers) {

    mysqli_stmt_bind_param($stmtUsers, 'i', $id_admin);

    if (mysqli_stmt_execute($stmtUsers)) {
        $query_users = mysqli_stmt_get_result($stmtUsers);
    } else {
        error_log(
            'WargaNet user list query failed: ' .
            mysqli_stmt_error($stmtUsers)
        );
    }

} else {

    error_log(
        'WargaNet user list prepare failed: ' .
        mysqli_error($koneksi)
    );
}

?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">

<head>
    <meta name="theme-color" content="#0B1120">
    <!-- Terapkan tema tersimpan sebelum halaman digambar (mencegah kedipan) -->
    <script>
        (function () {
            var t = 'dark';
            try { t = localStorage.getItem('wn-theme') === 'light' ? 'light' : 'dark'; } catch (e) {}
            document.documentElement.setAttribute('data-bs-theme', t);
            var m = document.querySelector('meta[name="theme-color"]');
            if (m) m.setAttribute('content', t === 'light' ? '#F3F6FC' : '#0B1120');
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Pengguna - WargaNet Panel</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/warganet.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/warganet.css'); ?>">
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
                       class="list-group-item list-group-item-action bg-transparent">
                        Kelola Artikel
                    </a>

                    <a href="kategori.php"
                       class="list-group-item list-group-item-action bg-transparent">
                        Kelola Kategori
                    </a>

                    <a href="pengguna.php"
                       class="list-group-item list-group-item-action bg-secondary text-white">
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

                <div class="d-flex justify-content-between align-items-center mb-3">

                    <h3>Daftar Warga Terdaftar</h3>

                </div>


                <!-- PESAN NOTIFIKASI -->
                <?php if ($pesan !== '') : ?>

                    <div class="alert alert-<?= e($jenis_pesan); ?> py-2">
                        <?= e($pesan); ?>
                    </div>

                <?php endif; ?>


                <!-- TABEL PENGGUNA -->
                <div class="card card-dark shadow-sm">

                    <div class="card-body p-0">

                        <div class="table-responsive">

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

                                    <?php if ($query_users && mysqli_num_rows($query_users) > 0) : ?>

                                        <?php $no = 1; ?>

                                        <?php while ($row = mysqli_fetch_assoc($query_users)) : ?>

                                            <tr>

                                                <td class="align-middle">
                                                    <?= $no++; ?>
                                                </td>

                                                <td class="align-middle">
                                                    <?= e($row['nama_lengkap']); ?>
                                                </td>

                                                <td class="align-middle">
                                                    @<?= e($row['username']); ?>
                                                </td>

                                                <td class="align-middle">

                                                    <?php if ($row['role'] === 'admin') : ?>

                                                        <span class="badge bg-danger">
                                                            Admin
                                                        </span>

                                                    <?php elseif ($row['role'] === 'penulis') : ?>

                                                        <span class="badge bg-primary">
                                                            Penulis
                                                        </span>

                                                    <?php else : ?>

                                                        <span class="badge bg-secondary">
                                                            Pembaca
                                                        </span>

                                                    <?php endif; ?>

                                                </td>

                                                <td>

                                                    <!-- FORM UBAH ROLE DAN HAPUS AKUN -->
                                                    <form action="pengguna.php"
                                                          method="POST"
                                                          class="d-flex gap-2">

                                                        <input type="hidden"
                                                               name="csrf_token"
                                                               value="<?= e(csrf_token()); ?>">

                                                        <input type="hidden"
                                                               name="id_user"
                                                               value="<?= (int)$row['id_user']; ?>">

                                                        <select name="role_baru"
                                                                class="form-select form-select-sm bg-dark text-white border-secondary">

                                                            <option value="pembaca"
                                                                <?= ($row['role'] === 'pembaca') ? 'selected' : ''; ?>>
                                                                Pembaca
                                                            </option>

                                                            <option value="penulis"
                                                                <?= ($row['role'] === 'penulis') ? 'selected' : ''; ?>>
                                                                Penulis
                                                            </option>

                                                            <option value="admin"
                                                                <?= ($row['role'] === 'admin') ? 'selected' : ''; ?>>
                                                                Admin
                                                            </option>

                                                        </select>

                                                        <button type="submit"
                                                                name="ubah_role"
                                                                class="btn btn-sm btn-success"
                                                                onclick="return konfirmasiPerubahanRole(this.form);">
                                                            Simpan
                                                        </button>

                                                        <button type="submit"
                                                                name="hapus_user"
                                                                class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('Hapus akun ini secara permanen?');">
                                                            Hapus
                                                        </button>

                                                    </form>

                                                </td>

                                            </tr>

                                        <?php endwhile; ?>

                                    <?php else : ?>

                                        <tr>

                                            <td colspan="5" class="text-center py-4 text-secondary">

                                                <?php if ($query_users === false) : ?>
                                                    Data pengguna belum dapat dimuat.
                                                <?php else : ?>
                                                    Belum ada pengguna lain yang terdaftar.
                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>


                <!-- TIPS SISTEM -->
                <div class="alert alert-info mt-4 bg-transparent border-info text-info">

                    <strong>Tips Sistem:</strong>

                    Jika ada pembaca yang meminta izin menjadi penulis berita,
                    Anda dapat mencarinya di tabel ini dan mengubah opsi statusnya
                    menjadi <strong>"Penulis"</strong>, lalu klik Simpan.

                </div>

            </div>

        </div>

    </div>


    <script>
        function konfirmasiPerubahanRole(form) {
            const roleBaru = form.querySelector('select[name="role_baru"]').value;

            if (roleBaru === 'admin') {
                return confirm(
                    'Apakah kamu yakin ingin memberikan hak akses ADMIN kepada pengguna ini?'
                );
            }

            return true;
        }
    </script>

    <script src="../assets/js/warganet.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/warganet.js'); ?>"></script>

</body>
</html>

<?php
if ($stmtUsers) {
    mysqli_stmt_close($stmtUsers);
}
?>
