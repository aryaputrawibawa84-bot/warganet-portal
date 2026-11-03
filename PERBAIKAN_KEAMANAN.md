# Perbaikan Keamanan WargaNet

## Perubahan pada paket ini
- `koneksi.php`: konfigurasi database dapat diatur lewat environment variable, charset `utf8mb4`, dan pesan error koneksi tidak membocorkan detail database. Nama database lama dipertahankan agar project yang sudah diimpor tidak rusak.
- `includes/security.php`: helper escaping HTML, token CSRF, dan validasi ID positif.
- `login.php`: query prepared statement, token CSRF, rotasi session ID setelah login, dan cookie sesi `HttpOnly`/`SameSite`.
- `register.php`: prepared statements, validasi input, password hash, token CSRF, dan error database generik.
- `logout.php`: pembersihan data sesi dan cookie sesi.
- `setup.php`: tidak bisa dijalankan melalui browser; hanya CLI dan meminta kredensial admin yang baru.
- `detail.php`: validasi ID, prepared statement, dan escaping konten artikel untuk mengurangi risiko XSS.

## Sebelum digunakan
1. Simpan salinan folder project dan ekspor database sebelum mengganti file.
2. Pertahankan nama database `db_portal berita/blog komunitas` kecuali kamu juga mengganti nama database dan mengimpor ulang SQL secara terencana.
3. Jika membuat admin baru, jalankan dari terminal di folder project: `php setup.php`. Jangan jalankan setup dari browser.
4. Ganti password admin lama jika masih menggunakan password awal. Hash password pada dump tidak memperlihatkan password asli; jangan mengasumsikan kredensial tertentu.
5. Pada hosting, set `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` di konfigurasi environment dan gunakan HTTPS.

## Checklist pengujian
- Buka `login.php` dan `register.php`; halaman tampil tanpa warning PHP.
- Daftar akun dengan username valid dan password minimal 8 karakter.
- Coba username duplikat, password pendek, dan konfirmasi berbeda; harus ditolak dengan pesan yang aman.
- Login dengan password benar; arah sesuai role dan sesi baru terbentuk.
- Login dengan password salah; hanya pesan umum yang tampil.
- Ubah/hilangkan `csrf_token` pada formulir login/registrasi; permintaan harus ditolak dengan HTTP 403.
- Buka `detail.php?id=abc` dan `detail.php?id=-1`; harus kembali ke beranda tanpa error SQL.
- Buka artikel yang berisi teks seperti `<script>alert(1)</script>` (gunakan data uji); teks harus tampil sebagai teks, tidak dieksekusi.
- Buka `setup.php` melalui browser; harus mendapatkan 404/Not Found.
- Pastikan admin, penulis, pembaca, artikel, kategori, dan gambar lama tetap tersedia.

## Masih perlu dikerjakan sebelum dianggap aman
Paket ini memperkuat autentikasi dan beberapa titik penting, tetapi **belum merupakan audit keamanan lengkap**. Aksi perubahan kategori, pengguna, dan penghapusan artikel sudah ditingkatkan dengan prepared statements/CSRF/POST. Namun, operasi tambah/edit artikel pada `admin/tambah.php`, `admin/edit.php`, `penulis/tambah.php`, dan `penulis/edit.php` masih perlu audit akhir terhadap seluruh query dan validasi MIME/isi file gambar sebelum upload. Periksa juga seluruh output dinamis pada halaman panel untuk memastikan sudah menggunakan `e()` dan pastikan setiap aksi sensitif di semua halaman terlindungi CSRF. Jangan memublikasikan ke internet sebelum item-item tersebut selesai.
