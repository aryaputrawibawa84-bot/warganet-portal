<?php
// 1. Pengaturan session cookie
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/koneksi.php';


// 2. Jika sudah login, arahkan sesuai role
if (isset($_SESSION['id_user'])) {
    $role = $_SESSION['role'] ?? '';

    if (in_array($role, ['admin', 'penulis', 'pembaca'], true)) {
        if ($role === 'admin') {
            header('Location: admin/index.php');
        } elseif ($role === 'penulis') {
            header('Location: penulis/index.php');
        } else {
            header('Location: index.php');
        }

        exit;
    }

    // Hapus sesi yang memiliki role tidak valid
    $_SESSION = [];
    session_regenerate_id(true);
}

$pesan_error = '';
$pesan_sukses = '';


// 3. Proses pendaftaran hanya melalui POST
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['register'])
) {
    require_csrf();

    $nama_lengkap = trim((string) ($_POST['nama_lengkap'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $konfirmasi_password = (string) ($_POST['konfirmasi_password'] ?? '');

    // 4. Validasi data
    $panjang_nama = function_exists('mb_strlen')
        ? mb_strlen($nama_lengkap, 'UTF-8')
        : strlen($nama_lengkap);

    if (
        $nama_lengkap === '' ||
        $panjang_nama > 100
    ) {
        $pesan_error = 'Nama lengkap wajib diisi dan maksimal 100 karakter.';

    } elseif (
        !preg_match('/\A[A-Za-z0-9_.-]{3,50}\z/', $username)
    ) {
        $pesan_error = 'Username harus 3–50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';

    } elseif (
        strlen($password) < 8 ||
        strlen($password) > 72
    ) {
        $pesan_error = 'Kata sandi harus minimal 8 karakter dan maksimal 72 byte.';

    } elseif ($password !== $konfirmasi_password) {
        $pesan_error = 'Konfirmasi kata sandi tidak cocok!';

    } else {

        // 5. Periksa apakah username sudah digunakan
        $stmt = mysqli_prepare(
            $koneksi,
            'SELECT id_user
             FROM users
             WHERE username = ?
             LIMIT 1'
        );

        if (!$stmt) {
            error_log(
                'WargaNet registration lookup prepare failed: ' .
                mysqli_error($koneksi)
            );

            $pesan_error = 'Pendaftaran belum dapat diproses. Silakan coba kembali.';

        } else {

            mysqli_stmt_bind_param($stmt, 's', $username);

            $lookup_ok = mysqli_stmt_execute($stmt);

            if ($lookup_ok) {
                mysqli_stmt_store_result($stmt);
                $exists = mysqli_stmt_num_rows($stmt) > 0;
            } else {
                $exists = false;

                error_log(
                    'WargaNet registration lookup failed: ' .
                    mysqli_stmt_error($stmt)
                );
            }

            mysqli_stmt_close($stmt);

            if (!$lookup_ok) {
                $pesan_error = 'Pendaftaran belum dapat diproses. Silakan coba kembali.';

            } elseif ($exists) {
                $pesan_error = 'Username sudah terdaftar, silakan gunakan yang lain!';

            } else {

                // 6. Hash password sebelum disimpan
                $password_hashed = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // 7. Role pendaftar selalu pembaca
                $role = 'pembaca';

                $stmt = mysqli_prepare(
                    $koneksi,
                    'INSERT INTO users
                     (nama_lengkap, username, password, role)
                     VALUES (?, ?, ?, ?)'
                );

                if (!$stmt) {
                    error_log(
                        'WargaNet registration insert prepare failed: ' .
                        mysqli_error($koneksi)
                    );

                    $pesan_error = 'Pendaftaran belum dapat diproses. Silakan coba kembali.';

                } else {

                    mysqli_stmt_bind_param(
                        $stmt,
                        'ssss',
                        $nama_lengkap,
                        $username,
                        $password_hashed,
                        $role
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $pesan_sukses = 'Pendaftaran berhasil! Silakan masuk dengan akun baru Anda.';

                    } else {

                        $errno = mysqli_stmt_errno($stmt);

                        if ($errno === 1062) {
                            $pesan_error = 'Username sudah terdaftar, silakan gunakan yang lain!';
                        } else {
                            $pesan_error = 'Pendaftaran gagal diproses. Silakan coba kembali.';

                            error_log(
                                'WargaNet registration insert failed, errno=' .
                                $errno
                            );
                        }
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daftar Akun - WargaNet</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/warganet.css">

    <script>
        document.documentElement.classList.add('js');
    </script>
</head>

<body class="wn-auth">

    <div class="login-wrapper d-flex flex-wrap flex-md-nowrap">

        <!-- Panel Kiri -->
        <div class="left-panel col-12 col-md-5">

            <div>
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-activity text-info me-2"></i>WargaNet
                </h5>
            </div>

            <div class="my-5 py-4">

                <i class="bi bi-people text-info fs-3 d-inline-block mb-3 p-2 border border-secondary rounded-3 border-opacity-50"></i>

                <h1 class="fw-bold mb-3" style="font-size: 2.2rem; line-height: 1.2;">
                    Bergabung dengan komunitas kami.
                </h1>

                <p class="text-secondary" style="font-size: 0.95rem;">
                    Dapatkan akses penuh untuk membaca berita terkini,
                    berdiskusi, dan mulai perjalanan Anda sebagai bagian
                    dari warga.
                </p>

            </div>

            <div>
                <small class="text-success text-small">
                    <i class="bi bi-shield-check me-1"></i>
                    Data Anda dijamin kerahasiaannya
                </small>
            </div>

        </div>


        <!-- Panel Kanan -->
        <div class="right-panel col-12 col-md-7">

            <!-- Tab Masuk / Daftar -->
            <div class="nav-toggle">
                <a href="login.php">Masuk</a>
                <a href="register.php" class="active">Daftar akun</a>
            </div>

            <div class="px-md-3">

                <h4 class="fw-normal mb-1">Buat akun baru</h4>

                <p class="text-secondary mb-4 text-small">
                    Lengkapi formulir di bawah ini untuk mendaftar.
                </p>


                <?php if ($pesan_error !== '') : ?>

                    <div class="alert alert-danger py-2 border-danger bg-transparent text-danger text-small">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= e($pesan_error); ?>
                    </div>

                <?php endif; ?>


                <?php if ($pesan_sukses !== '') : ?>

                    <div class="alert alert-success py-2 border-success bg-transparent text-success text-small">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= e($pesan_sukses); ?>
                    </div>

                <?php endif; ?>


                <form action="" method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrf_token()); ?>"
                    >

                    <div class="mb-3">

                        <label class="form-label text-secondary text-small mb-1">
                            Nama Lengkap
                        </label>

                        <div class="input-group">

                            <span class="input-group-text rounded-start-2">
                                <i class="bi bi-person-vcard"></i>
                            </span>

                            <input
                                type="text"
                                class="form-control rounded-end-2"
                                name="nama_lengkap"
                                placeholder="Contoh: Arya Putra"
                                required
                                autocomplete="name"
                                maxlength="100"
                            >

                        </div>
                    </div>


                    <div class="mb-3">

                        <label class="form-label text-secondary text-small mb-1">
                            Username
                        </label>

                        <div class="input-group">

                            <span class="input-group-text rounded-start-2">
                                <i class="bi bi-at"></i>
                            </span>

                            <input
                                type="text"
                                class="form-control rounded-end-2"
                                name="username"
                                placeholder="Gunakan nama tanpa spasi"
                                required
                                autocomplete="username"
                                minlength="3"
                                maxlength="50"
                                pattern="[A-Za-z0-9_.-]+"
                            >

                        </div>
                    </div>


                    <div class="row mb-4">

                        <div class="col-md-6 mb-3 mb-md-0">

                            <label class="form-label text-secondary text-small mb-1">
                                Kata sandi
                            </label>

                            <div class="input-group">

                                <span class="input-group-text rounded-start-2">
                                    <i class="bi bi-lock"></i>
                                </span>

                                <input
                                    type="password"
                                    class="form-control rounded-end-2"
                                    name="password"
                                    placeholder="Minimal 8 karakter"
                                    required
                                    minlength="8"
                                    maxlength="72"
                                    autocomplete="new-password"
                                >

                            </div>
                        </div>


                        <div class="col-md-6">

                            <label class="form-label text-secondary text-small mb-1">
                                Konfirmasi sandi
                            </label>

                            <div class="input-group">

                                <span class="input-group-text rounded-start-2">
                                    <i class="bi bi-check2-circle"></i>
                                </span>

                                <input
                                    type="password"
                                    class="form-control rounded-end-2"
                                    name="konfirmasi_password"
                                    placeholder="Ulangi kata sandi"
                                    required
                                    minlength="8"
                                    maxlength="72"
                                    autocomplete="new-password"
                                >

                            </div>
                        </div>

                    </div>


                    <button
                        type="submit"
                        name="register"
                        class="btn btn-primary w-100 mb-3 mt-2"
                    >
                        Daftar sekarang
                    </button>


                    <div class="text-center mt-4">

                        <small class="text-secondary" style="font-size: 0.7rem;">
                            Dengan mendaftar, Anda menyetujui Ketentuan
                            Layanan dan Kebijakan Privasi WargaNet.
                        </small>

                    </div>

                </form>

            </div>
        </div>

    </div>

    <script src="assets/js/warganet.js"></script>

</body>
</html>
