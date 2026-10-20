<?php
session_start();
require 'koneksi.php';

// Jika sudah login, tidak boleh buka halaman register
if (isset($_SESSION['id_user'])) {
    header("Location: index.php");
    exit;
}

$pesan_error = "";
$pesan_sukses = "";

if (isset($_POST['register'])) {
    $nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']);
    $konfirmasi_password = mysqli_real_escape_string($koneksi, $_POST['konfirmasi_password']);

    // Cek apakah username sudah dipakai
    $cek_username = mysqli_query($koneksi, "SELECT username FROM users WHERE username = '$username'");

    if (mysqli_num_rows($cek_username) > 0) {
        $pesan_error = "Username sudah terdaftar, silakan gunakan yang lain!";
    } elseif ($password !== $konfirmasi_password) {
        $pesan_error = "Konfirmasi kata sandi tidak cocok!";
    } else {
        // Enkripsi password sebelum disimpan
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert data (role otomatis menjadi 'pembaca' sesuai setelan default database)
        $query = "INSERT INTO users (nama_lengkap, username, password) VALUES ('$nama_lengkap', '$username', '$password_hashed')";
        
        if (mysqli_query($koneksi, $query)) {
            $pesan_sukses = "Pendaftaran berhasil! Silakan masuk dengan akun baru Anda.";
        } else {
            $pesan_error = "Gagal mendaftar: " . mysqli_error($koneksi);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
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
    <script>document.documentElement.classList.add('js')</script>
</head>
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
                    Dapatkan akses penuh untuk membaca berita terkini, berdiskusi, dan mulai perjalanan Anda sebagai bagian dari warga.
                </p>
            </div>

            <div>
                <small class="text-success text-small">
                    <i class="bi bi-shield-check me-1"></i> Data Anda dijamin kerahasiaannya
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
                <p class="text-secondary mb-4 text-small">Lengkapi formulir di bawah ini untuk mendaftar.</p>

                <?php if ($pesan_error != "") : ?>
                    <div class="alert alert-danger py-2 border-danger bg-transparent text-danger text-small">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= $pesan_error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($pesan_sukses != "") : ?>
                    <div class="alert alert-success py-2 border-success bg-transparent text-success text-small">
                        <i class="bi bi-check-circle me-2"></i><?= $pesan_sukses; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="mb-3">
                        <label class="form-label text-secondary text-small mb-1">Nama Lengkap</label>
                        <div class="input-group">
                            <span class="input-group-text rounded-start-2"><i class="bi bi-person-vcard"></i></span>
                            <input type="text" class="form-control rounded-end-2" name="nama_lengkap" placeholder="Contoh: Arya Putra" required autocomplete="off">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary text-small mb-1">Username</label>
                        <div class="input-group">
                            <span class="input-group-text rounded-start-2"><i class="bi bi-at"></i></span>
                            <input type="text" class="form-control rounded-end-2" name="username" placeholder="Gunakan nama tanpa spasi" required autocomplete="off">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label text-secondary text-small mb-1">Kata sandi</label>
                            <div class="input-group">
                                <span class="input-group-text rounded-start-2"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control rounded-end-2" name="password" placeholder="••••••••" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary text-small mb-1">Konfirmasi sandi</label>
                            <div class="input-group">
                                <span class="input-group-text rounded-start-2"><i class="bi bi-check2-circle"></i></span>
                                <input type="password" class="form-control rounded-end-2" name="konfirmasi_password" placeholder="••••••••" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="register" class="btn btn-primary w-100 mb-3 mt-2">Daftar sekarang</button>
                    
                    <div class="text-center mt-4">
                        <small class="text-secondary" style="font-size: 0.7rem;">
                            Dengan mendaftar, Anda menyetujui Ketentuan Layanan dan Kebijakan Privasi WargaNet.
                        </small>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script src="assets/js/warganet.js"></script>
</body>
</html>