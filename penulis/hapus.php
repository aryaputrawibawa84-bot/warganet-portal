<?php
session_start();
require '../koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'penulis') {
    header("Location: ../login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id_artikel = mysqli_real_escape_string($koneksi, $_GET['id']);
    $id_user_aktif = $_SESSION['id_user'];

    // Pastikan hanya mencari gambar dari artikel milik penulis ini
    $query_gambar = mysqli_query($koneksi, "SELECT gambar FROM artikel WHERE id_artikel = '$id_artikel' AND id_user = '$id_user_aktif'");
    
    if (mysqli_num_rows($query_gambar) > 0) {
        $data_gambar = mysqli_fetch_assoc($query_gambar);
        
        // Hapus file fisik gambar jika ada
        if (file_exists('../uploads/' . $data_gambar['gambar'])) {
            unlink('../uploads/' . $data_gambar['gambar']);
        }

        // Hapus data dari database
        mysqli_query($koneksi, "DELETE FROM artikel WHERE id_artikel = '$id_artikel' AND id_user = '$id_user_aktif'");
    }
    
    header("Location: index.php");
    exit;
} else {
    header("Location: index.php");
    exit;
}
?>