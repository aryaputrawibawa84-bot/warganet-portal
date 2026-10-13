<?php
require 'koneksi.php';
$password_hashed = password_hash('admin123', PASSWORD_DEFAULT);
$query = "INSERT INTO users (nama_lengkap, username, password, role) 
          VALUES ('Administrator Utama', 'admin', '$password_hashed', 'admin')";

if (mysqli_query($koneksi, $query)) {
    echo "Akun admin berhasil dibuat! Username: admin | Password: admin123";
} else {
    echo "Gagal: " . mysqli_error($koneksi);
}
?>