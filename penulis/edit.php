<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $https,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../koneksi.php';

// Proteksi akses khusus penulis
if (!isset($_SESSION['id_user'], $_SESSION['role']) || $_SESSION['role'] !== 'penulis') {
    header('Location: ../login.php');
    exit;
}

$id_user_aktif = (int) $_SESSION['id_user'];

// Validasi ID artikel dari URL
$id_artikel = valid_positive_int($_GET['id'] ?? null);
if ($id_artikel === null) {
    header('Location: index.php');
    exit;
}

// Hanya ambil artikel kalau benar-benar milik penulis yang sedang login
$stmt = mysqli_prepare($koneksi, 'SELECT * FROM artikel WHERE id_artikel = ? AND id_user = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $id_artikel, $id_user_aktif);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);
$data = $hasil ? mysqli_fetch_assoc($hasil) : null;
mysqli_stmt_close($stmt);

if (!$data) {
    // Artikel tidak ada, atau milik orang lain
    header('Location: index.php');
    exit;
}

$upload_dir = __DIR__ . '/../uploads';
$pesan_error = '';

// Daftar kategori (dipakai untuk dropdown sekaligus validasi)
$daftar_kategori = [];
$query_kategori = mysqli_query($koneksi, 'SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori');
if ($query_kategori) {
    while ($k = mysqli_fetch_assoc($query_kategori)) {
        $daftar_kategori[(int) $k['id_kategori']] = $k['nama_kategori'];
    }
}

// Nilai awal form = data di database
$form = [
    'judul'       => (string) $data['judul'],
    'isi_artikel' => (string) $data['isi_artikel'],
    'id_kategori' => (int) $data['id_kategori'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    require_csrf();

    $form['judul']       = trim((string) ($_POST['judul'] ?? ''));
    $form['isi_artikel'] = trim((string) ($_POST['isi_artikel'] ?? ''));
    $form['id_kategori'] = (int) (valid_positive_int($_POST['id_kategori'] ?? null) ?? 0);

    // Saat diedit, status otomatis dikembalikan ke 'pending' agar direviu ulang oleh admin
    $status = 'pending';

    if ($form['judul'] === '' || mb_strlen($form['judul']) > 200 || $form['isi_artikel'] === '') {
        $pesan_error = 'Judul wajib diisi (maksimal 200 karakter) dan isi tulisan tidak boleh kosong.';
    } elseif (!isset($daftar_kategori[$form['id_kategori']])) {
        $pesan_error = 'Pilih kategori yang valid.';
    } else {
        $gambar_final = $data['gambar'];
        $gambar_baru = null;

        // Hanya proses upload kalau penulis memilih file baru
        if (($_FILES['gambar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = save_uploaded_image($_FILES['gambar'], $upload_dir);
            if ($upload['error'] !== null) {
                $pesan_error = $upload['error'];
            } else {
                $gambar_baru = $upload['filename'];
                $gambar_final = $gambar_baru;
            }
        }

        if ($pesan_error === '') {
            // Kepemilikan dicek lagi di UPDATE (AND id_user = ?) sebagai lapis pengaman kedua
            $upd = mysqli_prepare($koneksi, 'UPDATE artikel SET judul = ?, isi_artikel = ?, gambar = ?, id_kategori = ?, status = ? WHERE id_artikel = ? AND id_user = ?');
            $berhasil = false;
            if ($upd) {
                mysqli_stmt_bind_param($upd, 'sssisii', $form['judul'], $form['isi_artikel'], $gambar_final, $form['id_kategori'], $status, $id_artikel, $id_user_aktif);
                $berhasil = mysqli_stmt_execute($upd);
                if (!$berhasil) {
                    error_log('WargaNet penulis article update failed: ' . mysqli_stmt_error($upd));
                }
                mysqli_stmt_close($upd);
            } else {
                error_log('WargaNet penulis article update prepare failed: ' . mysqli_error($koneksi));
            }

            if ($berhasil) {
                // Gambar lama baru dihapus setelah update benar-benar berhasil
                if ($gambar_baru !== null) {
                    delete_uploaded_image($data['gambar'], $upload_dir);
                }
                header('Location: index.php');
                exit;
            }

            // Update gagal: buang gambar baru agar tidak jadi file yatim
            if ($gambar_baru !== null) {
                delete_uploaded_image($gambar_baru, $upload_dir);
            }
            $pesan_error = 'Tulisan gagal diperbarui. Silakan coba kembali.';
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
    <title>Edit Karya - WargaNet</title>
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
            <div class="col-md-2">
                <div class="list-group list-group-flush border-end border-secondary h-100">
                    <a href="index.php" class="list-group-item list-group-item-action bg-secondary text-white">Artikel Saya</a>
                    <a href="../index.php" class="list-group-item list-group-item-action bg-transparent text-light" target="_blank">Lihat Web Publik</a>
                    <a href="../logout.php" class="list-group-item list-group-item-action text-danger bg-transparent mt-5">Logout</a>
                </div>
            </div>
            <div class="col-md-10 px-4">
                <h3 class="mb-4">Perbarui Tulisan</h3>

                <?php if ($pesan_error !== '') : ?>
                    <div class="alert alert-danger"><?= e($pesan_error); ?></div>
                <?php endif; ?>
                <div class="alert alert-warning bg-transparent border-warning text-warning py-2 fw-bold">
                    <i class="bi bi-info-circle me-2"></i>Mengedit artikel akan mengubah statusnya kembali menjadi "Pending" untuk direviu ulang.
                </div>

                <div class="card card-dark shadow-lg rounded-3 mb-5">
                    <div class="card-body p-4">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                            <div class="mb-3">
                                <label class="form-label fw-bold">Judul Berita</label>
                                <input type="text" name="judul" class="form-control" maxlength="200" required
                                       value="<?= e($form['judul']); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Kategori</label>
                                <select name="id_kategori" class="form-select" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($daftar_kategori as $id_k => $nama_k) : ?>
                                        <option value="<?= (int) $id_k; ?>" <?= ($id_k === $form['id_kategori']) ? 'selected' : ''; ?>>
                                            <?= e($nama_k); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Sampul Saat Ini</label>
                                    <?php if (!empty($data['gambar'])) : ?>
                                        <img src="../uploads/<?= rawurlencode(basename((string) $data['gambar'])); ?>"
                                             alt="Sampul saat ini" class="w-100 rounded-3 border border-secondary"
                                             style="aspect-ratio: 16 / 9; object-fit: cover;">
                                    <?php else : ?>
                                        <div class="text-secondary small">Belum ada gambar sampul.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Ganti Sampul (Opsional)</label>
                                    <input type="file" name="gambar" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Isi Tulisan</label>
                                <textarea name="isi_artikel" class="form-control" rows="10" required><?= e($form['isi_artikel']); ?></textarea>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-outline-secondary px-4">Batal</a>
                                <button type="submit" name="simpan" class="btn btn-warning px-5 text-dark fw-bold">Update &amp; Kirim Ulang</button>
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