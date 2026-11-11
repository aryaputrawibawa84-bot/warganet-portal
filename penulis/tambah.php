<?php
// ========================================
// 1. PENGATURAN SESSION
// ========================================

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

// ========================================
// 2. PROTEKSI HALAMAN
// ========================================

$id_user = valid_positive_int($_SESSION['id_user'] ?? null);

if (
    $id_user === null ||
    ($_SESSION['role'] ?? '') !== 'penulis'
) {
    header('Location: ../login.php');
    exit;
}

// ========================================
// 3. VARIABEL AWAL
// ========================================

$pesan_error = '';
$judul_lama = '';
$isi_lama = '';
$kategori_lama = null;

// ========================================
// 4. AMBIL DAFTAR KATEGORI
// ========================================

$query_kategori = mysqli_query(
    $koneksi,
    'SELECT id_kategori, nama_kategori
     FROM kategori
     ORDER BY nama_kategori ASC'
);

$kategori_tersedia = false;

if ($query_kategori === false) {
    error_log(
        'WargaNet: gagal mengambil kategori: ' .
        mysqli_error($koneksi)
    );

    $pesan_error = 'Kategori belum dapat dimuat. Silakan coba kembali.';
} else {
    $kategori_tersedia = mysqli_num_rows($query_kategori) > 0;

    if (!$kategori_tersedia) {
        $pesan_error = 'Belum ada kategori tersedia. Hubungi administrator.';
    }
}

