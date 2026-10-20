<?php
session_start();
require '../koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$pesan_error = "";
$query_kategori = mysqli_query($koneksi, "SELECT * FROM kategori");

if (isset($_POST['simpan'])) {
    $judul = mysqli_real_escape_string($koneksi, $_POST['judul']);
    $id_kategori = (int) $_POST['id_kategori'];
    $isi_artikel = mysqli_real_escape_string($koneksi, $_POST['isi_artikel']);
    $id_user = (int) $_SESSION['id_user'];
    $status = 'terbit';

    $gambar = $_FILES['gambar']['name'];
    $tmp_name = $_FILES['gambar']['tmp_name'];
    $ukuran = $_FILES['gambar']['size'];
    $error = $_FILES['gambar']['error'];

    if ($error === 4) {
        $pesan_error = "Pilih gambar sampul terlebih dahulu!";
    } elseif ($error !== 0) {
        $pesan_error = "Terjadi kesalahan saat mengunggah gambar.";
    } else {
        $ekstensi_valid = ['jpg', 'jpeg', 'png'];
        $ekstensi_gambar = strtolower(pathinfo($gambar, PATHINFO_EXTENSION));

        if (!in_array($ekstensi_gambar, $ekstensi_valid)) {
            $pesan_error = "Gambar harus berformat JPG atau PNG!";
        } elseif ($ukuran > 2000000) {
            $pesan_error = "Ukuran gambar terlalu besar (Maksimal 2MB)!";
        } else {
            $info_gambar = getimagesize($tmp_name);

            if ($info_gambar === false) {
                $pesan_error = "File yang diunggah bukan gambar yang valid!";
            } else {
                $nama_file_baru = uniqid() . '.' . $ekstensi_gambar;
                $tujuan_upload = '../uploads/' . $nama_file_baru;

                if (move_uploaded_file($tmp_name, $tujuan_upload)) {
                    $query_insert = "INSERT INTO artikel
                        (judul, isi_artikel, gambar, id_kategori, id_user, status)
                        VALUES ('$judul', '$isi_artikel', '$nama_file_baru', '$id_kategori', '$id_user', '$status')";

                    if (mysqli_query($koneksi, $query_insert)) {
                        header("Location: index.php");
                        exit;
                    } else {
                        if (file_exists($tujuan_upload)) {
                            unlink($tujuan_upload);
                        }
                        $pesan_error = "Gagal menyimpan data: " . mysqli_error($koneksi);
                    }
                } else {
                    $pesan_error = "Gagal mengunggah gambar sampul.";
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
    <title>Tulis Artikel Baru - WargaNet Panel</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="../assets/css/warganet.css?v=<?= filemtime(__DIR__ . '/../assets/css/warganet.css'); ?>">
    <script>document.documentElement.classList.add('js');</script>
</head>

<body class="wn-panel">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 border-bottom border-secondary">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">WargaNet Panel</a>
            <span class="navbar-text">
                Halo, <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin'); ?>!
            </span>
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

            <!-- Konten Utama -->
            <div class="col-md-10 px-4">

                <h3 class="mb-4">Tulis Artikel Baru</h3>

                <?php if ($pesan_error !== "") : ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($pesan_error); ?></div>
                <?php endif; ?>

                <div class="card card-dark shadow-lg rounded-3">
                    <div class="card-body p-4">

                        <form action="" method="POST" enctype="multipart/form-data">

                            <div class="mb-3">
                                <label class="form-label fw-bold">Judul Artikel</label>
                                <input type="text" name="judul" class="form-control bg-dark text-white border-secondary"
                                       required placeholder="Masukkan judul berita..."
                                       value="<?= htmlspecialchars($_POST['judul'] ?? ''); ?>">
                            </div>

                            <div class="row mb-3">

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Kategori Berita</label>
                                    <select name="id_kategori" class="form-select bg-dark text-white border-secondary" required>
                                        <option value="">-- Pilih Kategori --</option>
                                        <?php if (mysqli_num_rows($query_kategori) > 0) : ?>
                                            <?php while ($kat = mysqli_fetch_assoc($query_kategori)) : ?>
                                                <option value="<?= (int) $kat['id_kategori']; ?>"
                                                    <?= (($_POST['id_kategori'] ?? '') == $kat['id_kategori']) ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars($kat['nama_kategori']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php else : ?>
                                            <option value="" disabled>Belum ada kategori tersedia</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- Gambar Sampul -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Gambar Sampul</label>
                                    <input type="file" name="gambar" class="form-control"
                                           accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Isi Artikel</label>
                                <textarea name="isi_artikel" class="form-control bg-dark text-white border-secondary"
                                          rows="10" required
                                          placeholder="Ketik isi berita lengkap di sini..."><?= htmlspecialchars($_POST['isi_artikel'] ?? ''); ?></textarea>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-outline-secondary px-4">Batal</a>
                                <button type="submit" name="simpan" class="btn btn-primary px-5">Simpan &amp; Publikasikan</button>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../assets/js/warganet.js?v=<?= filemtime(__DIR__ . '/../assets/js/warganet.js'); ?>"></script>

</body>
</html>