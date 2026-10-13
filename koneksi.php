<?php
$koneksi = mysqli_connect("localhost", "root", "", "db_portal berita/blog komunitas");

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>