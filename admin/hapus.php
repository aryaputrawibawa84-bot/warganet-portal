<?php
session_start();
require '../koneksi.php';

// Pastikan hanya admin yang bisa mengeksekusi file ini
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Cek apakah parameter ID artikel dikirim melalui URL
if (isset($_GET['id'])) {
    $id_artikel = mysqli_real_escape_string($koneksi, $_GET['id']);

    // 1. Ambil nama file gambar dari database sebelum data dihapus
    $query_gambar = mysqli_query($koneksi, "SELECT gambar FROM artikel WHERE id_artikel = '$id_artikel'");
    $data_gambar = mysqli_fetch_assoc($query_gambar);
    
    // 2. Hapus file gambar fisik dari folder uploads (mencegah penumpukan file sampah)
    if ($data_gambar && file_exists('../uploads/' . $data_gambar['gambar'])) {
        unlink('../uploads/' . $data_gambar['gambar']);
    }

    // 3. Eksekusi query hapus data artikel dari tabel database
    $hapus = mysqli_query($koneksi, "DELETE FROM artikel WHERE id_artikel = '$id_artikel'");

    if ($hapus) {
        // Jika berhasil, kembalikan admin ke halaman dasbor
        header("Location: index.php");
        exit;
    } else {
        echo "Gagal menghapus data: " . mysqli_error($koneksi);
    }
} else {
    // Jika file diakses langsung tanpa ID, lempar kembali ke dasbor
    header("Location: index.php");
    exit;
}
?>