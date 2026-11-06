
<?php

// Batasi agar file hanya dapat dijalankan melalui terminal (CLI).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Halaman tidak ditemukan.');
}

require_once __DIR__ . '/koneksi.php';

// Fungsi untuk membaca input terminal.
function input_terminal(string $pesan): string
{
    echo $pesan;
    return trim((string) fgets(STDIN));
}

echo "====================================\n";
echo "       SETUP ADMIN WARGANET\n";
echo "====================================\n\n";

// Ambil data admin baru.
$nama = input_terminal("Nama lengkap admin: ");
$username = input_terminal("Username admin baru: ");
$password = input_terminal("Password admin baru: ");
$konfirmasi = input_terminal("Konfirmasi password: ");

echo "\n";

// Validasi nama.
if ($nama === '' || strlen($nama) > 100) {
    exit("Gagal: Nama wajib diisi dan maksimal 100 karakter.\n");
}

// Validasi username.
if (
    !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)
) {
    exit("Gagal: Username harus 3-50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda minus.\n");
}

// Validasi password.
if (strlen($password) < 6) {
    exit("Gagal: Password admin minimal 6 karakter.\n");
}

if ($password !== $konfirmasi) {
    exit("Gagal: Konfirmasi password tidak cocok.\n");
}

// Periksa apakah username sudah digunakan.
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id_user FROM users WHERE username = ? LIMIT 1"
);

if (!$stmt) {
    error_log("WargaNet setup: gagal menyiapkan query pemeriksaan username.");
    exit("Gagal memeriksa username. Periksa konfigurasi database.\n");
}

mysqli_stmt_bind_param($stmt, 's', $username);

if (!mysqli_stmt_execute($stmt)) {
    error_log("WargaNet setup: gagal memeriksa username.");
    mysqli_stmt_close($stmt);
    exit("Gagal memeriksa username.\n");
}

mysqli_stmt_store_result($stmt);
$username_ada = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if ($username_ada) {
    exit("Gagal: Username tersebut sudah digunakan. Pilih username lain.\n");
}

// Hash password sebelum disimpan.
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Simpan admin baru ke database.
$stmt = mysqli_prepare(
    $koneksi,
    "INSERT INTO users (nama_lengkap, username, password, role)
     VALUES (?, ?, ?, 'admin')"
);

if (!$stmt) {
    error_log("WargaNet setup: gagal menyiapkan query INSERT.");
    exit("Gagal menyiapkan penyimpanan admin.\n");
}

mysqli_stmt_bind_param(
    $stmt,
    'sss',
    $nama,
    $username,
    $password_hash
);

$berhasil = mysqli_stmt_execute($stmt);

if (!$berhasil) {
    error_log("WargaNet setup: gagal menyimpan admin.");
    mysqli_stmt_close($stmt);
    exit("Gagal menyimpan akun admin. Periksa database.\n");
}

mysqli_stmt_close($stmt);

echo "====================================\n";
echo "Akun admin berhasil dibuat!\n";
echo "Username: " . $username . "\n";
echo "Role: admin\n";
echo "====================================\n";