// ========================================
// 5. PROSES PENYIMPANAN ARTIKEL
// ========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['simpan'])
) {
    require_csrf();

    $judul = trim((string) ($_POST['judul'] ?? ''));

    $isi_artikel_raw = trim(
        (string) ($_POST['isi_artikel'] ?? '')
    );

    // Sanitasi HTML dari Quill.
    $isi_artikel = sanitize_article_html($isi_artikel_raw);

    $id_kategori = valid_positive_int(
        $_POST['id_kategori'] ?? null
    );

    // Simpan input agar tidak hilang ketika validasi gagal.
    $judul_lama = $judul;
    $isi_lama = $isi_artikel;
    $kategori_lama = $id_kategori;

    // Periksa apakah isi artikel memiliki teks.
    $teks_artikel = html_entity_decode(
        strip_tags($isi_artikel),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $teks_artikel = str_replace(
        ["\xc2\xa0", '&nbsp;'],
        ' ',
        $teks_artikel
    );

    $teks_artikel = trim($teks_artikel);

    $panjang_judul = function_exists('mb_strlen')
        ? mb_strlen($judul, 'UTF-8')
        : strlen($judul);

    // Validasi data.
    if (
        $judul === '' ||
        $panjang_judul > 200 ||
        $teks_artikel === ''
    ) {
        $pesan_error = 'Judul wajib diisi (maksimal 200 karakter) dan isi artikel tidak boleh kosong.';

    } elseif ($id_kategori === null) {
        $pesan_error = 'Pilih kategori yang valid.';

    } elseif (!$kategori_tersedia) {
        $pesan_error = 'Kategori belum tersedia. Silakan hubungi administrator.';

    } else {

        // ========================================
        // 6. PERIKSA KATEGORI
        // ========================================

        $check = mysqli_prepare(
            $koneksi,
            'SELECT id_kategori
             FROM kategori
             WHERE id_kategori = ?
             LIMIT 1'
        );

        if (!$check) {
            error_log(
                'WargaNet: category prepare failed: ' .
                mysqli_error($koneksi)
            );

            $pesan_error = 'Terjadi kesalahan saat memeriksa kategori.';

        } else {
            mysqli_stmt_bind_param(
                $check,
                'i',
                $id_kategori
            );

            if (!mysqli_stmt_execute($check)) {
                error_log(
                    'WargaNet: category execute failed: ' .
                    mysqli_stmt_error($check)
                );

                $pesan_error = 'Kategori gagal diperiksa. Silakan coba kembali.';

                mysqli_stmt_close($check);

            } else {
                mysqli_stmt_store_result($check);

                $kategori_valid = (
                    mysqli_stmt_num_rows($check) === 1
                );

                mysqli_stmt_close($check);

                if (!$kategori_valid) {
                    $pesan_error = 'Kategori yang dipilih tidak ditemukan.';

                } else {

                    // ========================================
                    // 7. UPLOAD GAMBAR
                    // ========================================

                    $upload_dir = __DIR__ . '/../uploads';

                    $upload = save_uploaded_image(
                        $_FILES['gambar'] ?? [],
                        $upload_dir
                    );

                    if (
                        !is_array($upload) ||
                        ($upload['error'] ?? null) !== null
                    ) {
                        $pesan_error = is_array($upload)
                            ? ($upload['error'] ?? 'Gambar gagal diunggah.')
                            : 'Gambar gagal diunggah.';

                    } else {
                        $gambar = $upload['filename'] ?? '';

                        if (
                            !is_string($gambar) ||
                            $gambar === ''
                        ) {
                            $pesan_error = 'Gambar gagal disimpan. Silakan coba kembali.';

                        } else {

                            // Artikel penulis harus menunggu persetujuan admin.
                            $status = 'pending';

                            // ========================================
                            // 8. SIMPAN ARTIKEL
                            // ========================================

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

                            if (!$stmt) {
                                error_log(
                                    'WargaNet: article prepare failed: ' .
                                    mysqli_error($koneksi)
                                );

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
                                    'WargaNet: article insert failed: ' .
                                    mysqli_stmt_error($stmt)
                                );

                                mysqli_stmt_close($stmt);

                                $pesan_error = 'Artikel gagal disimpan. Silakan periksa data dan coba kembali.';
                            }

                            // Hapus gambar jika artikel gagal disimpan.
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

    <title>Tulis Berita Baru - Ruang Penulis WargaNet</title>

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
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
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
                WargaNet | Ruang Penulis
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
                        Artikel Saya
                    </a>

                    <a
                        href="../index.php"
                        class="list-group-item list-group-item-action bg-transparent text-light"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <i class="bi bi-globe me-2"></i>
                        Lihat Web Publik
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

                <h3 class="mb-3">Tulis Berita Baru</h3>

                <div class="alert alert-info bg-transparent border-info text-info py-2 mb-4">
                    <strong>
                        <i class="bi bi-info-circle me-1"></i>
                        Catatan:
                    </strong>

                    Artikel Anda akan berstatus <em>Pending</em>
                    hingga disetujui oleh Administrator.
                </div>

                <!-- PESAN ERROR -->
                <?php if ($pesan_error !== '') : ?>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= e($pesan_error); ?>
                    </div>

                <?php endif; ?>

                <!-- FORM ARTIKEL -->
                <div class="card editor-card shadow-lg rounded-3">
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

                                <label class="form-label fw-bold">
                                    Judul Artikel
                                </label>

                                <input
                                    type="text"
                                    name="judul"
                                    class="form-control"
                                    maxlength="200"
                                    required
                                    placeholder="Masukkan judul berita..."
                                    value="<?= e($judul_lama); ?>"
                                >

                                <small class="text-secondary">
                                    Maksimal 200 karakter.
                                </small>

                            </div>

                            <!-- KATEGORI DAN GAMBAR -->
                            <div class="row mb-4">

                                <div class="col-md-6 mb-3 mb-md-0">

                                    <label class="form-label fw-bold">
                                        Kategori Berita
                                    </label>

                                    <select
                                        name="id_kategori"
                                        class="form-select"
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
                                                    <?= $kategori_lama === (int) $kat['id_kategori'] ? 'selected' : ''; ?>
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

                                    <label class="form-label fw-bold">
                                        Gambar Sampul
                                    </label>

                                    <input
                                        type="file"
                                        name="gambar"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                        required
                                    >

                                    <small class="text-secondary">
                                        Format JPG atau PNG. Maksimal 2MB.
                                    </small>

                                </div>

                            </div>

                            <!-- ISI ARTIKEL -->
                            <div class="mb-4">

                                <label class="form-label fw-bold mb-2">
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

                                </div>

                                <!-- INPUT HTML UNTUK PHP -->
                                <textarea
                                    name="isi_artikel"
                                    id="isi_artikel"
                                    hidden
                                ><?= e($isi_lama); ?></textarea>

                                <div class="d-flex justify-content-between flex-wrap gap-2 mt-2">

                                    <small class="editor-keterangan">
                                        <i class="bi bi-pencil-square me-1"></i>
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
                                >
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Isi artikel tidak boleh kosong.
                                </div>

                                <div
                                    class="alert alert-danger mt-3 d-none"
                                    id="editor-load-error"
                                >
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Editor gagal dimuat. Periksa koneksi internet dan muat ulang halaman.
                                </div>

                            </div>

                            <!-- TOMBOL -->
                            <div class="d-flex justify-content-end gap-2 flex-wrap">

                                <a
                                    href="index.php"
                                    class="btn btn-outline-secondary px-4"
                                >
                                    Batal
                                </a>

                                <button
                                    type="submit"
                                    name="simpan"
                                    id="tombol-simpan"
                                    class="btn btn-primary px-5"
                                    <?= !$kategori_tersedia ? 'disabled' : ''; ?>
                                >
                                    <i class="bi bi-send me-1"></i>
                                    Kirim untuk Ditinjau
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

            if (
                !editorElement ||
                !textarea ||
                !form ||
                !jumlahKata ||
                !editorError ||
                !editorLoadError ||
                !tombolSimpan
            ) {
                console.error('Elemen editor WargaNet tidak ditemukan.');

                if (tombolSimpan) {
                    tombolSimpan.disabled = true;
                }

                return;
            }

            // Periksa apakah Quill berhasil dimuat.
            if (typeof Quill === 'undefined') {

                editorLoadError.classList.remove('d-none');
                tombolSimpan.disabled = true;

                return;
            }

            // Inisialisasi editor.
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

            quill.on('text-change', function () {

                hitungKata();

                editorError.classList.add('d-none');

            });

            hitungKata();

            // Sinkronkan HTML Quill sebelum dikirim ke PHP.
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