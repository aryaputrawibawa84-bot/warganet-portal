<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $https,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../koneksi.php';

if (!isset($_SESSION['id_user'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Tab status yang diizinkan (kunci = nilai di URL / database)
$tab_valid = ['semua' => 'Semua', 'pending' => 'Pending', 'terbit' => 'Terbit', 'ditolak' => 'Ditolak', 'draf' => 'Draf'];

// Filter dari URL (selalu divalidasi)
$tab = (string) ($_GET['status'] ?? 'semua');
if (!isset($tab_valid[$tab])) {
    $tab = 'semua';
}
$keyword = trim((string) ($_GET['cari'] ?? ''));
if (mb_strlen($keyword) > 100) {
    $keyword = mb_substr($keyword, 0, 100);
}

// Bangun alamat halaman daftar dengan filter yang sedang aktif
function url_daftar(string $tab, string $keyword, array $extra = []): string {
    $q = [];
    if ($tab !== 'semua') { $q['status'] = $tab; }
    if ($keyword !== '')  { $q['cari'] = $keyword; }
    $q = array_merge($q, $extra);
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

// ---------- Aksi setujui / tolak (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ubah_status'])) {
    require_csrf();

    $id = valid_positive_int($_POST['id_artikel'] ?? null);
    $aksi = (string) ($_POST['aksi'] ?? '');
    $peta = ['setujui' => 'terbit', 'tolak' => 'ditolak'];

    // Filter yang aktif saat tombol ditekan, supaya admin kembali ke tampilan yang sama
    $tab_kembali = (string) ($_POST['tab'] ?? 'semua');
    if (!isset($tab_valid[$tab_kembali])) { $tab_kembali = 'semua'; }
    $cari_kembali = mb_substr(trim((string) ($_POST['cari'] ?? '')), 0, 100);

    $info = 'gagal';
    if ($id !== null && isset($peta[$aksi])) {
        $status_baru = $peta[$aksi];
        $upd = mysqli_prepare($koneksi, 'UPDATE artikel SET status = ? WHERE id_artikel = ?');
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'si', $status_baru, $id);
            if (mysqli_stmt_execute($upd)) {
                $info = ($aksi === 'setujui') ? 'disetujui' : 'ditolak';
            } else {
                error_log('WargaNet gagal mengubah status artikel: ' . mysqli_stmt_error($upd));
            }
            mysqli_stmt_close($upd);
        }
    }
    header('Location: ' . url_daftar($tab_kembali, $cari_kembali, ['info' => $info]));
    exit;
}

// ---------- Ringkasan angka (dari seluruh database, tidak terpengaruh filter) ----------
$hitung = ['pending' => 0, 'terbit' => 0, 'ditolak' => 0, 'draf' => 0];
$rs = mysqli_query($koneksi, 'SELECT status, COUNT(*) AS n FROM artikel GROUP BY status');
if ($rs) {
    while ($r = mysqli_fetch_assoc($rs)) {
        if (isset($hitung[$r['status']])) { $hitung[$r['status']] = (int) $r['n']; }
    }
}
$total_artikel = array_sum($hitung);
$total_views = 0;
$rv = mysqli_query($koneksi, "SELECT COALESCE(SUM(views), 0) FROM artikel WHERE status = 'terbit'");
if ($rv) { $baris_v = mysqli_fetch_row($rv); $total_views = (int) $baris_v[0]; }

// ---------- Daftar artikel (prepared statement) ----------
$sql = "SELECT artikel.*, kategori.nama_kategori, users.nama_lengkap
        FROM artikel
        LEFT JOIN kategori ON artikel.id_kategori = kategori.id_kategori
        LEFT JOIN users ON artikel.id_user = users.id_user";
$where = [];
$tipe = '';
$param = [];
if ($tab !== 'semua') {
    $where[] = 'artikel.status = ?';
    $tipe .= 's';
    $param[] = $tab;
}
if ($keyword !== '') {
    $where[] = "artikel.judul LIKE ? ESCAPE '\\\\'";
    $tipe .= 's';
    $param[] = '%' . addcslashes($keyword, '%_\\') . '%';
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
// Di tab "Semua", artikel Pending ditaruh paling atas supaya cepat ditinjau
$sql .= ($tab === 'semua')
    ? " ORDER BY (artikel.status = 'pending') DESC, artikel.tanggal_dibuat DESC"
    : ' ORDER BY artikel.tanggal_dibuat DESC';

$stmt = mysqli_prepare($koneksi, $sql);
if ($tipe !== '') {
    mysqli_stmt_bind_param($stmt, $tipe, ...$param);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$jumlah_baris = $result ? mysqli_num_rows($result) : 0;

// Pesan hasil aksi (hanya nilai yang dikenal)
$info = (string) ($_GET['info'] ?? '');
$pesan_info = [
    'disetujui' => ['success', 'Artikel disetujui dan sekarang tampil di halaman publik.'],
    'ditolak'   => ['warning', 'Artikel ditolak dan tidak tampil di halaman publik.'],
    'gagal'     => ['danger',  'Perubahan status gagal. Silakan coba kembali.'],
][$info] ?? null;
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#0B1120">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Artikel - WargaNet Panel</title>
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
            <a class="navbar-brand fw-bold" href="index.php">WargaNet Panel</a>
            <span class="navbar-text">Halo, <?= e($_SESSION['nama_lengkap'] ?? ''); ?>!</span>
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

            <div class="col-md-10 px-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Daftar Artikel</h3>
                    <a href="tambah.php" class="btn btn-primary">+ Tulis Artikel Baru</a>
                </div>

                <!-- Kartu ringkasan -->
                <div class="wn-stats wn-stats--panel">
                    <div class="wn-stat"><i class="bi bi-journal-text"></i><b data-count="<?= $total_artikel; ?>">0</b><span>Total artikel</span></div>
                    <div class="wn-stat"><i class="bi bi-hourglass-split"></i><b data-count="<?= $hitung['pending']; ?>">0</b><span>Menunggu review</span></div>
                    <div class="wn-stat"><i class="bi bi-check2-circle"></i><b data-count="<?= $hitung['terbit']; ?>">0</b><span>Terbit</span></div>
                    <div class="wn-stat"><i class="bi bi-eye"></i><b data-count="<?= $total_views; ?>">0</b><span>Total dibaca</span></div>
                </div>

                <?php if ($pesan_info) : ?>
                    <div class="alert alert-<?= e($pesan_info[0]); ?> bg-transparent py-2">
                        <?= e($pesan_info[1]); ?>
                    </div>
                <?php endif; ?>

                <!-- Tab status -->
                <div class="wn-tabs">
                    <?php foreach ($tab_valid as $kunci => $label) : ?>
                        <?php $angka = ($kunci === 'semua') ? $total_artikel : $hitung[$kunci]; ?>
                        <a href="<?= e(url_daftar($kunci, $keyword)); ?>" class="wn-tab <?= ($tab === $kunci) ? 'active' : ''; ?>">
                            <?= e($label); ?> <small><?= (int) $angka; ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Pencarian -->
                <form action="" method="GET" class="mb-4 d-flex gap-2 wn-search">
                    <?php if ($tab !== 'semua') : ?>
                        <input type="hidden" name="status" value="<?= e($tab); ?>">
                    <?php endif; ?>
                    <input type="text" name="cari" class="form-control" maxlength="100" placeholder="Cari judul..." value="<?= e($keyword); ?>">
                    <button type="submit" class="btn btn-outline-secondary">Cari</button>
                    <?php if ($keyword !== '') : ?>
                        <a href="<?= e(url_daftar($tab, '')); ?>" class="btn btn-outline-danger">Reset</a>
                    <?php endif; ?>
                </form>

                <div class="card card-dark shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-dark table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Judul Artikel</th>
                                    <th>Kategori</th>
                                    <th>Penulis</th>
                                    <th>Status</th>
                                    <th>Dibaca</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; if ($jumlah_baris > 0) : while ($row = mysqli_fetch_assoc($result)) : ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td class="wn-title-cell">
                                        <?php if ($row['status'] === 'terbit') : ?>
                                            <a href="../detail.php?id=<?= (int) $row['id_artikel']; ?>" target="_blank" class="text-white text-decoration-none"><?= e($row['judul']); ?></a>
                                        <?php else : ?>
                                            <?= e($row['judul']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($row['nama_kategori'] ?? 'Tanpa Kategori'); ?></td>
                                    <td><?= e($row['nama_lengkap'] ?? '-'); ?></td>
                                    <td>
                                        <?php
                                        if ($row['status'] === 'terbit') { echo '<span class="badge bg-success">Terbit</span>'; }
                                        elseif ($row['status'] === 'pending') { echo '<span class="badge bg-warning text-dark">Pending</span>'; }
                                        elseif ($row['status'] === 'ditolak') { echo '<span class="badge bg-danger">Ditolak</span>'; }
                                        else { echo '<span class="badge bg-secondary">' . e(ucfirst((string) $row['status'])) . '</span>'; }
                                        ?>
                                    </td>
                                    <td><i class="bi bi-eye text-secondary"></i> <?= number_format((int) $row['views'], 0, ',', '.'); ?></td>
                                    <td><?= date('d M Y', strtotime($row['tanggal_dibuat'])); ?></td>
                                    <td>
                                        <div class="wn-actions">
                                            <?php if ($row['status'] !== 'terbit') : ?>
                                                <form method="POST" action="index.php">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                    <input type="hidden" name="id_artikel" value="<?= (int) $row['id_artikel']; ?>">
                                                    <input type="hidden" name="aksi" value="setujui">
                                                    <input type="hidden" name="tab" value="<?= e($tab); ?>">
                                                    <input type="hidden" name="cari" value="<?= e($keyword); ?>">
                                                    <button type="submit" name="ubah_status" value="1" class="btn btn-sm btn-success" title="Setujui &amp; terbitkan">
                                                        <i class="bi bi-check-lg"></i> Setujui
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($row['status'] !== 'ditolak') : ?>
                                                <?php $tarik = ($row['status'] === 'terbit'); ?>
                                                <form method="POST" action="index.php"
                                                      <?= $tarik ? 'onsubmit="return confirm(\'Tarik artikel ini dari halaman publik?\');"' : ''; ?>>
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                    <input type="hidden" name="id_artikel" value="<?= (int) $row['id_artikel']; ?>">
                                                    <input type="hidden" name="aksi" value="tolak">
                                                    <input type="hidden" name="tab" value="<?= e($tab); ?>">
                                                    <input type="hidden" name="cari" value="<?= e($keyword); ?>">
                                                    <button type="submit" name="ubah_status" value="1" class="btn btn-sm btn-outline-danger"
                                                            title="<?= $tarik ? 'Tarik dari publik' : 'Tolak artikel'; ?>">
                                                        <i class="bi bi-x-lg"></i> <?= $tarik ? 'Tarik' : 'Tolak'; ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <a href="edit.php?id=<?= (int) $row['id_artikel']; ?>" class="btn btn-sm btn-warning">Edit</a>

                                            <form action="hapus.php" method="POST" onsubmit="return confirm('Hapus artikel ini secara permanen?');">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="id_artikel" value="<?= (int) $row['id_artikel']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; else : ?>
                                <tr><td colspan="8" class="text-center py-4 text-secondary">
                                    <?= ($keyword !== '' || $tab !== 'semua') ? 'Tidak ada artikel yang cocok dengan filter ini.' : 'Belum ada data artikel.'; ?>
                                </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/warganet.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/warganet.js'); ?>"></script>
</body>
</html>