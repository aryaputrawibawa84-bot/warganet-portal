<?php
session_start();
require '../koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$pesan_error = "";

$id_artikel = $_GET['id'];
$query_artikel = mysqli_query($koneksi, "SELECT * FROM artikel WHERE id_artikel = '$id_artikel'");
$data = mysqli_fetch_assoc($query_artikel);

$query_kategori = mysqli_query($koneksi, "SELECT * FROM kategori");

if (isset($_POST['simpan'])) {
    $judul = mysqli_real_escape_string($koneksi, $_POST['judul']);
    $id_kategori = $_POST['id_kategori'];
    $isi_artikel = mysqli_real_escape_string($koneksi, $_POST['isi_artikel']);
    $status = $_POST['status']; 
    
    $gambar_baru = $_FILES['gambar']['name'];
    $tmp_name = $_FILES['gambar']['tmp_name'];
    $error = $_FILES['gambar']['error'];

    if ($error === 4) {
        $nama_file_gambar = $data['gambar']; 
        
        $query_update = "UPDATE artikel SET 
                         judul = '$judul', 
                         isi_artikel = '$isi_artikel', 
                         id_kategori = '$id_kategori', 
                         status = '$status' 
                         WHERE id_artikel = '$id_artikel'";
                         
        mysqli_query($koneksi, $query_update);
        header("Location: index.php");
        exit;

    } else {
        $ekstensi_valid = ['jpg', 'jpeg', 'png'];
        $ekstensi_gambar = strtolower(pathinfo($gambar_baru, PATHINFO_EXTENSION));
        $ukuran = $_FILES['gambar']['size'];

        if (!in_array($ekstensi_gambar, $ekstensi_valid)) {
            $pesan_error = "Format gambar harus JPG/PNG!";
        } elseif ($ukuran > 2000000) {
            $pesan_error = "Ukuran gambar maksimal 2MB!";
        } else {
            if (file_exists('../uploads/' . $data['gambar'])) {
                unlink('../uploads/' . $data['gambar']);
            }

            $nama_file_baru = uniqid() . '.' . $ekstensi_gambar;
            move_uploaded_file($tmp_name, '../uploads/' . $nama_file_baru);

            $query_update = "UPDATE artikel SET 
                             judul = '$judul', 
                             isi_artikel = '$isi_artikel', 
                             gambar = '$nama_file_baru',
                             id_kategori = '$id_kategori', 
                             status = '$status' 
                             WHERE id_artikel = '$id_artikel'";
                             
            mysqli_query($koneksi, $query_update);
            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
    
<head>
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Artikel - WargaNet Panel</title>
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
            <span class="navbar-text">Halo, <?= $_SESSION['nama_lengkap']; ?>!</span>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Kiri -->
            <div class="col-md-2">
                <div class="list-group list-group-flush border-end border-secondary h-100">
                    <a href="index.php" class="list-group-item list-group-item-action bg-secondary text-white">Kelola Artikel</a>
                    <a href="kategori.php" class="list-group-item list-group-item-action bg-transparent text-light">Kelola Kategori</a>
                    <a href="pengguna.php" class="list-group-item list-group-item-action bg-transparent text-light">Kelola Pengguna</a>
                    <a href="../logout.php" class="list-group-item list-group-item-action text-danger bg-transparent mt-5">Logout</a>
                </div>
            </div>

            <div class="col-md-9 px-4">
                <h3 class="mb-4">Edit Artikel</h3>
                
                <?php if ($pesan_error != "") : ?>
                    <div class="alert alert-danger"><?= $pesan_error; ?></div>
                <?php endif; ?>

                <div class="card card-dark shadow-lg rounded-3 mb-5">
                    <div class="card-body p-4">
                        <form action="" method="POST" enctype="multipart/form-data">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Judul Artikel</label>
                                <input type="text" name="judul" class="form-control bg-dark text-white border-secondary" value="<?= $data['judul']; ?>" required>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Kategori</label>
                                    <select name="id_kategori" class="form-select bg-dark text-white border-secondary" required>
                                        <?php while($kat = mysqli_fetch_assoc($query_kategori)) : ?>
                                            <option value="<?= $kat['id_kategori']; ?>" <?= ($kat['id_kategori'] == $data['id_kategori']) ? 'selected' : ''; ?>>
                                                <?= $kat['nama_kategori']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Status Artikel</label>
                                    <select name="status" class="form-select bg-dark text-white border-secondary" required>
                                        <option value="terbit" <?= ($data['status'] == 'terbit') ? 'selected' : ''; ?>>Terbit</option>
                                        <option value="pending" <?= ($data['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="draf" <?= ($data['status'] == 'draf') ? 'selected' : ''; ?>>Draf</option>
                                        <option value="ditolak" <?= ($data['status'] == 'ditolak') ? 'selected' : ''; ?>>Ditolak</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Ganti Sampul (Opsional)</label>
                                    <input type="file" name="gambar" class="form-control bg-dark text-white border-secondary" accept="image/png, image/jpeg">
                                    <small class="text-secondary">Abaikan jika tidak ingin diganti.</small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Isi Artikel</label>
                                <textarea name="isi_artikel" class="form-control bg-dark text-white border-secondary" rows="10" required><?= $data['isi_artikel']; ?></textarea>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-outline-secondary px-4">Batal</a>
                                <button type="submit" name="simpan" class="btn btn-warning px-5 fw-bold text-dark">Update Artikel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/warganet.js"></script>
</body>
</html>