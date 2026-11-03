<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $https,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/koneksi.php';

// Jika sudah login, arahkan sesuai role
if (isset($_SESSION['id_user'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/index.php");
    } elseif ($_SESSION['role'] == 'penulis') {
        header("Location: penulis/index.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = false;

if (isset($_POST['login'])) {
    require_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username !== '' && $password !== '') {
        $stmt = mysqli_prepare($koneksi, 'SELECT id_user, nama_lengkap, username, password, role FROM users WHERE username = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($row && password_verify($password, $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['id_user'] = (int)$row['id_user'];
                $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                if ($row['role'] === 'admin') {
                    header('Location: admin/index.php');
                } elseif ($row['role'] === 'penulis') {
                    header('Location: penulis/index.php');
                } else {
                    // Pembaca: kembali ke artikel yang tadi dibuka (diset oleh detail.php)
                    $tujuan = $_SESSION['after_login'] ?? 'index.php';
                    unset($_SESSION['after_login']);
                    header('Location: ' . $tujuan);
                }
                exit;
            }
        } else {
            error_log('WargaNet could not prepare login query.');
        }
    }
    $error = true;
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
    
<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - WargaNet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/warganet.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/warganet.css'); ?>">
    <script>document.documentElement.classList.add('js')</script>
</head>
<body class="wn-auth">

    <div class="login-wrapper d-flex flex-wrap flex-md-nowrap">
        
        <!-- Panel Kiri (Desain Branding) -->
        <div class="left-panel col-12 col-md-5">
            <div>
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-activity text-info me-2"></i>WargaNet
                </h5>
            </div>
            
            <div class="my-5 py-4">
                <i class="bi bi-broadcast text-info fs-3 d-inline-block mb-3 p-2 border border-secondary rounded-3 border-opacity-50"></i>
                <h1 class="fw-bold mb-3" style="font-size: 2.5rem; line-height: 1.2;">
                    Ruang redaksi,<br>dalam satu<br>denyut.
                </h1>
                <p class="text-secondary" style="font-size: 0.95rem;">
                    Kelola berita, kolaborasi dengan editor, dan terbitkan cerita yang penting bagi pembaca.
                </p>
            </div>

            <div>
                <small class="text-success text-small">
                    <i class="bi bi-shield-check me-1"></i> Akses terenkripsi dan terlindungi
                </small>
            </div>
        </div>

        <!-- Panel Kanan (Form Login) -->
        <div class="right-panel col-12 col-md-7">
            
            <!-- Tab Masuk / Daftar -->
            <div class="nav-toggle">
                <a href="login.php" class="active">Masuk</a>
                <a href="register.php">Daftar akun</a>
            </div>

            <div class="px-md-4">
                <h3 class="fw-normal mb-1">Selamat datang kembali</h3>
                <p class="text-secondary mb-4 text-small">Masuk untuk melanjutkan pekerjaan di WargaNet.</p>

                <?php if ($error) : ?>
                    <div class="alert alert-danger py-2 border-danger bg-transparent text-danger">
                        <i class="bi bi-exclamation-circle me-2"></i>Username atau kata sandi salah!
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label text-secondary text-small mb-1">Username</label>
                        <div class="input-group">
                            <span class="input-group-text rounded-start-2"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control rounded-end-2" name="username" placeholder="Masukkan username Anda" required autocomplete="username" maxlength="50">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary text-small mb-1">Kata sandi</label>
                        <div class="input-group">
                            <span class="input-group-text rounded-start-2"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control rounded-end-2" name="password" placeholder="••••••••••••" required autocomplete="current-password">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4 text-small">
                        <div class="form-check">
                            <input class="form-check-input bg-transparent border-secondary" type="checkbox" id="ingatSaya">
                            <label class="form-check-label text-secondary" for="ingatSaya">
                                Ingat saya
                            </label>
                        </div>
                        <a href="#" class="text-primary text-decoration-none">Lupa kata sandi?</a>
                    </div>

                    <button type="submit" name="login" class="btn btn-primary w-100 mb-4">Masuk ke dashboard</button>
                    
                    <div class="text-center text-small">
                        <span class="text-secondary">Belum memiliki akun?</span> <a href="register.php" class="text-primary text-decoration-none fw-bold">Daftar sebagai kontributor</a>
                    </div>

                    <div class="text-center mt-5">
                        <small class="text-secondary" style="font-size: 0.7rem;">
                            Dengan masuk, Anda menyetujui Ketentuan Layanan dan Kebijakan Privasi WargaNet.
                        </small>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script src="assets/js/warganet.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/warganet.js'); ?>"></script>
</body>
</html>