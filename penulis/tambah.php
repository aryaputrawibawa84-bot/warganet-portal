<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $https,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../koneksi.php';

// Proteksi: hanya akun berperan 'penulis' yang boleh membuka halaman ini
if (!isset($_SESSION['id_user'], $_SESSION['role']) || $_SESSION['role'] !== 'penulis') {
    header('Location: ../login.php');
    exit;
}

$pesan_error = '';
$judul_lama = '';
$isi_lama = '';
$kategori_lama = null;

$query_kategori = mysqli_query($koneksi, 'SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori');
if (!$query_kategori) {
    error_log('WargaNet category query failed: ' . mysqli_error($koneksi));
    $pesan_error = 'Kategori belum dapat dimuat. Silakan coba kembali.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    require_csrf();
    $judul = trim((string)($_POST['judul'] ?? ''));
    $isi_artikel = trim((string)($_POST['isi_artikel'] ?? ''));
    $id_kategori = valid_positive_int($_POST['id_kategori'] ?? null);
    $id_user = (int)$_SESSION['id_user'];

    // Simpan isian supaya tidak hilang kalau ada error
    $judul_lama = $judul;
    $isi_lama = $isi_artikel;
    $kategori_lama = $id_kategori;

    if ($judul === '' || mb_strlen($judul) > 200 || $isi_artikel === '') {
        $pesan_error = 'Judul wajib diisi (maksimal 200 karakter) dan isi artikel tidak boleh kosong.';
    } elseif ($id_kategori === null) {
        $pesan_error = 'Pilih kategori yang valid.';
    } else {
        $check = mysqli_prepare($koneksi, 'SELECT id_kategori FROM kategori WHERE id_kategori = ?');
        mysqli_stmt_bind_param($check, 'i', $id_kategori);
        mysqli_stmt_execute($check);
        $categoryExists = mysqli_stmt_get_result($check);
        $validCategory = $categoryExists && mysqli_num_rows($categoryExists) === 1;
        mysqli_stmt_close($check);

        if (!$validCategory) {
            $pesan_error = 'Kategori yang dipilih tidak ditemukan.';
        } else {
            $upload = save_uploaded_image($_FILES['gambar'] ?? [], __DIR__ . '/../uploads');
            if ($upload['error'] !== null) {
                $pesan_error = $upload['error'];
            } else {
                $gambar = $upload['filename'];
                $status = 'pending'; // artikel penulis menunggu persetujuan admin
                $stmt = mysqli_prepare($koneksi, 'INSERT INTO artikel (judul, isi_artikel, gambar, id_kategori, id_user, status) VALUES (?, ?, ?, ?, ?, ?)');
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'sssiis', $judul, $isi_artikel, $gambar, $id_kategori, $id_user, $status);
                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        header('Location: index.php');
                        exit;
                    }
                    error_log('WargaNet penulis article insert failed: ' . mysqli_stmt_error($stmt));
                    mysqli_stmt_close($stmt);
                } else {
                    error_log('WargaNet penulis article prepare failed: ' . mysqli_error($koneksi));
                }
                @unlink(__DIR__ . '/../uploads/' . $gambar);
                $pesan_error = 'Artikel gagal disimpan. Silakan periksa data dan coba kembali.';
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
    <title>Tulis Berita Baru - Ruang Penulis WargaNet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/warganet.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/warganet.css'); ?>">
    <script>document.documentElement.classList.add('js')</script>
</head>
<body class="wn-panel">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 border-bottom border-secondary">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-info" href="index.php">WargaNet | Ruang Penulis</a>
            <span class="navbar-text">Halo, <?= e($_SESSION['nama_lengkap'] ?? ''); ?>!</span>
        </div>
    </nav>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Kiri -->
            <div class="col-md-2">
                <div class="list-group list-group-flush border-end border-secondary h-100">
                    <a href="index.php" class="list-group-item list-group-item-action bg-secondary text-white">Artikel Saya</a>
                    <a href="../index.php" class="list-group-item list-group-item-action bg-transparent text-light" target="_blank">Lihat Web Publik</a>
                    <a href="../logout.php" class="list-group-item list-group-item-action text-danger bg-transparent mt-5">Logout</a>
                </div>
            </div>

            <div class="col-md-10 px-4">
                <h3 class="mb-3">Tulis Berita Baru</h3>

                <div class="alert alert-info bg-transparent border-info text-info py-2 mb-4">
                    <strong>Catatan:</strong> Artikel Anda akan berstatus <em>Pending</em> hingga disetujui oleh Administrator.
                </div>

                <?php if ($pesan_error !== '') : ?>
                    <div class="alert alert-danger"><?= e($pesan_error); ?></div>
                <?php endif; ?>

                <div class="card card-dark shadow-lg rounded-3">
                    <div class="card-body p-4">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                            <div class="mb-3">
                                <label class="form-label fw-bold">Judul Artikel</label>
                                <input type="text" name="judul" class="form-control" maxlength="200" required
                                       placeholder="Masukkan judul berita..." value="<?= e($judul_lama); ?>">
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Kategori Berita</label>
                                    <select name="id_kategori" class="form-select" required>
                                        <option value="">-- Pilih Kategori --</option>
                                        <?php if ($query_kategori && mysqli_num_rows($query_kategori) > 0) : ?>
                                            <?php while ($kat = mysqli_fetch_assoc($query_kategori)) : ?>
                                                <option value="<?= (int)$kat['id_kategori']; ?>"
                                                    <?= ($kategori_lama === (int)$kat['id_kategori']) ? 'selected' : ''; ?>>
                                                    <?= e($kat['nama_kategori']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php else : ?>
                                            <option value="" disabled>Belum ada kategori tersedia</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Gambar Sampul</label>
                                    <input type="file" name="gambar" class="form-control"
                                           accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Isi Artikel</label>
                                <textarea name="isi_artikel" class="form-control" rows="10" required
                                          placeholder="Ketik isi berita lengkap di sini..."><?= e($isi_lama); ?></textarea>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-outline-secondary px-4">Batal</a>
                                <button type="submit" name="simpan" class="btn btn-primary px-5">Kirim untuk Ditinjau</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/warganet.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/warganet.js'); ?>"></script>
</body>
</html>