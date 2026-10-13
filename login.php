<?php
session_start();
require 'koneksi.php';

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
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = $_POST['password'];

    $result = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username'");
    
    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        
        if (password_verify($password, $row['password'])) {
            $_SESSION['id_user'] = $row['id_user'];
            $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
            $_SESSION['role'] = $row['role'];
            
            if ($row['role'] == 'admin') {
                header("Location: admin/index.php");
            } elseif ($row['role'] == 'penulis') {
                header("Location: penulis/index.php");
            } else {
                header("Location: index.php"); 
            }
            exit;
        }
    }
    $error = true;
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - WargaNet</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { 
            background-color: #0B1120; /* Latar belakang paling gelap */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-wrapper {
            background-color: #172133; /* Warna card form */
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 1000px;
            width: 100%;
            margin: 20px;
        }

        .left-panel {
            background: linear-gradient(145deg, #1e3a8a 0%, #0f172a 100%);
            padding: 50px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .right-panel {
            padding: 50px;
        }

        /* Toggle Button Masuk / Daftar */
        .nav-toggle {
            background-color: #0B1120;
            border-radius: 8px;
            padding: 4px;
            display: flex;
            margin-bottom: 40px;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }
        
        .nav-toggle a {
            padding: 8px 30px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .nav-toggle a.active {
            background-color: #1e293b;
            color: white;
        }

        /* Input Kustom */
        .input-group-text {
            background-color: #0B1120;
            border: 1px solid #334155;
            border-right: none;
            color: #64748b;
        }

        .form-control {
            background-color: #0B1120;
            border: 1px solid #334155;
            border-left: none;
            color: white;
            padding: 12px 12px 12px 0;
        }

        .form-control:focus {
            background-color: #0B1120;
            color: white;
            box-shadow: none;
            border-color: #334155;
        }

        .input-group:focus-within .input-group-text,
        .input-group:focus-within .form-control {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .btn-primary {
            background-color: #3b82f6;
            border: none;
            padding: 12px;
            font-weight: 500;
            border-radius: 8px;
        }
        
        .btn-primary:hover {
            background-color: #2563eb;
        }

        .text-small {
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

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
                    <div class="mb-3">
                        <label class="form-label text-secondary text-small mb-1">Username</label>
                        <div class="input-group">
                            <span class="input-group-text rounded-start-2"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control rounded-end-2" name="username" placeholder="Masukkan username Anda" required autocomplete="off">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary text-small mb-1">Kata sandi</label>
                        <div class="input-group">
                            <span class="input-group-text rounded-start-2"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control rounded-end-2" name="password" placeholder="••••••••••••" required>
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

</body>
</html>