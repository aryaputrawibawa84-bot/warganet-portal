```php
<?php
/**
 * Helper keamanan sederhana untuk WargaNet.
 */

// 1. Mencegah XSS saat menampilkan data

/**
 * @param mixed $value
 */
function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


// 2. Membuat token CSRF

function csrf_token(): string
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        $_SESSION['csrf_token'] === ''
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


// 3. Memvalidasi token CSRF

function csrf_validate(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? null;

    return is_string($submitted)
        && is_string($sessionToken)
        && $sessionToken !== ''
        && hash_equals($sessionToken, $submitted);
}


// 4. Menolak permintaan tanpa token CSRF valid

function require_csrf(): void
{
    if (!csrf_validate()) {
        http_response_code(403);
        exit('Permintaan ditolak. Muat ulang halaman dan coba kembali.');
    }
}


// 5. Memvalidasi bilangan bulat positif

/**
 * @param mixed $value
 */
function valid_positive_int($value): ?int
{
    $number = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    return $number === false ? null : $number;
}


// 6. Memvalidasi dan menyimpan gambar JPG/PNG

function save_uploaded_image(array $file, string $targetDirectory): array
{
    // Memeriksa data upload
    if (!isset($file['error']) || is_array($file['error'])) {
        return [
            'filename' => null,
            'error' => 'Data upload tidak valid.'
        ];
    }

    // Memeriksa status upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran gambar melebihi batas server.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran gambar terlalu besar.',
            UPLOAD_ERR_PARTIAL => 'Gambar hanya terunggah sebagian.',
            UPLOAD_ERR_NO_FILE => 'Pilih gambar sampul terlebih dahulu.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara upload tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menyimpan gambar.',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh konfigurasi server.'
        ];

        return [
            'filename' => null,
            'error' => $messages[$file['error']] ?? 'Upload gambar gagal.'
        ];
    }

    // Memeriksa file sementara
    if (
        !isset($file['tmp_name'], $file['size']) ||
        !is_string($file['tmp_name']) ||
        $file['tmp_name'] === '' ||
        !is_uploaded_file($file['tmp_name'])
    ) {
        return [
            'filename' => null,
            'error' => 'File upload tidak valid.'
        ];
    }

    // Memastikan ukuran file berupa angka
    if (!is_numeric($file['size'])) {
        return [
            'filename' => null,
            'error' => 'Ukuran gambar tidak valid.'
        ];
    }

    $fileSize = (int) $file['size'];

    // Maksimal ukuran gambar 2 MB
    if ($fileSize < 1 || $fileSize > 2 * 1024 * 1024) {
        return [
            'filename' => null,
            'error' => 'Ukuran gambar maksimal 2MB.'
        ];
    }

    // Memeriksa MIME gambar
    if (!class_exists('finfo')) {
        error_log('WargaNet upload failed: PHP fileinfo extension is unavailable.');

        return [
            'filename' => null,
            'error' => 'Server belum mendukung pemeriksaan gambar.'
        ];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    if (!isset($allowed[$mime])) {
        return [
            'filename' => null,
            'error' => 'Format gambar harus JPG atau PNG.'
        ];
    }

    // Memeriksa isi file benar-benar gambar
    $imageInfo = @getimagesize($file['tmp_name']);

    if (
        $imageInfo === false ||
        !isset($imageInfo['mime']) ||
        $imageInfo['mime'] !== $mime
    ) {
        return [
            'filename' => null,
            'error' => 'Isi file bukan gambar yang valid.'
        ];
    }

    // Membuat folder upload jika belum tersedia
    if (
        !is_dir($targetDirectory) &&
        !mkdir($targetDirectory, 0755, true)
    ) {
        return [
            'filename' => null,
            'error' => 'Folder penyimpanan gambar tidak tersedia.'
        ];
    }

    // Memeriksa izin folder
    if (!is_writable($targetDirectory)) {
        return [
            'filename' => null,
            'error' => 'Folder penyimpanan gambar tidak dapat ditulis.'
        ];
    }

    // Membuat nama file acak
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

    $destination = rtrim(
        $targetDirectory,
        DIRECTORY_SEPARATOR
    ) . DIRECTORY_SEPARATOR . $filename;

    // Memindahkan gambar ke folder uploads
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'filename' => null,
            'error' => 'Gambar gagal disimpan.'
        ];
    }

    return [
        'filename' => $filename,
        'error' => null
    ];
}


/**
 * Hapus file gambar di folder upload dengan aman
 * untuk mencegah path traversal.
 */

function delete_uploaded_image(?string $filename, string $directory): void
{
    if ($filename === null || $filename === '') {
        return;
    }

    $base = realpath($directory);

    if ($base === false) {
        return;
    }

    $file = realpath(
        $base . DIRECTORY_SEPARATOR . basename($filename)
    );

    if (
        $file !== false &&
        strpos($file, $base . DIRECTORY_SEPARATOR) === 0 &&
        is_file($file)
    ) {
        @unlink($file);
    }
}
