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
require_once __DIR__ . '/../includes/tampilan.php';
require_once __DIR__ . '/../koneksi.php';

// ========================================
// 2. PROTEKSI HALAMAN
// ========================================

$id_user_aktif = valid_positive_int($_SESSION['id_user'] ?? null);

if (
    $id_user_aktif === null ||
    ($_SESSION['role'] ?? '') !== 'penulis'
) {
    header('Location: ../login.php');
    exit;
}

// ========================================
// 3. VALIDASI ID ARTIKEL
// ========================================

$id_artikel = valid_positive_int($_GET['id'] ?? null);

if ($id_artikel === null) {
    header('Location: index.php');
    exit;
}

// ========================================
// 4. VARIABEL AWAL
// ========================================

$upload_dir = __DIR__ . '/../uploads';
$pesan_error = '';

$form = [
    'judul' => '',
    'isi_artikel' => '',
    'id_kategori' => 0
];

// Variabel gambar diinisialisasi sejak awal.
$gambar_lama = '';
$gambar_final = '';
$gambar_baru = null;

// ========================================
// 5. AMBIL ARTIKEL MILIK PENULIS
// ========================================

$stmt = mysqli_prepare(
    $koneksi,
    'SELECT id_artikel, judul, isi_artikel, gambar, id_kategori
     FROM artikel
     WHERE id_artikel = ? AND id_user = ?
     LIMIT 1'
);

if (!$stmt) {
    error_log(
        'WargaNet edit SELECT prepare: ' .
        mysqli_error($koneksi)
    );

    http_response_code(500);
    exit('Terjadi kesalahan saat mengambil artikel.');
}

