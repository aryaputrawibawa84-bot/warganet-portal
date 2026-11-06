
<?php
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

// Validasi hak akses admin
$id_user = valid_positive_int($_SESSION['id_user'] ?? null);

if ($id_user === null || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pesan_error = '';
$judul_form = '';
$isi_form = '';
$id_kategori_form = '';

// Ambil daftar kategori
$query_kategori = mysqli_query(
    $koneksi,
    'SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori'
);

$kategori_tersedia = $query_kategori && mysqli_num_rows($query_kategori) > 0;

if (!$query_kategori) {
    error_log('WargaNet: gagal mengambil daftar kategori: ' . mysqli_error($koneksi));
    $pesan_error = 'Kategori belum dapat dimuat. Silakan coba kembali.';
}

// Proses penyimpanan artikel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $judul = trim((string)($_POST['judul'] ?? ''));
    $isi_artikel = trim((string)($_POST['isi_artikel'] ?? ''));
    $id_kategori = valid_positive_int($_POST['id_kategori'] ?? null);

    $judul_form = $judul;
    $isi_form = $isi_artikel;
    $id_kategori_form = (string)($_POST['id_kategori'] ?? '');

    $panjang_judul = function_exists('mb_strlen')
        ? mb_strlen($judul, 'UTF-8')
        : strlen($judul);

    if ($judul === '' || $panjang_judul > 200 || $isi_artikel === '') {
        $pesan_error = 'Judul wajib diisi (maksimal 200 karakter) dan isi artikel tidak boleh kosong.';
    } elseif ($id_kategori === null) {
        $pesan_error = 'Pilih kategori yang valid.';
    } elseif (!$query_kategori) {
        $pesan_error = 'Kategori belum dapat dimuat. Silakan coba kembali.';
    } else {
        // Pastikan kategori benar-benar tersedia di database
        $check = mysqli_prepare(
            $koneksi,
            'SELECT id_kategori FROM kategori WHERE id_kategori = ? LIMIT 1'
        );

        if (!$check) {
            error_log('WargaNet: gagal menyiapkan pemeriksaan kategori.');
            $pesan_error = 'Terjadi kesalahan saat memeriksa kategori.';
        } else {
            mysqli_stmt_bind_param($check, 'i', $id_kategori);

            if (!mysqli_stmt_execute($check)) {
                error_log('WargaNet: gagal menjalankan pemeriksaan kategori.');
                $pesan_error = 'Kategori gagal diperiksa. Silakan coba kembali.';
                mysqli_stmt_close($check);
            } else {
                mysqli_stmt_store_result($check);
                $valid_category = mysqli_stmt_num_rows($check) === 1;
                mysqli_stmt_close($check);

                if (!$valid_category) {
                    $pesan_error = 'Kategori yang dipilih tidak ditemukan.';
                } else {
                    // Upload gambar sampul
                    $upload_dir = __DIR__ . '/../uploads';

                    $upload = save_uploaded_image(
                        $_FILES['gambar'] ?? [],
                        $upload_dir
                    );

                    if ($upload['error'] !== null) {
                        $pesan_error = $upload['error'];
                    } else {
                        $gambar = $upload['filename'];
                        $status = 'terbit';

                        // Simpan artikel
                        $stmt = mysqli_prepare(
                            $koneksi,
                            'INSERT INTO artikel
                            (judul, isi_artikel, gambar, id_kategori, id_user, status)
                            VALUES (?, ?, ?, ?, ?, ?)'
                        );

                        if (!$stmt) {
                            error_log('WargaNet: gagal menyiapkan query INSERT artikel.');
                            $pesan_error = 'Artikel gagal disimpan. Silakan coba kembali.';
                        } else {
                            mysqli_stmt_bind_param(
                                $stmt,
                                'sssiis',
                                $judul,
                                $isi_artikel,
                                $gambar,
                                $id_kategori,
                                $id_user,
                                $status
                            );

                            if (mysqli_stmt_execute($stmt)) {
                                mysqli_stmt_close($stmt);

                                header('Location: index.php');
                                exit;
                            }

                            error_log(
                                'WargaNet: gagal menyimpan artikel: ' .
                                mysqli_stmt_error($stmt)
                            );

                            mysqli_stmt_close($stmt);
                            $pesan_error = 'Artikel gagal disimpan. Silakan periksa data dan coba kembali.';
                        }

                        // Hapus gambar baru jika penyimpanan artikel gagal
                        if ($pesan_error !== '' && !empty($gambar)) {
                            delete_uploaded_image($gambar, $upload_dir);
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tulis Artikel Baru - WargaNet Panel</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/warganet.css">

    <script>document.documentElement.classList.add('js')</script>
</head>

<body class="wn-panel">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 border-bottom border-secondary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="#">WargaNet Panel</a>
        <span class="navbar-text">
            Halo, <?= e($_SESSION['nama_lengkap'] ?? ''); ?>!
        </span>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <div class="col-md-2">
            <div class="list-group list-group-flush border-end border-secondary h-100">

                <a href="index.php"
                   class="list-group-item list-group-item-action bg-secondary text-white">
                    Kelola Artikel
                </a>

                <a href="kategori.php"
                   class="list-group-item list-group-item-action bg-transparent text-light">
                    Kelola Kategori
                </a>

                <a href="pengguna.php"
                   class="list-group-item list-group-item-action bg-transparent text-light">
                    Kelola Pengguna
                </a>

                <form action="../logout.php" method="POST" class="mt-5">
                    <input type="hidden" name="csrf_token"
                           value="<?= e(csrf_token()) ?>">

                    <button type="submit"
                            class="list-group-item list-group-item-action text-danger bg-transparent w-100 text-start border-0">
                        Logout
                    </button>
                </form>

            </div>
        </div>

        <!-- Konten -->
        <div class="col-md-10 px-4">

            <h3 class="mb-4">Tulis Artikel Baru</h3>

            <?php if ($pesan_error !== '') : ?>
                <div class="alert alert-danger">
                    <?= e($pesan_error); ?>
                </div>
            <?php endif; ?>

            <div class="card card-dark shadow-lg rounded-3">
                <div class="card-body p-4">

                    <form action="" method="POST" enctype="multipart/form-data">

                        <input type="hidden" name="csrf_token"
                               value="<?= e(csrf_token()); ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Judul Artikel</label>

                            <input
                                type="text"
                                name="judul"
                                class="form-control bg-dark text-white border-secondary"
                                required
                                maxlength="200"
                                placeholder="Masukkan judul berita..."
                                value="<?= e($judul_form); ?>"
                            >
                        </div>

                        <div class="row mb-3">

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Kategori Berita</label>

                                <select
                                    name="id_kategori"
                                    class="form-select bg-dark text-white border-secondary"
                                    required
                                    <?= !$kategori_tersedia ? 'disabled' : ''; ?>
                                >
                                    <option value="">-- Pilih Kategori --</option>

                                    <?php if ($kategori_tersedia) : ?>
                                        <?php while ($kat = mysqli_fetch_assoc($query_kategori)) : ?>
                                            <option
                                                value="<?= (int)$kat['id_kategori']; ?>"
                                                <?= $id_kategori_form === (string)$kat['id_kategori'] ? 'selected' : ''; ?>
                                            >
                                                <?= e($kat['nama_kategori']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else : ?>
                                        <option value="" disabled>
                                            Belum ada kategori tersedia
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Gambar Sampul</label>

                                <input
                                    type="file"
                                    name="gambar"
                                    class="form-control bg-dark text-white border-secondary"
                                    accept="image/png, image/jpeg"
                                    required
                                >

                                <small class="text-secondary">
                                    Format JPG/PNG. Maksimal 2MB.
                                </small>
                            </div>

                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Isi Artikel</label>

                            <textarea
                                name="isi_artikel"
                                class="form-control bg-dark text-white border-secondary"
                                rows="10"
                                required
                                placeholder="Ketik isi berita lengkap di sini..."
                            ><?= e($isi_form); ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">

                            <a href="index.php"
                               class="btn btn-outline-secondary px-4">
                                Batal
                            </a>

                            <button
                                type="submit"
                                name="simpan"
                                class="btn btn-primary px-5"
                                <?= !$kategori_tersedia ? 'disabled' : ''; ?>
                            >
                                Simpan & Publikasikan
                            </button>

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
