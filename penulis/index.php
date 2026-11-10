<?php
// 1. Pengaturan session cookie
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

// 2. Pastikan hanya penulis yang dapat mengakses
$id_user_aktif = valid_positive_int($_SESSION['id_user'] ?? null);

if (
    $id_user_aktif === null ||
    ($_SESSION['role'] ?? '') !== 'penulis'
) {
    header('Location: ../login.php');
    exit;
}

// 3. Ambil kata kunci pencarian
$keyword = trim((string) ($_GET['cari'] ?? ''));

// Batasi panjang kata kunci
if (function_exists('mb_substr')) {
    $keyword = mb_substr($keyword, 0, 100, 'UTF-8');
} else {
    $keyword = substr($keyword, 0, 100);
}

// 4. Siapkan query artikel milik penulis aktif
$sql = "
    SELECT
        artikel.id_artikel,
        artikel.judul,
        artikel.status,
        artikel.tanggal_dibuat,
        kategori.nama_kategori
    FROM artikel
    LEFT JOIN kategori
        ON artikel.id_kategori = kategori.id_kategori
    WHERE artikel.id_user = ?
";

if ($keyword !== '') {
    $sql .= " AND artikel.judul LIKE ?";
}

$sql .= " ORDER BY artikel.tanggal_dibuat DESC, artikel.id_artikel DESC";

// 5. Jalankan query menggunakan prepared statement
$stmt = mysqli_prepare($koneksi, $sql);

if (!$stmt) {
    error_log(
        'WargaNet author index prepare failed: ' .
        mysqli_error($koneksi)
    );

    http_response_code(500);
    exit('Terjadi kesalahan saat mengambil data artikel.');
}

if ($keyword !== '') {
    $like = '%' . $keyword . '%';

    $bind_berhasil = mysqli_stmt_bind_param(
        $stmt,
        'is',
        $id_user_aktif,
        $like
    );
} else {
    $bind_berhasil = mysqli_stmt_bind_param(
        $stmt,
        'i',
        $id_user_aktif
    );
}