if (!mysqli_stmt_bind_param(
    $stmt,
    'ii',
    $id_artikel,
    $id_user_aktif
)) {
    error_log(
        'WargaNet edit SELECT bind: ' .
        mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('Terjadi kesalahan saat mengambil artikel.');
}

if (!mysqli_stmt_execute($stmt)) {
    error_log(
        'WargaNet edit SELECT execute: ' .
        mysqli_stmt_error($stmt)
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
// 6. AMBIL DAFTAR KATEGORI
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
        'WargaNet edit kategori: ' .
        mysqli_error($koneksi)
    );

    $pesan_error = 'Kategori belum dapat dimuat. Silakan coba kembali.';

} else {

    while ($kat = mysqli_fetch_assoc($query_kategori)) {

        $daftar_kategori[(int) $kat['id_kategori']] =
            (string) $kat['nama_kategori'];
    }

    $kategori_tersedia = !empty($daftar_kategori);

    if (!$kategori_tersedia) {
        $pesan_error = 'Belum ada kategori tersedia. Hubungi administrator.';
    }
}

// ========================================
// 7. ISI AWAL FORM
// ========================================

$form = [
    'judul' => (string) ($data['judul'] ?? ''),
    'isi_artikel' => (string) ($data['isi_artikel'] ?? ''),
    'id_kategori' => (int) ($data['id_kategori'] ?? 0)
];

// Simpan nama gambar lama untuk proses update.
$gambar_lama = (string) ($data['gambar'] ?? '');
$gambar_final = $gambar_lama;

// ========================================
// 8. PROSES UPDATE ARTIKEL
// ========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['simpan'])
) {

    require_csrf();

    $form['judul'] = trim(
        (string) ($_POST['judul'] ?? '')
    );

    $form['isi_artikel'] = sanitize_article_html(
        (string) ($_POST['isi_artikel'] ?? '')
    );

    $form['id_kategori'] = valid_positive_int(
        $_POST['id_kategori'] ?? null
    ) ?? 0;

    if ($pesan_error === '') {

        // Periksa apakah isi artikel memiliki teks.
        $isi_teks = html_entity_decode(
            strip_tags($form['isi_artikel']),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $isi_teks = str_replace(
            ["\xc2\xa0", '&nbsp;'],
            ' ',
            $isi_teks
        );

        $isi_teks = trim($isi_teks);

        $panjang_judul = function_exists('mb_strlen')
            ? mb_strlen($form['judul'], 'UTF-8')
            : strlen($form['judul']);

        if (
            $form['judul'] === '' ||
            $panjang_judul > 200 ||
            $isi_teks === ''
        ) {

            $pesan_error = 'Judul wajib diisi (maksimal 200 karakter) dan isi artikel tidak boleh kosong.';

        } elseif (!isset($daftar_kategori[$form['id_kategori']])) {

            $pesan_error = 'Pilih kategori yang valid.';

        } else {

            // ========================================
            // 9. PERIKSA KATEGORI KE DATABASE
            // ========================================

            $cek_kategori = mysqli_prepare(
                $koneksi,
                'SELECT id_kategori
                 FROM kategori
                 WHERE id_kategori = ?
                 LIMIT 1'
            );

            $kategori_valid = false;

            if ($cek_kategori) {

                $bind_kategori = mysqli_stmt_bind_param(
                    $cek_kategori,
                    'i',
                    $form['id_kategori']
                );

                if (
                    $bind_kategori &&
                    mysqli_stmt_execute($cek_kategori)
                ) {

                    mysqli_stmt_store_result($cek_kategori);

                    $kategori_valid =
                        mysqli_stmt_num_rows($cek_kategori) === 1;

                } else {

                    error_log(
                        'WargaNet edit cek kategori: ' .
                        mysqli_stmt_error($cek_kategori)
                    );
                }

                mysqli_stmt_close($cek_kategori);

            } else {

                error_log(
                    'WargaNet edit kategori prepare: ' .
                    mysqli_error($koneksi)
                );
            }

            if (!$kategori_valid) {
                $pesan_error = 'Kategori tidak ditemukan atau tidak dapat diperiksa.';
            }
        }
    }

    // ========================================
    // 10. PROSES GAMBAR
    // ========================================

    if ($pesan_error === '') {

        // Artikel yang diedit kembali ke status pending.
        $status = 'pending';

        // Gambar bersifat opsional saat edit.
        $file_error = $_FILES['gambar']['error'] ?? UPLOAD_ERR_NO_FILE;

        if (!is_int($file_error)) {
            $file_error = UPLOAD_ERR_NO_FILE;
        }

        if ($file_error !== UPLOAD_ERR_NO_FILE) {

            $upload = save_uploaded_image(
                $_FILES['gambar'] ?? [],
                $upload_dir
            );

            if (
                !is_array($upload) ||
                !array_key_exists('error', $upload) ||
                $upload['error'] !== null
            ) {

                $pesan_error = is_array($upload)
                    ? (string) (
                        $upload['error'] ??
                        'Gambar gagal diunggah.'
                    )
                    : 'Gambar gagal diunggah. Silakan coba kembali.';

            } elseif (
                empty($upload['filename']) ||
                !is_string($upload['filename'])
            ) {

                $pesan_error = 'Nama file gambar tidak valid.';

            } else {

                $gambar_baru = $upload['filename'];
                $gambar_final = $gambar_baru;
            }
        }
    }

    // ========================================
    // 11. UPDATE DATA ARTIKEL
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
             WHERE id_artikel = ? AND id_user = ?'
        );

        $berhasil = false;

        if ($upd) {

            $bind_berhasil = mysqli_stmt_bind_param(
                $upd,
                'sssisii',
                $form['judul'],
                $form['isi_artikel'],
                $gambar_final,
                $form['id_kategori'],
                $status,
                $id_artikel,
                $id_user_aktif
            );

            if ($bind_berhasil) {
                $berhasil = mysqli_stmt_execute($upd);
            }

            if (!$berhasil) {

                error_log(
                    'WargaNet edit UPDATE: ' .
                    mysqli_stmt_error($upd)
                );
            }

            mysqli_stmt_close($upd);

        } else {

            error_log(
                'WargaNet edit UPDATE prepare: ' .
                mysqli_error($koneksi)
            );
        }

        if ($berhasil) {

            // Hapus gambar lama setelah update berhasil.
            if (
                $gambar_baru !== null &&
                $gambar_lama !== '' &&
                $gambar_lama !== $gambar_baru
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
        if ($gambar_baru !== null && $gambar_baru !== '') {

            delete_uploaded_image(
                $gambar_baru,
                $upload_dir
            );
        }

        $pesan_error = 'Tulisan gagal diperbarui. Silakan coba kembali.';
    }
}
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">

<head>

    <meta charset="UTF-8">
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Karya - Ruang Penulis WargaNet</title>

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

    <script>
        document.documentElement.classList.add('js');
    </script>

    <!-- STYLE EDITOR -->
    <style>
        .editor-wrapper {
            width: 100%;
        }

        #editor-toolbar.ql-toolbar.ql-snow {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px;
            padding: 12px;
            background: #1e293b !important;
            border: 1px solid #334155 !important;
            border-radius: 12px 12px 0 0;
        }

        #editor-toolbar .ql-formats {
            display: inline-flex;
            align-items: center;
            margin-right: 8px;
            padding-right: 8px;
            border-right: 1px solid #475569;
        }

        #editor-toolbar .ql-formats:last-child {
            border-right: none;
        }

        #editor-container.ql-container.ql-snow {
            background: #0f172a !important;
            color: #e2e8f0;
            border: 1px solid #334155 !important;
            border-top: none !important;
            border-radius: 0 0 12px 12px;
            font-family: Inter, sans-serif;
            font-size: 16px;
        }

        #editor-container .ql-editor {
            min-height: 380px;
            padding: 24px;
            background: #0f172a !important;
            color: #e2e8f0 !important;
            line-height: 1.85;
            overflow-wrap: break-word;
        }

        #editor-container .ql-editor.ql-blank::before {
            left: 24px;
            color: #94a3b8;
            font-style: normal;
        }

        #editor-toolbar .ql-stroke {
            stroke: #cbd5e1;
        }

        #editor-toolbar .ql-fill {
            fill: #cbd5e1;
        }

        #editor-toolbar .ql-picker {
            color: #e2e8f0;
        }

        #editor-toolbar .ql-picker-label {
            border-radius: 6px;
        }

        #editor-toolbar .ql-picker-options {
            background: #1e293b !important;
            border: 1px solid #475569 !important;
            border-radius: 8px;
        }

        #editor-toolbar .ql-picker-item {
            color: #e2e8f0;
        }

        #editor-toolbar button:hover,
        #editor-toolbar button.ql-active,
        #editor-toolbar .ql-picker-label:hover,
        #editor-toolbar .ql-picker-label.ql-active {
            color: #60a5fa;
        }

        #editor-toolbar button:hover .ql-stroke,
        #editor-toolbar button.ql-active .ql-stroke {
            stroke: #60a5fa;
        }

        #editor-toolbar button:hover .ql-fill,
        #editor-toolbar button.ql-active .ql-fill {
            fill: #60a5fa;
        }

        #editor-container .ql-editor h2 {
            margin: 24px 0 12px;
            font-size: 26px;
            font-weight: 700;
            line-height: 1.4;
        }

        #editor-container .ql-editor h3 {
            margin: 20px 0 10px;
            font-size: 21px;
            font-weight: 700;
            line-height: 1.5;
        }

        #editor-container .ql-editor p {
            margin-bottom: 12px;
        }

        #editor-container .ql-editor blockquote {
            margin: 18px 0;
            padding: 12px 18px;
            border-left: 4px solid #3b82f6;
            background: rgba(59, 130, 246, 0.08);
            color: #cbd5e1;
        }

        #editor-container .ql-editor a {
            color: #60a5fa;
            text-decoration: underline;
        }

        #editor-container .ql-editor ul,
        #editor-container .ql-editor ol {
            padding-left: 1.5em;
            margin-bottom: 14px;
        }

        #editor-container .ql-editor .ql-font-serif {
            font-family: Georgia, serif;
        }

        #editor-container .ql-editor .ql-font-monospace {
            font-family: monospace;
        }

        #editor-container .ql-editor .ql-size-small {
            font-size: 0.75em;
        }

        #editor-container .ql-editor .ql-size-large {
            font-size: 1.5em;
        }

        #editor-container .ql-editor .ql-size-huge {
            font-size: 2.5em;
        }

        .editor-keterangan {
            color: #94a3b8;
            font-size: 13px;
        }

        .editor-card {
            border: 1px solid rgba(148, 163, 184, 0.15);
            background: #172235;
        }

        #editor-container:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        @media (max-width: 768px) {

            #editor-toolbar.ql-toolbar.ql-snow {
                padding: 8px;
                gap: 2px;
            }

            #editor-toolbar .ql-formats {
                margin-right: 3px;
                padding-right: 3px;
            }

            #editor-container .ql-editor {
                min-height: 280px;
                padding: 16px;
            }

            #editor-container .ql-editor.ql-blank::before {
                left: 16px;
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

                    <!-- LOGOUT -->
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

                <h3 class="mb-3">Perbarui Tulisan</h3>

                <div class="alert alert-warning bg-transparent border-warning text-warning py-2 mb-4 fw-bold">

                    <i class="bi bi-info-circle me-2"></i>

                    Mengedit artikel akan mengubah statusnya kembali menjadi
                    <em>Pending</em> untuk direviu ulang.

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

                                <label
                                    for="judul"
                                    class="form-label fw-bold"
                                >
                                    Judul Artikel
                                </label>

                                <input
                                    type="text"
                                    id="judul"
                                    name="judul"
                                    class="form-control"
                                    maxlength="200"
                                    required
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

                                    <label
                                        for="id_kategori"
                                        class="form-label fw-bold"
                                    >
                                        Kategori Berita
                                    </label>

                                    <select
                                        name="id_kategori"
                                        id="id_kategori"
                                        class="form-select"
                                        required
                                        <?= !$kategori_tersedia ? 'disabled' : ''; ?>
                                    >

                                        <option value="">
                                            -- Pilih Kategori --
                                        </option>

                                        <?php foreach ($daftar_kategori as $id_k => $nama_k) : ?>

                                            <option
                                                value="<?= (int) $id_k; ?>"
                                                <?= (int) $id_k === (int) $form['id_kategori'] ? 'selected' : ''; ?>
                                            >
                                                <?= e($nama_k); ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <div class="col-md-6">

                                    <label class="form-label fw-bold">
                                        Sampul Saat Ini
                                    </label>

                                    <?php if (!empty($data['gambar'])) : ?>

                                        <img
                                            src="../uploads/<?= e(rawurlencode(basename((string) $data['gambar']))); ?>"
                                            alt="Sampul artikel saat ini"
                                            class="w-100 rounded-3 border border-secondary mb-3"
                                            style="aspect-ratio: 16 / 9; object-fit: cover;"
                                        >

                                    <?php else : ?>

                                        <div class="text-secondary small mb-3">
                                            Belum ada gambar sampul.
                                        </div>

                                    <?php endif; ?>

                                    <label
                                        for="gambar"
                                        class="form-label fw-bold"
                                    >
                                        Ganti Sampul (Opsional)
                                    </label>

                                    <input
                                        type="file"
                                        id="gambar"
                                        name="gambar"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                    >

                                    <small class="text-secondary">
                                        Format JPG atau PNG. Maksimal 2MB.
                                    </small>

                                </div>

                            </div>

                            <!-- ISI ARTIKEL -->
                            <div class="mb-4">

                                <label class="form-label fw-bold mb-2">
                                    Isi Tulisan
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

                                </div>

                                <!-- HTML UNTUK PHP -->
                                <textarea
                                    name="isi_artikel"
                                    id="isi_artikel"
                                    hidden
                                ><?= e($form['isi_artikel']); ?></textarea>

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
                                    id="tombol-update"
                                    class="btn btn-warning px-5 text-dark fw-bold"
                                    <?= !$kategori_tersedia ? 'disabled' : ''; ?>
                                >
                                    <i class="bi bi-send me-1"></i>
                                    Update &amp; Kirim Ulang
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

            const tombolUpdate = document.getElementById('tombol-update');

            if (
                !editorElement ||
                !textarea ||
                !form ||
                !jumlahKata ||
                !editorError ||
                !editorLoadError ||
                !tombolUpdate
            ) {
                console.error('Elemen editor WargaNet tidak ditemukan.');
                return;
            }

            // Periksa apakah Quill berhasil dimuat.
            if (typeof Quill === 'undefined') {

                editorLoadError.classList.remove('d-none');
                tombolUpdate.disabled = true;

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

            // Pulihkan HTML artikel dari database.
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

            // Sinkronkan HTML editor sebelum form dikirim.
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