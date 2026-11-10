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
require_once __DIR__ . '/../includes/tampilan.php';
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
// 4. VALIDASI ID ARTIKEL
// ========================================

$id_artikel = valid_positive_int($_GET['id'] ?? null);

if ($id_artikel === null) {
    header('Location: index.php');
    exit;
}

// ========================================
// 5. VARIABEL AWAL
// ========================================

$upload_dir = __DIR__ . '/../uploads';

$pesan_error = '';

$status_valid = [
    'draf',
    'pending',
    'terbit',
    'ditolak'
];

$form = [
    'judul' => '',
    'isi_artikel' => '',
    'id_kategori' => '',
    'status' => 'terbit'
];

// ========================================
// 6. AMBIL DATA ARTIKEL
// ========================================

$stmt = mysqli_prepare(
    $koneksi,
    'SELECT id_artikel, judul, isi_artikel, gambar,
            id_kategori, status
     FROM artikel
     WHERE id_artikel = ?
     LIMIT 1'
);

if ($stmt === false) {
    error_log(
        'WargaNet admin/edit.php - SELECT prepare: '
        . mysqli_error($koneksi)
    );

    http_response_code(500);
    exit('Terjadi kesalahan saat mengambil artikel.');
}

if (!mysqli_stmt_bind_param($stmt, 'i', $id_artikel)) {
    error_log(
        'WargaNet admin/edit.php - SELECT bind: '
        . mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('Terjadi kesalahan saat mengambil artikel.');
}

if (!mysqli_stmt_execute($stmt)) {
    error_log(
        'WargaNet admin/edit.php - SELECT execute: '
        . mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('Terjadi kesalahan saat mengambil artikel.');
}

$hasil = mysqli_stmt_get_result($stmt);
$data = $hasil ? mysqli_fetch_assoc($hasil) : null;

mysqli_stmt_close($stmt);

if (!$data) {
    header('Location: index.php');
    exit;
}

// ========================================
// 7. AMBIL DAFTAR KATEGORI
// ========================================

$daftar_kategori = [];

$query_kategori = mysqli_query(
    $koneksi,
    'SELECT id_kategori, nama_kategori
     FROM kategori
     ORDER BY nama_kategori ASC'
);

$kategori_tersedia = false;

if ($query_kategori === false) {
    error_log(
        'WargaNet admin/edit.php - Kategori: '
        . mysqli_error($koneksi)
    );

    $pesan_error = 'Kategori belum dapat dimuat. Silakan coba kembali.';
} else {
    while ($kat = mysqli_fetch_assoc($query_kategori)) {
        $daftar_kategori[(int) $kat['id_kategori']] =
            (string) $kat['nama_kategori'];
    }

    $kategori_tersedia = !empty($daftar_kategori);

    if (!$kategori_tersedia) {
        $pesan_error = 'Belum ada kategori tersedia. Silakan tambahkan kategori terlebih dahulu.';
    }
}

// ========================================
// 8. ISI AWAL FORM
// ========================================

$form = [
    'judul' => (string) ($data['judul'] ?? ''),
    'isi_artikel' => (string) ($data['isi_artikel'] ?? ''),
    'id_kategori' => (string) ($data['id_kategori'] ?? ''),
    'status' => (string) ($data['status'] ?? 'terbit')
];

// ========================================
// 9. PROSES UPDATE ARTIKEL
// ========================================

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['simpan'])
) {
    require_csrf();

    // ------------------------------------
    // Ambil input
    // ------------------------------------

    $judul_input = $_POST['judul'] ?? '';
    $isi_input = $_POST['isi_artikel'] ?? '';
    $kategori_input = $_POST['id_kategori'] ?? '';
    $status_input = $_POST['status'] ?? '';

    $form['judul'] = is_string($judul_input)
        ? trim($judul_input)
        : '';

    $isi_artikel_raw = is_string($isi_input)
        ? trim($isi_input)
        : '';

    $form['isi_artikel'] = sanitize_article_html(
        $isi_artikel_raw
    );

    $id_kategori = valid_positive_int($kategori_input);

    $form['id_kategori'] = $id_kategori !== null
        ? (string) $id_kategori
        : '';

    $form['status'] = is_string($status_input)
        ? trim($status_input)
        : '';

    // ------------------------------------
    // Validasi isi artikel
    // ------------------------------------

    $teks_artikel = html_entity_decode(
        strip_tags($form['isi_artikel']),
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
        ? mb_strlen($form['judul'], 'UTF-8')
        : strlen($form['judul']);

    // ------------------------------------
    // Validasi data
    // ------------------------------------

    if (
        $form['judul'] === ''
        || $panjang_judul > 200
        || $teks_artikel === ''
    ) {
        $pesan_error = 'Judul wajib diisi (maksimal 200 karakter) dan isi artikel tidak boleh kosong.';

    } elseif ($id_kategori === null) {
        $pesan_error = 'Pilih kategori yang valid.';

    } elseif (!isset($daftar_kategori[$id_kategori])) {
        $pesan_error = 'Kategori yang dipilih tidak ditemukan.';

    } elseif (!in_array($form['status'], $status_valid, true)) {
        $pesan_error = 'Status artikel tidak valid.';

    } elseif (!$kategori_tersedia) {
        $pesan_error = 'Kategori belum tersedia. Silakan coba kembali.';
    }

    // ========================================
    // 10. PERIKSA KATEGORI KE DATABASE
    // ========================================

    if ($pesan_error === '') {
        $cek_kategori = mysqli_prepare(
            $koneksi,
            'SELECT id_kategori
             FROM kategori
             WHERE id_kategori = ?
             LIMIT 1'
        );

        $kategori_valid = false;

        if ($cek_kategori === false) {
            error_log(
                'WargaNet admin/edit.php - Kategori prepare: '
                . mysqli_error($koneksi)
            );

            $pesan_error = 'Terjadi kesalahan saat memeriksa kategori.';

        } else {
            $bind_kategori = mysqli_stmt_bind_param(
                $cek_kategori,
                'i',
                $id_kategori
            );

            if (
                $bind_kategori
                && mysqli_stmt_execute($cek_kategori)
            ) {
                mysqli_stmt_store_result($cek_kategori);

                $kategori_valid = (
                    mysqli_stmt_num_rows($cek_kategori) === 1
                );
            } else {
                error_log(
                    'WargaNet admin/edit.php - Kategori execute: '
                    . mysqli_stmt_error($cek_kategori)
                );
            }

            mysqli_stmt_close($cek_kategori);

            if (!$kategori_valid) {
                $pesan_error = 'Kategori tidak ditemukan atau gagal diperiksa.';
            }
        }
    }

    // ========================================
    // 11. PROSES GAMBAR
    // ========================================

    $gambar_lama = (string) ($data['gambar'] ?? '');
    $gambar_final = $gambar_lama;
    $gambar_baru = null;

    if ($pesan_error === '') {
        $file_error = UPLOAD_ERR_NO_FILE;

        if (
            isset($_FILES['gambar'])
            && is_array($_FILES['gambar'])
            && isset($_FILES['gambar']['error'])
            && is_int($_FILES['gambar']['error'])
        ) {
            $file_error = $_FILES['gambar']['error'];
        }

        // Gambar boleh tidak diganti saat edit.
        if ($file_error !== UPLOAD_ERR_NO_FILE) {
            if (
                !isset($_FILES['gambar'])
                || !is_array($_FILES['gambar'])
            ) {
                $pesan_error = 'Data gambar tidak valid.';

            } else {
                $upload = save_uploaded_image(
                    $_FILES['gambar'],
                    $upload_dir
                );

                if (
                    !is_array($upload)
                    || !array_key_exists('error', $upload)
                    || $upload['error'] !== null
                ) {
                    $pesan_error = is_array($upload)
                        ? (string) (
                            $upload['error']
                            ?? 'Gambar gagal diunggah.'
                        )
                        : 'Gambar gagal diunggah. Silakan coba kembali.';

                } elseif (
                    empty($upload['filename'])
                    || !is_string($upload['filename'])
                ) {
                    $pesan_error = 'Nama file gambar tidak valid.';

                } else {
                    $gambar_baru = basename($upload['filename']);
                    $gambar_final = $gambar_baru;
                }
            }
        }
    }

    // ========================================
    // 12. UPDATE ARTIKEL
    // ========================================

    if ($pesan_error === '') {
        $upd = mysqli_prepare(
            $koneksi,
            'UPDATE artikel
             SET judul = ?,
                 isi_artikel = ?,
                 gambar = ?,
                 id_kategori = ?,
                 status = ?
             WHERE id_artikel = ?
             LIMIT 1'
        );

        $berhasil = false;

        if ($upd === false) {
            error_log(
                'WargaNet admin/edit.php - UPDATE prepare: '
                . mysqli_error($koneksi)
            );

        } else {
            $bind_update = mysqli_stmt_bind_param(
                $upd,
                'sssisi',
                $form['judul'],
                $form['isi_artikel'],
                $gambar_final,
                $id_kategori,
                $form['status'],
                $id_artikel
            );

            if (
                $bind_update
                && mysqli_stmt_execute($upd)
            ) {
                $berhasil = true;
            } else {
                error_log(
                    'WargaNet admin/edit.php - UPDATE execute: '
                    . mysqli_stmt_error($upd)
                );
            }

            mysqli_stmt_close($upd);
        }

        if ($berhasil) {
            // Hapus gambar lama hanya setelah update berhasil.
            if (
                $gambar_baru !== null
                && $gambar_lama !== ''
                && $gambar_lama !== $gambar_baru
            ) {
                delete_uploaded_image(
                    $gambar_lama,
                    $upload_dir
                );
            }

            header('Location: index.php');
            exit;
        }

        // Hapus gambar baru jika update gagal.
        if (
            $gambar_baru !== null
            && $gambar_baru !== ''
        ) {
            delete_uploaded_image(
                $gambar_baru,
                $upload_dir
            );
        }

        $pesan_error = 'Artikel gagal diperbarui. Silakan periksa data dan coba kembali.';
    }
}
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Artikel - WargaNet Panel</title>

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

    <script>
        document.documentElement.classList.add('js');
    </script>

    <style>
        /* =====================================
           WARGANET - QUILL DARK EDITOR
        ===================================== */

        .editor-wrapper {
            width: 100%;
        }

        .ql-toolbar.ql-snow {
            background: #1e293b !important;
            border: 1px solid #334155 !important;
            border-radius: 10px 10px 0 0;
            padding: 12px;
        }

        .ql-container.ql-snow {
            background: #0f172a !important;
            border: 1px solid #334155 !important;
            border-top: 0 !important;
            border-radius: 0 0 10px 10px;
            font-family: Inter, sans-serif;
            font-size: 16px;
        }

        #editor-container {
            min-height: 380px;
            background: #0f172a;
            color: #e2e8f0;
        }

        #editor-container .ql-editor {
            min-height: 380px;
            padding: 22px;
            line-height: 1.85;
            color: #e2e8f0;
            overflow-wrap: break-word;
        }

        #editor-container .ql-editor.ql-blank::before {
            color: #94a3b8;
            font-style: normal;
            left: 22px;
        }

        .ql-snow .ql-stroke {
            stroke: #cbd5e1;
        }

        .ql-snow .ql-fill {
            fill: #cbd5e1;
        }

        .ql-snow .ql-picker {
            color: #e2e8f0;
        }

        .ql-snow .ql-picker-options {
            background: #1e293b !important;
            border: 1px solid #334155 !important;
            border-radius: 8px;
        }

        .ql-snow .ql-picker-item {
            color: #e2e8f0;
        }

        .ql-snow .ql-picker-label {
            border-radius: 5px;
        }

        .ql-snow .ql-toolbar button:hover,
        .ql-snow .ql-toolbar button.ql-active,
        .ql-snow .ql-toolbar .ql-picker-label:hover,
        .ql-snow .ql-toolbar .ql-picker-label.ql-active {
            color: #60a5fa;
        }

        .ql-snow .ql-toolbar button:hover .ql-stroke,
        .ql-snow .ql-toolbar button.ql-active .ql-stroke {
            stroke: #60a5fa;
        }

        .ql-snow .ql-toolbar button:hover .ql-fill,
        .ql-snow .ql-toolbar button.ql-active .ql-fill {
            fill: #60a5fa;
        }

        #editor-container .ql-editor h2 {
            font-size: 25px;
            font-weight: 700;
            margin: 20px 0 12px;
        }

        #editor-container .ql-editor h3 {
            font-size: 21px;
            font-weight: 700;
            margin: 16px 0 10px;
        }

        #editor-container .ql-editor blockquote {
            border-left: 4px solid #3b82f6;
            padding-left: 15px;
            color: #cbd5e1;
        }

        #editor-container .ql-editor a {
            color: #60a5fa;
            text-decoration: underline;
        }

        #editor-container .ql-editor ul,
        #editor-container .ql-editor ol {
            padding-left: 1.5em;
        }

        #editor-container .ql-font-serif {
            font-family: Georgia, serif;
        }

        #editor-container .ql-font-monospace {
            font-family: monospace;
        }

        #editor-container .ql-size-small {
            font-size: 0.75em;
        }

        #editor-container .ql-size-large {
            font-size: 1.5em;
        }

        #editor-container .ql-size-huge {
            font-size: 2.5em;
        }

        .editor-keterangan {
            font-size: 13px;
            color: #94a3b8;
        }

        .ql-container.ql-snow:focus-within {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .editor-card {
            background: #172235;
            border: 1px solid rgba(148, 163, 184, 0.15);
        }

        @media (max-width: 768px) {
            #editor-container,
            #editor-container .ql-editor {
                min-height: 280px;
            }

            .ql-toolbar.ql-snow {
                padding: 7px;
            }

            .card-body {
                padding: 18px !important;
            }
        }
    </style>
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
                            Edit Artikel
                        </h3>

                        <p class="text-secondary mb-0">
                            Perbarui isi artikel dan pengaturan publikasi WargaNet.
                        </p>
                    </div>

                    <span class="badge bg-warning text-dark px-3 py-2">
                        <i class="bi bi-pencil-square me-1"></i>
                        Edit Artikel
                    </span>

                </div>

                <!-- PESAN ERROR -->
                <?php if ($pesan_error !== '') : ?>

                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= e($pesan_error); ?>
                    </div>

                <?php endif; ?>

                <!-- FORM EDIT ARTIKEL -->
                <div class="card editor-card shadow-lg rounded-3 mb-5">

                    <div class="card-body p-4">

                        <form
                            id="form-edit-artikel"
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
                                    value="<?= e($form['judul']); ?>"
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

                                        <?php foreach ($daftar_kategori as $id_k => $nama_k) : ?>

                                            <option
                                                value="<?= (int) $id_k; ?>"
                                                <?= (string) $id_k === $form['id_kategori'] ? 'selected' : ''; ?>
                                            >
                                                <?= e($nama_k); ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <div class="col-md-6">

                                    <label class="form-label fw-bold">
                                        <i class="bi bi-image me-2 text-info"></i>
                                        Gambar Sampul Saat Ini
                                    </label>

                                    <?php if ($gambar_lama = (string) ($data['gambar'] ?? '')) : ?>

                                        <img
                                            src="../uploads/<?= e(rawurlencode(basename($gambar_lama))); ?>"
                                            alt="Gambar sampul artikel"
                                            class="w-100 rounded-3 border border-secondary mb-3"
                                            style="aspect-ratio: 16 / 9; object-fit: cover;"
                                        >

                                    <?php else : ?>

                                        <div class="text-secondary small mb-3">
                                            Belum ada gambar sampul.
                                        </div>

                                    <?php endif; ?>

                                    <label class="form-label fw-bold" for="gambar">
                                        Ganti Gambar Sampul (Opsional)
                                    </label>

                                    <input
                                        type="file"
                                        name="gambar"
                                        id="gambar"
                                        class="form-control bg-dark text-white border-secondary"
                                        accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                    >

                                    <small class="text-secondary">
                                        Format JPG/PNG. Maksimal 2MB.
                                    </small>

                                </div>

                            </div>

                            <!-- STATUS ARTIKEL -->
                            <div class="mb-4">

                                <label class="form-label fw-bold" for="status">
                                    <i class="bi bi-clipboard-check me-2 text-info"></i>
                                    Status Artikel
                                </label>

                                <select
                                    name="status"
                                    id="status"
                                    class="form-select bg-dark text-white border-secondary"
                                    required
                                >

                                    <option
                                        value="draf"
                                        <?= $form['status'] === 'draf' ? 'selected' : ''; ?>
                                    >
                                        Draf
                                    </option>

                                    <option
                                        value="pending"
                                        <?= $form['status'] === 'pending' ? 'selected' : ''; ?>
                                    >
                                        Pending
                                    </option>

                                    <option
                                        value="terbit"
                                        <?= $form['status'] === 'terbit' ? 'selected' : ''; ?>
                                    >
                                        Terbit
                                    </option>

                                    <option
                                        value="ditolak"
                                        <?= $form['status'] === 'ditolak' ? 'selected' : ''; ?>
                                    >
                                        Ditolak
                                    </option>

                                </select>

                                <small class="text-secondary">
                                    Admin dapat mengatur status publikasi artikel.
                                </small>

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

                                            <select class="ql-font">
                                                <option selected></option>
                                                <option value="serif"></option>
                                                <option value="monospace"></option>
                                            </select>

                                            <select class="ql-size">
                                                <option value="small"></option>
                                                <option selected></option>
                                                <option value="large"></option>
                                                <option value="huge"></option>
                                            </select>

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
                                    ><?= e($form['isi_artikel']); ?></textarea>

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
                                    <i class="bi bi-save me-2"></i>
                                    Simpan Perubahan
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

    <!-- INISIALISASI QUILL -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const editorElement = document.getElementById('editor-container');
            const textarea = document.getElementById('isi_artikel');
            const form = document.getElementById('form-edit-artikel');

            const jumlahKata = document.getElementById('jumlah-kata');
            const editorError = document.getElementById('editor-error');
            const editorLoadError = document.getElementById('editor-load-error');
            const tombolSimpan = document.getElementById('tombol-simpan');

            // Pastikan semua elemen tersedia.
            if (
                !editorElement
                || !textarea
                || !form
                || !jumlahKata
                || !editorError
                || !editorLoadError
                || !tombolSimpan
            ) {
                console.error('Elemen editor WargaNet tidak ditemukan.');

                if (tombolSimpan) {
                    tombolSimpan.disabled = true;
                }

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

            // Pulihkan isi artikel dari database.
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

            // Sinkronkan isi Quill sebelum dikirim ke PHP.
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