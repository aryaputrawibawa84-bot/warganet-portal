<?php
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
session_start();
require_once '../includes/security.php';
require '../koneksi.php';
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'penulis') {
    header('Location: ../login.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
require_csrf();
$id = valid_positive_int($_POST['id_artikel'] ?? null);
if ($id === null) { header('Location: index.php'); exit; }
$userId = (int)$_SESSION['id_user'];
$stmt = mysqli_prepare($koneksi, 'SELECT gambar FROM artikel WHERE id_artikel = ? AND id_user = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $id, $userId); mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt); $row = $res ? mysqli_fetch_assoc($res) : null; mysqli_stmt_close($stmt);
if ($row) {
    $del = mysqli_prepare($koneksi, 'DELETE FROM artikel WHERE id_artikel = ? AND id_user = ?');
    mysqli_stmt_bind_param($del, 'ii', $id, $userId); $ok = mysqli_stmt_execute($del); mysqli_stmt_close($del);
    if ($ok && !empty($row['gambar'])) {
        $name = basename((string)$row['gambar']);
        $path = realpath(__DIR__ . '/../uploads');
        $file = $path ? realpath($path . DIRECTORY_SEPARATOR . $name) : false;
        if ($path && $file && strpos($file, $path . DIRECTORY_SEPARATOR) === 0 && is_file($file)) { @unlink($file); }
    }
}
header('Location: index.php'); exit;
