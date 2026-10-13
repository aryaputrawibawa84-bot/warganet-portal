<?php
session_start();
require '../koneksi.php';

// Proteksi akses khusus penulis
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'penulis') {
    header("Location: ../login.php");
    exit;
}

$pesan_error = "";
$id_artikel = mysqli_real_escape_string($koneksi, $_GET['id']);
$id_user_aktif = $_SESSION['id_user'];

// Cek apakah artikel ini benar-benar milik penulis yang sedang login
$query_artikel = mysqli_query($koneksi, "SELECT * FROM artikel WHERE id_artikel = '$id_artikel' AND id_user = '$id_user_aktif'");

if (mysqli_num_rows($query_artikel) == 0) {
    // Jika mencoba mengedit artikel orang lain, tendang kembali ke index
    header("Location: index.php");
    exit;
}

$data = mysqli_fetch_assoc($query_artikel);
$query_kategori = mysqli_query($koneksi, "SELECT * FROM kategori");

if (isset($_POST['simpan'])) {
    $judul = mysqli_real_escape_string($koneksi, $_POST['judul']);
    $id_kategori = $_POST['id_kategori'];
    $isi_artikel = mysqli_real_escape_string($koneksi, $_POST['isi_artikel']);
    // Saat diedit, status otomatis dikembalikan ke 'pending' agar direviu ulang oleh admin
    $status = 'pending'; 
    
    $gambar_baru = $_FILES['gambar']['name'];
    $tmp_name = $_FILES['gambar']['tmp_name'];
    $error = $_FILES['gambar']['error'];

    if ($error === 4) {
        $query_update = "UPDATE artikel SET 
                         judul = '$judul', 
                         isi_artikel = '$isi_artikel', 
                         id_kategori = '$id_kategori', 
                         status = '$status' 
                         WHERE id_artikel = '$id_artikel' AND id_user = '$id_user_aktif'";
                         
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
                             WHERE id_artikel = '$id_artikel' AND id_user = '$id_user_aktif'";
                             
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
    <meta charset="UTF-8">
    <title>Edit Karya - WargaNet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #0F172A; } 
        .card-dark { background-color: #1E293B; border: none; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 border-bottom border-secondary">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-info" href="#">WargaNet | Ruang Penulis</a>
            <span class="navbar-text">Halo, <?= $_SESSION['nama_lengkap']; ?>!</span>
        </div>
    </nav>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2">
                <div class="list-group list-group-flush border-end border-secondary h-100">
                    <a href="index.php" class="list-group-item list-group-item-action bg-secondary text-white">Artikel Saya</a>
                    <a href="../index.php" class="list-group-item list-group-item-action bg-transparent text-light" target="_blank">Lihat Web Publik</a>
                    <a href="../logout.php" class="list-group-item list-group-item-action text-danger bg-transparent mt-5">Logout</a>
                </div>
            </div>
            <div class="col-md-9 px-4">
                <h3 class="mb-4">Perbarui Tulisan</h3>
                
                <?php if ($pesan_error != "") : ?>
                    <div class="alert alert-danger"><?= $pesan_error; ?></div>
                <?php endif; ?>
                <div class="alert alert-warning py-2 text-dark fw-bold">
                    Mengedit artikel akan mengubah statusnya kembali menjadi "Pending" untuk direviu ulang.
                </div>

                <div class="card card-dark shadow-lg rounded-3 mb-5">
                    <div class="card-body p-4">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Judul Berita</label>
                                <input type="text" name="judul" class="form-control bg-dark text-white border-secondary" value="<?= $data['judul']; ?>" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Kategori</label>
                                    <select name="id_kategori" class="form-select bg-dark text-white border-secondary" required>
                                        <?php while($kat = mysqli_fetch_assoc($query_kategori)) : ?>
                                            <option value="<?= $kat['id_kategori']; ?>" <?= ($kat['id_kategori'] == $data['id_kategori']) ? 'selected' : ''; ?>>
                                                <?= $kat['nama_kategori']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Ganti Sampul (Opsional)</label>
                                    <input type="file" name="gambar" class="form-control bg-dark text-white border-secondary" accept="image/png, image/jpeg">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Isi Tulisan</label>
                                <textarea name="isi_artikel" class="form-control bg-dark text-white border-secondary" rows="10" required><?= $data['isi_artikel']; ?></textarea>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-outline-secondary px-4">Batal</a>
                                <button type="submit" name="simpan" class="btn btn-warning px-5 text-dark fw-bold">Update & Kirim Ulang</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>