if (!$bind_berhasil) {
    error_log(
        'WargaNet author index bind failed: ' .
        mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('Terjadi kesalahan saat mengambil data artikel.');
}

if (!mysqli_stmt_execute($stmt)) {
    error_log(
        'WargaNet author index execute failed: ' .
        mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('Terjadi kesalahan saat mengambil data artikel.');
}

// 6. Ambil hasil query
$result = mysqli_stmt_get_result($stmt);

if ($result === false) {
    error_log(
        'WargaNet author index get_result failed: ' .
        mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('Terjadi kesalahan saat mengambil data artikel.');
}

// Simpan hasil ke array agar statement dapat ditutup sebelum HTML
$daftar_artikel = [];

while ($row = mysqli_fetch_assoc($result)) {
    $daftar_artikel[] = $row;
}

mysqli_free_result($result);
mysqli_stmt_close($stmt);

// 7. Fungsi untuk menampilkan status artikel
function label_status_artikel(string $status): string
{
    $status = strtolower(trim($status));

    $daftar_status = [
        'terbit' => [
            'label' => 'Terbit',
            'class' => 'bg-success'
        ],
        'pending' => [
            'label' => 'Pending',
            'class' => 'bg-warning text-dark'
        ],
        'draf' => [
            'label' => 'Draf',
            'class' => 'bg-secondary'
        ],
        'ditolak' => [
            'label' => 'Ditolak',
            'class' => 'bg-danger'
        ]
    ];

    if (isset($daftar_status[$status])) {
        $label = $daftar_status[$status]['label'];
        $class = $daftar_status[$status]['class'];
    } else {
        $label = $status !== '' ? ucfirst($status) : 'Tidak diketahui';
        $class = 'bg-secondary';
    }

    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

// 8. Fungsi tanggal agar tidak menampilkan tanggal yang salah.
function format_tanggal_artikel(?string $tanggal): string
{
    if (empty($tanggal)) {
        return '-';
    }

    $timestamp = strtotime($tanggal);

    if ($timestamp === false) {
        return '-';
    }

    return date('d M Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ruang Redaksi - WargaNet</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/warganet.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/warganet.css'); ?>">

    <script>
        document.documentElement.classList.add('js');
    </script>
</head>

<body class="wn-panel">

    <!-- Navbar -->
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

            <!-- Sidebar -->
            <div class="col-md-2">

                <div class="list-group list-group-flush border-end border-secondary h-100">

                    <a href="index.php"
                       class="list-group-item list-group-item-action bg-secondary text-white">
                        Artikel Saya
                    </a>

                    <a href="../index.php"
                       class="list-group-item list-group-item-action bg-transparent text-light"
                       target="_blank"
                       rel="noopener noreferrer">
                        Lihat Web Publik
                    </a>

                    <form action="../logout.php" method="POST" class="mt-5">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= e(csrf_token()); ?>">

                        <button type="submit"
                                class="list-group-item list-group-item-action text-danger bg-transparent w-100 text-start border-0">
                            Logout
                        </button>

                    </form>

                </div>

            </div>

            <!-- Konten utama -->
            <div class="col-md-10 px-4">

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">

                    <h3 class="mb-0">
                        Karya Tulisan Saya
                    </h3>

                    <a href="tambah.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>
                        Tulis Berita Baru
                    </a>

                </div>

                <div class="alert alert-info bg-transparent border-info text-info py-2 mb-4">

                    <strong>Catatan:</strong>
                    Artikel yang baru Anda tulis akan berstatus
                    <em>Pending</em> hingga disetujui oleh Administrator.

                </div>

                <!-- Pencarian artikel -->
                <div class="card card-dark shadow-sm mb-4">

                    <div class="card-body">

                        <form action="index.php" method="GET">

                            <div class="row g-2">

                                <div class="col-md-9">

                                    <input
                                        type="search"
                                        name="cari"
                                        class="form-control"
                                        placeholder="Cari judul artikel..."
                                        value="<?= e($keyword); ?>"
                                        maxlength="100">

                                </div>

                                <div class="col-md-3 d-flex gap-2">

                                    <button type="submit" class="btn btn-info w-100">
                                        <i class="bi bi-search me-1"></i>
                                        Cari
                                    </button>

                                    <?php if ($keyword !== '') : ?>
                                        <a href="index.php"
                                           class="btn btn-outline-secondary"
                                           title="Reset pencarian">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                    <?php endif; ?>

                                </div>

                            </div>

                        </form>

                    </div>

                </div>

                <!-- Tabel artikel -->
                <div class="card card-dark shadow-sm mb-5">

                    <div class="card-body p-0">

                        <div class="table-responsive">

                            <table class="table table-dark table-hover table-striped mb-0">

                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Judul Artikel</th>
                                        <th>Kategori</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                        <th style="min-width: 150px;">Aksi</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php if (!empty($daftar_artikel)) : ?>

                                        <?php $no = 1; ?>

                                        <?php foreach ($daftar_artikel as $row) : ?>

                                            <tr>

                                                <td>
                                                    <?= $no++; ?>
                                                </td>

                                                <td>
                                                    <?= e($row['judul'] ?? ''); ?>
                                                </td>

                                                <td>
                                                    <?= e($row['nama_kategori'] ?? 'Tanpa Kategori'); ?>
                                                </td>

                                                <td>
                                                    <?= label_status_artikel(
                                                        (string) ($row['status'] ?? '')
                                                    ); ?>
                                                </td>

                                                <td>
                                                    <?= e(format_tanggal_artikel(
                                                        $row['tanggal_dibuat'] ?? ''
                                                    )); ?>
                                                </td>

                                                <td>

                                                    <div class="d-flex flex-wrap gap-1">

                                                        <a
                                                            href="edit.php?id=<?= (int) $row['id_artikel']; ?>"
                                                            class="btn btn-sm btn-warning">

                                                            <i class="bi bi-pencil-square"></i>
                                                            Edit

                                                        </a>

                                                        <form
                                                            action="hapus.php"
                                                            method="POST"
                                                            class="d-inline"
                                                            onsubmit="return confirm('Apakah Anda yakin ingin menghapus artikel ini?');">

                                                            <input
                                                                type="hidden"
                                                                name="csrf_token"
                                                                value="<?= e(csrf_token()); ?>">

                                                            <input
                                                                type="hidden"
                                                                name="id_artikel"
                                                                value="<?= (int) $row['id_artikel']; ?>">

                                                            <button
                                                                type="submit"
                                                                class="btn btn-sm btn-danger">

                                                                <i class="bi bi-trash"></i>
                                                                Hapus

                                                            </button>

                                                        </form>

                                                    </div>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    <?php else : ?>

                                        <tr>
                                            <td colspan="6" class="text-center py-4">

                                                <?php if ($keyword !== '') : ?>

                                                    <i class="bi bi-search fs-3 d-block mb-2"></i>

                                                    Tidak ditemukan artikel dengan judul
                                                    "<strong><?= e($keyword); ?></strong>".

                                                <?php else : ?>

                                                    <i class="bi bi-journal-text fs-3 d-block mb-2"></i>

                                                    Anda belum menulis artikel.

                                                    <div class="mt-2">
                                                        <a href="tambah.php" class="btn btn-sm btn-primary">
                                                            Mulai Menulis
                                                        </a>
                                                    </div>

                                                <?php endif; ?>

                                            </td>
                                        </tr>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>

            </div>

        </div>
    </div>

    <script src="../assets/js/warganet.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/warganet.js'); ?>"></script>

</body>
</html>