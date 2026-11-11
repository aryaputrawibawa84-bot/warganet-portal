<?php
// ========================================
// 1. PENGATURAN SESSION
// ========================================

$https = (
    !empty($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off'
);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// ========================================
// 2. MEMANGGIL FILE PENDUKUNG
// ========================================

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../koneksi.php';

// ========================================
// 3. VALIDASI HAK AKSES ADMIN
// ========================================

$id_user = valid_positive_int($_SESSION['id_user'] ?? null);

if (
    $id_user === null
    || ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

// ========================================
// 4. VARIABEL AWAL
// ========================================

$pesan_error = '';
$judul_form = '';
$isi_form = '';
$id_kategori_form = '';

$query_kategori = false;
$kategori_tersedia = false;

// ========================================
// 5. AMBIL DAFTAR KATEGORI
// ========================================

$query_kategori = mysqli_query(
    $koneksi,
    'SELECT id_kategori, nama_kategori
     FROM kategori
     ORDER BY nama_kategori ASC'
);

if ($query_kategori === false) {
    error_log(
        'WargaNet admin/tambah.php - Gagal mengambil kategori: '
        . mysqli_error($koneksi)
    );

    $pesan_error = 'Kategori belum dapat dimuat. Silakan coba kembali.';
} else {
    $kategori_tersedia = mysqli_num_rows($query_kategori) > 0;
}

// ========================================
// 6. PROSES PENYIMPANAN ARTIKEL
// ========================================

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['simpan'])
) {
    require_csrf();

    // ------------------------------------
    // Ambil dan validasi input
    // ------------------------------------

    $judul_input = $_POST['judul'] ?? '';
    $isi_input = $_POST['isi_artikel'] ?? '';
    $kategori_input = $_POST['id_kategori'] ?? '';

    $judul = is_string($judul_input)
        ? trim($judul_input)
        : '';

    $isi_artikel_raw = is_string($isi_input)
        ? trim($isi_input)
        : '';

    $kategori_string = is_scalar($kategori_input)
        ? (string) $kategori_input
        : '';

    $id_kategori = valid_positive_int($kategori_input);

    // Sanitasi HTML dari Quill.
    $isi_artikel = sanitize_article_html($isi_artikel_raw);

    // Simpan kembali input untuk ditampilkan jika gagal.
    $judul_form = $judul;
    $isi_form = $isi_artikel;
    $id_kategori_form = $kategori_string;

    // ------------------------------------
    // Periksa isi artikel
    // ------------------------------------

    $teks_artikel = html_entity_decode(
        strip_tags($isi_artikel),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $teks_artikel = str_replace(
        "\xc2\xa0",
        ' ',
        $teks_artikel
    );

    $teks_artikel = trim($teks_artikel);

    // ------------------------------------
    // Hitung panjang judul
    // ------------------------------------

    $panjang_judul = function_exists('mb_strlen')
        ? mb_strlen($judul, 'UTF-8')
        : preg_match_all('/./us', $judul);

    if ($panjang_judul === false) {
        $panjang_judul = strlen($judul);
    }

    // ------------------------------------
    // Validasi judul dan isi
    // ------------------------------------

    if (
        $judul === ''
        || $panjang_judul > 200
        || $teks_artikel === ''
    ) {
        $pesan_error = 'Judul wajib diisi (maksimal 200 karakter) dan isi artikel tidak boleh kosong.';

    } elseif ($id_kategori === null) {
        $pesan_error = 'Pilih kategori yang valid.';

    } elseif ($query_kategori === false) {
        $pesan_error = 'Kategori belum dapat dimuat. Silakan coba kembali.';

    } else {

        // ========================================
        // 7. PERIKSA KATEGORI DI DATABASE
        // ========================================

        $stmt_kategori = mysqli_prepare(
            $koneksi,
            'SELECT id_kategori
             FROM kategori
             WHERE id_kategori = ?
             LIMIT 1'
        );

        if ($stmt_kategori === false) {
            error_log(
                'WargaNet admin/tambah.php - Prepare kategori gagal: '
                . mysqli_error($koneksi)
            );

            $pesan_error = 'Terjadi kesalahan saat memeriksa kategori.';

        } else {

            $bind_kategori = mysqli_stmt_bind_param(
                $stmt_kategori,
                'i',
                $id_kategori
            );

            if (
                !$bind_kategori
                || !mysqli_stmt_execute($stmt_kategori)
            ) {
                error_log(
                    'WargaNet admin/tambah.php - Pemeriksaan kategori gagal: '
                    . mysqli_stmt_error($stmt_kategori)
                );

                $pesan_error = 'Kategori gagal diperiksa. Silakan coba kembali.';

                mysqli_stmt_close($stmt_kategori);

            } else {

                mysqli_stmt_store_result($stmt_kategori);

                $kategori_valid = (
                    mysqli_stmt_num_rows($stmt_kategori) === 1
                );

                mysqli_stmt_close($stmt_kategori);

                if (!$kategori_valid) {
                    $pesan_error = 'Kategori yang dipilih tidak ditemukan.';

                } else {

                    // ========================================
                    // 8. VALIDASI DAN UPLOAD GAMBAR
                    // ========================================

                    $upload_dir = __DIR__ . '/../uploads';

                    if (
                        !isset($_FILES['gambar'])
                        || !is_array($_FILES['gambar'])
                    ) {
                        $pesan_error = 'Silakan pilih gambar sampul artikel.';

                    } else {

                        $upload = save_uploaded_image(
                            $_FILES['gambar'],
                            $upload_dir
                        );

                        if (($upload['error'] ?? null) !== null) {
                            $pesan_error = (string) $upload['error'];

                        } else {

                            $gambar = $upload['filename'] ?? '';

                            if (
                                !is_string($gambar)
                                || $gambar === ''
                            ) {
                                $pesan_error = 'Gambar gagal disimpan. Silakan coba kembali.';

                            } else {

                                // ========================================
                                // 9. SIMPAN ARTIKEL
                                // ========================================

                                // Artikel admin langsung diterbitkan.
                                $status = 'terbit';

                                $stmt = mysqli_prepare(
                                    $koneksi,
                                    'INSERT INTO artikel
                                    (
                                        judul,
                                        isi_artikel,
                                        gambar,
                                        id_kategori,
                                        id_user,
                                        status
                                    )
                                    VALUES (?, ?, ?, ?, ?, ?)'
                                );

                                if ($stmt === false) {
                                    error_log(
                                        'WargaNet admin/tambah.php - Prepare INSERT gagal: '
                                        . mysqli_error($koneksi)
                                    );

                                    $pesan_error = 'Artikel gagal disimpan. Silakan coba kembali.';

                                } else {

                                    $bind_insert = mysqli_stmt_bind_param(
                                        $stmt,
                                        'sssiis',
                                        $judul,
                                        $isi_artikel,
                                        $gambar,
                                        $id_kategori,
                                        $id_user,
                                        $status
                                    );

                                    if (
                                        $bind_insert
                                        && mysqli_stmt_execute($stmt)
                                    ) {
                                        mysqli_stmt_close($stmt);

                                        header('Location: index.php');
                                        exit;
                                    }

                                    error_log(
                                        'WargaNet admin/tambah.php - INSERT artikel gagal: '
                                        . mysqli_stmt_error($stmt)
                                    );

                                    mysqli_stmt_close($stmt);

                                    $pesan_error = 'Artikel gagal disimpan. Silakan periksa data dan coba kembali.';
                                }

                                // ------------------------------------
                                // Hapus gambar jika INSERT gagal
                                // ------------------------------------

                                if ($pesan_error !== '') {
                                    delete_uploaded_image(
                                        $gambar,
                                        $upload_dir
                                    );
                                }
                            }
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
    <meta charset="UTF-8">
    <meta name="theme-color" content="#0B1120">
    <!-- Terapkan tema tersimpan sebelum halaman digambar (mencegah kedipan) -->
    <script>
        (function () {
            var t = 'dark';
            try { t = localStorage.getItem('wn-theme') === 'light' ? 'light' : 'dark'; } catch (e) {}
            document.documentElement.setAttribute('data-bs-theme', t);
            var m = document.querySelector('meta[name="theme-color"]');
            if (m) m.setAttribute('content', t === 'light' ? '#F3F6FC' : '#0B1120');
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tulis Artikel Baru - WargaNet Panel</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- CSS WargaNet -->
    <link
        rel="stylesheet"
        href="../assets/css/warganet.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/warganet.css'); ?>"
    >

    <!-- Quill CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css"
        rel="stylesheet"
    >

    <!-- Tipografi artikel (font & ukuran) + gaya editor, mengikuti tema gelap/terang -->
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,700;1,400&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Montserrat:ital,wght@0,400;0,600;1,400&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,400;0,600;1,400&family=Roboto:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link
        rel="stylesheet"
        href="../assets/css/artikel.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/artikel.css'); ?>"
    >
    <link
        rel="stylesheet"
        href="../assets/css/editor.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/editor.css'); ?>"
    >
</head>

<body class="wn-panel">

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 border-bottom border-secondary">

        <div class="container-fluid">

            <a class="navbar-brand fw-bold text-info" href="index.php">
                <i class="bi bi-activity me-1"></i>
                WargaNet Panel
            </a>

            <span class="navbar-text">
                Halo, <?= e($_SESSION['nama_lengkap'] ?? ''); ?>!
            </span>

        </div>

    </nav>

    <div class="container-fluid">

        <div class="row">

            <!-- SIDEBAR -->
            <div class="col-md-2">

                <div class="list-group list-group-flush border-end border-secondary h-100">

                    <a
                        href="index.php"
                        class="list-group-item list-group-item-action bg-secondary text-white"
                    >
                        <i class="bi bi-newspaper me-2"></i>
                        Kelola Artikel
                    </a>

                    <a
                        href="kategori.php"
                        class="list-group-item list-group-item-action bg-transparent text-light"
                    >
                        <i class="bi bi-tags me-2"></i>
                        Kelola Kategori
                    </a>

                    <a
                        href="pengguna.php"
                        class="list-group-item list-group-item-action bg-transparent text-light"
                    >
                        <i class="bi bi-people me-2"></i>
                        Kelola Pengguna
                    </a>

                    <!-- LOGOUT POST + CSRF -->
                    <form action="../logout.php" method="POST" class="mt-5">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()); ?>"
                        >

                        <button
                            type="submit"
                            class="list-group-item list-group-item-action text-danger bg-transparent w-100 text-start border-0"
                        >
                            <i class="bi bi-box-arrow-right me-2"></i>
                            Logout
                        </button>

                    </form>

                </div>

            </div>

            <!-- KONTEN UTAMA -->
            <div class="col-md-10 px-4">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">

                    <div>
                        <h3 class="fw-bold mb-1">
                            Tulis Artikel Baru
                        </h3>

                        <p class="text-secondary mb-0">
                            Buat dan publikasikan berita baru di WargaNet.
                        </p>
                    </div>

                    <span class="badge bg-success px-3 py-2">
                        <i class="bi bi-check-circle me-1"></i>
                        Publikasi Admin
                    </span>

                </div>

                <!-- PESAN ERROR -->
                <?php if ($pesan_error !== '') : ?>

                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= e($pesan_error); ?>
                    </div>

                <?php endif; ?>

                <!-- FORM ARTIKEL -->
                <div class="card card-dark shadow-lg rounded-3 mb-5">

                    <div class="card-body p-4">

                        <form
                            id="form-artikel"
                            action=""
                            method="POST"
                            enctype="multipart/form-data"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(csrf_token()); ?>"
                            >

                            <!-- JUDUL -->
                            <div class="mb-4">

                                <label class="form-label fw-bold" for="judul">
                                    <i class="bi bi-type me-2 text-info"></i>
                                    Judul Artikel
                                </label>

                                <input
                                    type="text"
                                    name="judul"
                                    id="judul"
                                    class="form-control bg-dark text-white border-secondary"
                                    required
                                    maxlength="200"
                                    placeholder="Masukkan judul berita..."
                                    value="<?= e($judul_form); ?>"
                                >

                                <small class="text-secondary">
                                    Maksimal 200 karakter.
                                </small>

                            </div>

                            <!-- KATEGORI DAN GAMBAR -->
                            <div class="row mb-4">

                                <div class="col-md-6 mb-3 mb-md-0">

                                    <label class="form-label fw-bold" for="id_kategori">
                                        <i class="bi bi-tags me-2 text-info"></i>
                                        Kategori Berita
                                    </label>

                                    <select
                                        name="id_kategori"
                                        id="id_kategori"
                                        class="form-select bg-dark text-white border-secondary"
                                        required
                                        <?= !$kategori_tersedia ? 'disabled' : ''; ?>
                                    >

                                        <option value="">
                                            -- Pilih Kategori --
                                        </option>

                                        <?php if ($kategori_tersedia) : ?>

                                            <?php
                                            mysqli_data_seek($query_kategori, 0);

                                            while ($kat = mysqli_fetch_assoc($query_kategori)) :
                                            ?>

                                                <option
                                                    value="<?= (int) $kat['id_kategori']; ?>"
                                                    <?= $id_kategori_form === (string) $kat['id_kategori'] ? 'selected' : ''; ?>
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

                                    <label class="form-label fw-bold" for="gambar">
                                        <i class="bi bi-image me-2 text-info"></i>
                                        Gambar Sampul
                                    </label>

                                    <input
                                        type="file"
                                        name="gambar"
                                        id="gambar"
                                        class="form-control bg-dark text-white border-secondary"
                                        accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                        required
                                    >

                                    <small class="text-secondary">
                                        Format JPG/PNG. Maksimal 2MB.
                                    </small>

                                </div>

                            </div>

                            <!-- ISI ARTIKEL -->
                            <div class="mb-4">

                                <label class="form-label fw-bold mb-2">
                                    <i class="bi bi-file-richtext me-2 text-info"></i>
                                    Isi Artikel
                                </label>

                                <div class="editor-wrapper">

                                    <!-- TOOLBAR QUILL -->
                                    <div id="editor-toolbar">

                                        <span class="ql-formats">

                                            <select class="ql-font"></select>

                                            <select class="ql-size"></select>

                                        </span>

                                        <span class="ql-formats">

                                            <select class="ql-header">
                                                <option selected></option>
                                                <option value="2"></option>
                                                <option value="3"></option>
                                            </select>

                                        </span>

                                        <span class="ql-formats">

                                            <button type="button" class="ql-bold"></button>
                                            <button type="button" class="ql-italic"></button>
                                            <button type="button" class="ql-underline"></button>
                                            <button type="button" class="ql-strike"></button>

                                        </span>

                                        <span class="ql-formats">

                                            <select class="ql-color"></select>
                                            <select class="ql-background"></select>

                                        </span>

                                        <span class="ql-formats">

                                            <button type="button" class="ql-list" value="ordered"></button>
                                            <button type="button" class="ql-list" value="bullet"></button>

                                            <button type="button" class="ql-indent" value="-1"></button>
                                            <button type="button" class="ql-indent" value="+1"></button>

                                        </span>

                                        <span class="ql-formats">

                                            <select class="ql-align"></select>

                                        </span>

                                        <span class="ql-formats">

                                            <button type="button" class="ql-script" value="sub"></button>
                                            <button type="button" class="ql-script" value="super"></button>
                                            <button type="button" class="ql-blockquote"></button>
                                            <button type="button" class="ql-link"></button>
                                            <button type="button" class="ql-clean"></button>

                                        </span>

                                    </div>

                                    <!-- AREA EDITOR -->
                                    <div id="editor-container"></div>

                                    <!-- INPUT HTML UNTUK PHP -->
                                    <textarea
                                        name="isi_artikel"
                                        id="isi_artikel"
                                        hidden
                                    ><?= e($isi_form); ?></textarea>

                                </div>

                                <div class="d-flex justify-content-between flex-wrap gap-2 mt-2">

                                    <small class="editor-keterangan">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Gunakan toolbar untuk mengatur format artikel.
                                    </small>

                                    <small
                                        class="editor-keterangan"
                                        id="jumlah-kata"
                                    >
                                        0 kata
                                    </small>

                                </div>

                                <div
                                    class="alert alert-danger mt-3 d-none"
                                    id="editor-error"
                                    role="alert"
                                >
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Isi artikel tidak boleh kosong.
                                </div>

                                <div
                                    class="alert alert-danger mt-3 d-none"
                                    id="editor-load-error"
                                    role="alert"
                                >
                                    Editor gagal dimuat. Periksa koneksi internet dan muat ulang halaman.
                                </div>

                            </div>

                            <!-- TOMBOL -->
                            <div class="d-flex justify-content-end gap-2 flex-wrap">

                                <a
                                    href="index.php"
                                    class="btn btn-outline-secondary px-4"
                                >
                                    <i class="bi bi-arrow-left me-1"></i>
                                    Batal
                                </a>

                                <button
                                    type="submit"
                                    name="simpan"
                                    id="tombol-simpan"
                                    class="btn btn-primary px-5"
                                    <?= !$kategori_tersedia ? 'disabled' : ''; ?>
                                >
                                    <i class="bi bi-send me-2"></i>
                                    Simpan & Publikasikan
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- BOOTSTRAP JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- QUILL JS -->
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>

    <!-- FONT & UKURAN HURUF EDITOR -->
    <script src="../assets/js/editor.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/editor.js'); ?>"></script>

    <!-- INISIALISASI QUILL -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const editorElement = document.getElementById('editor-container');
            const textarea = document.getElementById('isi_artikel');
            const form = document.getElementById('form-artikel');
            const jumlahKata = document.getElementById('jumlah-kata');
            const editorError = document.getElementById('editor-error');
            const editorLoadError = document.getElementById('editor-load-error');
            const tombolSimpan = document.getElementById('tombol-simpan');

            // Pastikan elemen tersedia.
            if (
                !editorElement
                || !textarea
                || !form
                || !jumlahKata
                || !editorError
                || !editorLoadError
                || !tombolSimpan
            ) {
                return;
            }

            // Periksa library Quill.
            if (typeof Quill === 'undefined') {

                editorLoadError.classList.remove('d-none');
                tombolSimpan.disabled = true;

                return;
            }

            // Inisialisasi Quill.
            const quill = new Quill('#editor-container', {

                theme: 'snow',

                modules: {
                    toolbar: '#editor-toolbar'
                },

                placeholder: 'Ketik isi berita lengkap di sini...',

                formats: [
                    'font',
                    'size',
                    'header',
                    'bold',
                    'italic',
                    'underline',
                    'strike',
                    'color',
                    'background',
                    'list',
                    'indent',
                    'align',
                    'blockquote',
                    'link',
                    'script'
                ]

            });

            // Pulihkan isi editor jika validasi sebelumnya gagal.
            const isiAwal = textarea.value.trim();

            if (isiAwal !== '') {
                quill.clipboard.dangerouslyPasteHTML(isiAwal);
            }

            // Hitung jumlah kata.
            function hitungKata() {

                const teks = quill.getText().trim();

                const jumlah = teks === ''
                    ? 0
                    : teks.split(/\s+/).length;

                jumlahKata.textContent = jumlah + ' kata';
            }

            // Perbarui jumlah kata ketika isi berubah.
            quill.on('text-change', function () {

                hitungKata();

                editorError.classList.add('d-none');

            });

            hitungKata();

            // Sinkronkan isi Quill sebelum form dikirim.
            form.addEventListener('submit', function (event) {

                const teks = quill.getText().trim();

                if (teks === '') {

                    event.preventDefault();

                    editorError.classList.remove('d-none');

                    quill.focus();

                    return;
                }

                textarea.value = quill.root.innerHTML;

            });

        });
    </script>

    <!-- JAVASCRIPT WARGANET -->
    <script src="../assets/js/warganet.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/warganet.js'); ?>"></script>

</body>
</html>