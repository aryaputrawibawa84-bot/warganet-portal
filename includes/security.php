<?php
/**
 * WargaNet - Security Helper
 *
 * Berisi fungsi keamanan, CSRF, sanitasi HTML artikel,
 * validasi input, dan pengelolaan gambar.
 */

/**
 * Mengamankan teks sebelum ditampilkan ke HTML.
 *
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

/**
 * Memvalidasi warna HEX atau RGB/RGBA.
 */
function is_safe_article_color(string $color): bool
{
    $color = trim($color);

    if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color)) {
        return true;
    }

    $pattern = '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/i';

    if (!preg_match($pattern, $color, $matches)) {
        return false;
    }

    for ($i = 1; $i <= 3; $i++) {
        if ((int) $matches[$i] > 255) {
            return false;
        }
    }

    if (isset($matches[4]) && $matches[4] !== '') {
        $opacity = (float) $matches[4];

        if ($opacity < 0 || $opacity > 1) {
            return false;
        }
    }

    return true;
}

/**
 * Sanitasi CSS yang diperbolehkan pada artikel.
 *
 * Hanya warna, warna latar belakang, dan perataan teks
 * yang diperbolehkan.
 */
function sanitize_article_style(string $style): string
{
    $safeStyles = [];
    $declarations = explode(';', $style);

    foreach ($declarations as $declaration) {
        if (strpos($declaration, ':') === false) {
            continue;
        }

        [$property, $value] = explode(':', $declaration, 2);

        $property = strtolower(trim($property));
        $value = trim($value);

        if ($value === '') {
            continue;
        }

        if (in_array($property, ['color', 'background-color'], true)) {
            if (is_safe_article_color($value)) {
                $safeStyles[] = $property . ': ' . $value;
            }

            continue;
        }

        if ($property === 'text-align') {
            $allowedAlignments = [
                'left',
                'center',
                'right',
                'justify'
            ];

            if (in_array(strtolower($value), $allowedAlignments, true)) {
                $safeStyles[] = $property . ': ' . strtolower($value);
            }
        }
    }

    return implode('; ', $safeStyles);
}

/**
 * Memvalidasi URL tautan artikel.
 */
function is_safe_article_url(string $url): bool
{
    $url = trim($url);

    if (
        $url === '' ||
        preg_match('/[\x00-\x20\x7f]/', $url)
    ) {
        return false;
    }

    return (bool) preg_match(
        '~^(?:https?://|mailto:)[^\s<>"\']+$~i',
        $url
    );
}

/**
 * Mengamankan HTML isi artikel dari editor Quill.
 *
 * Tag yang diperbolehkan:
 * p, br, strong, b, em, i, u, s, h2, h3,
 * blockquote, ol, ul, li, a, span, sub, sup.
 *
 * Atribut dibatasi agar tidak dapat menyisipkan
 * JavaScript atau elemen HTML berbahaya.
 */
function sanitize_article_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }

    // Fallback jika ekstensi DOM tidak tersedia.
    if (!class_exists('DOMDocument')) {
        return nl2br(e(strip_tags($html)));
    }

    $allowedTags = [
        'p',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        's',
        'h2',
        'h3',
        'blockquote',
        'ol',
        'ul',
        'li',
        'a',
        'span',
        'sub',
        'sup'
    ];

    $dangerousTags = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'form',
        'input',
        'button',
        'textarea',
        'select',
        'option',
        'svg',
        'math',
        'template',
        'noscript',
        'meta',
        'link',
        'base'
    ];

    // Daftar font & ukuran harus sama dengan assets/js/editor.js
    // dan assets/css/artikel.css.
    $allowedFonts = [
        'serif',
        'monospace',
        'poppins',
        'roboto',
        'montserrat',
        'merriweather',
        'lora',
        'playfair'
    ];

    // 'small', 'large', 'huge' = ukuran lama (artikel yang sudah tersimpan).
    $allowedSizes = [
        'small',
        'large',
        'huge',
        '12px',
        '14px',
        '16px',
        '20px',
        '24px',
        '28px',
        '32px',
        '36px',
        '48px'
    ];

    $allowedClasses = [
        'ql-align-center',
        'ql-align-right',
        'ql-align-justify',
        'ql-ui'
    ];

    foreach ($allowedFonts as $fontName) {
        $allowedClasses[] = 'ql-font-' . $fontName;
    }

    foreach ($allowedSizes as $sizeName) {
        $allowedClasses[] = 'ql-size-' . $sizeName;
    }

    $allowedListTypes = [
        'ordered',
        'bullet',
        'checked',
        'unchecked'
    ];

    $previousErrorState = libxml_use_internal_errors(true);

    $dom = new DOMDocument('1.0', 'UTF-8');

    $htmlDocument = '<?xml encoding="UTF-8">'
        . '<div id="wn-article-root">'
        . $html
        . '</div>';

    $loaded = $dom->loadHTML(
        $htmlDocument,
        LIBXML_HTML_NOIMPLIED
        | LIBXML_HTML_NODEFDTD
        | LIBXML_NONET
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorState);

    if (!$loaded) {
        return nl2br(e(strip_tags($html)));
    }

    // Cari elemen pembungkus utama.
    $root = $dom->getElementById('wn-article-root');

    if (!$root) {
        $divs = $dom->getElementsByTagName('div');

        foreach ($divs as $div) {
            if (
                $div instanceof DOMElement &&
                $div->getAttribute('id') === 'wn-article-root'
            ) {
                $root = $div;
                break;
            }
        }
    }

    if (!$root) {
        return nl2br(e(strip_tags($html)));
    }

    /**
     * Memproses elemen secara rekursif.
     *
     * Elemen berbahaya dihapus beserta isinya.
     * Elemen yang tidak dikenal dibuka pembungkusnya,
     * tetapi isi di dalamnya tetap disanitasi.
     */
    $sanitizeNode = function ($node) use (
        &$sanitizeNode,
        $allowedTags,
        $dangerousTags,
        $allowedClasses,
        $allowedListTypes
    ): void {

        if (!$node->hasChildNodes()) {
            return;
        }

        // Salin daftar anak agar aman saat DOM diubah.
        $children = [];

        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {

            // Hapus komentar HTML.
            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
                continue;
            }

            // Lewati teks biasa dan node selain elemen.
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            // Hapus elemen berbahaya beserta seluruh isinya.
            if (in_array($tag, $dangerousTags, true)) {
                $node->removeChild($child);
                continue;
            }

            // Sanitasi isi elemen terlebih dahulu.
            $sanitizeNode($child);

            // Elemen yang tidak diizinkan dibuka pembungkusnya.
            if (!in_array($tag, $allowedTags, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);
                continue;
            }

            // Tidak ada atribut yang perlu diperiksa.
            if (!$child->hasAttributes()) {
                continue;
            }

            $attributes = [];

            foreach ($child->attributes as $attribute) {
                $attributes[] = $attribute->name;
            }

            foreach ($attributes as $attributeName) {

                $attributeName = strtolower($attributeName);
                $value = $child->getAttribute($attributeName);

                // Atribut data-list hanya untuk elemen LI.
                if ($attributeName === 'data-list') {
                    if (
                        $tag !== 'li' ||
                        !in_array($value, $allowedListTypes, true)
                    ) {
                        $child->removeAttribute($attributeName);
                    }

                    continue;
                }

                // Atribut href hanya untuk elemen A.
                if ($attributeName === 'href') {
                    if (
                        $tag !== 'a' ||
                        !is_safe_article_url($value)
                    ) {
                        $child->removeAttribute($attributeName);
                    }

                    continue;
                }

                // Atribut title hanya diperbolehkan pada tautan.
                if ($attributeName === 'title') {
                    if ($tag !== 'a') {
                        $child->removeAttribute($attributeName);
                    }

                    continue;
                }

                // Batasi class sesuai kebutuhan editor Quill.
                if ($attributeName === 'class') {

                    $classes = preg_split('/\s+/', trim($value));
                    $safeClasses = [];

                    foreach ($classes as $className) {

                        if (!in_array($className, $allowedClasses, true)) {
                            continue;
                        }

                        // ql-ui hanya diperbolehkan pada span.
                        if (
                            $className === 'ql-ui' &&
                            $tag !== 'span'
                        ) {
                            continue;
                        }

                        // Kelas perataan hanya untuk elemen blok.
                        if (
                            strpos($className, 'ql-align-') === 0 &&
                            !in_array(
                                $tag,
                                ['p', 'h2', 'h3', 'blockquote'],
                                true
                            )
                        ) {
                            continue;
                        }

                        // Kelas font dan ukuran hanya untuk span.
                        if (
                            (
                                strpos($className, 'ql-font-') === 0 ||
                                strpos($className, 'ql-size-') === 0
                            ) &&
                            $tag !== 'span'
                        ) {
                            continue;
                        }

                        $safeClasses[] = $className;
                    }

                    if (!empty($safeClasses)) {
                        $child->setAttribute(
                            'class',
                            implode(' ', array_unique($safeClasses))
                        );
                    } else {
                        $child->removeAttribute('class');
                    }

                    continue;
                }

                // Sanitasi CSS inline.
                if ($attributeName === 'style') {

                    $safeStyle = sanitize_article_style($value);

                    if ($safeStyle !== '') {
                        $child->setAttribute('style', $safeStyle);
                    } else {
                        $child->removeAttribute('style');
                    }

                    continue;
                }

                // Semua atribut lainnya tidak diperbolehkan.
                $child->removeAttribute($attributeName);
            }
        }
    };

    $sanitizeNode($root);

    // Ambil isi elemen utama tanpa wrapper.
    $result = '';

    foreach ($root->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }

    return $result;
}

/**
 * Membuat token CSRF.
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        $_SESSION['csrf_token'] === ''
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Memeriksa token CSRF dari form POST.
 */
function csrf_validate(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $sessionToken = $_SESSION['csrf_token'] ?? null;
    $submittedToken = $_POST['csrf_token'] ?? null;

    if (
        !is_string($sessionToken) ||
        !is_string($submittedToken) ||
        $sessionToken === '' ||
        $submittedToken === ''
    ) {
        return false;
    }

    return hash_equals($sessionToken, $submittedToken);
}

/**
 * Menghentikan permintaan jika token CSRF tidak valid.
 */
function require_csrf(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
        !csrf_validate()
    ) {
        http_response_code(403);
        exit('Permintaan ditolak. Muat ulang halaman dan coba kembali.');
    }
}

/**
 * Memvalidasi ID berupa bilangan bulat positif.
 *
 * @param mixed $value
 */
function valid_positive_int($value): ?int
{
    if (
        !is_scalar($value) ||
        is_bool($value) ||
        filter_var($value, FILTER_VALIDATE_INT) === false
    ) {
        return null;
    }

    $number = (int) $value;

    return $number > 0 ? $number : null;
}

/**
 * Menyimpan gambar JPG/JPEG/PNG dengan nama acak.
 *
 * Hasil:
 * [
 *     'filename' => nama file atau null,
 *     'error' => pesan error atau null
 * ]
 */
function save_uploaded_image(
    array $file,
    string $targetDirectory
): array {

    $result = [
        'filename' => null,
        'error' => null
    ];

    if (
        !isset($file['error']) ||
        !is_int($file['error'])
    ) {
        $result['error'] = 'Data upload tidak valid.';
        return $result;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {

        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran gambar melebihi batas server.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran gambar terlalu besar.',
            UPLOAD_ERR_PARTIAL => 'Gambar hanya terunggah sebagian.',
            UPLOAD_ERR_NO_FILE => 'Silakan pilih gambar terlebih dahulu.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara server tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Gambar gagal disimpan.',
            UPLOAD_ERR_EXTENSION => 'Upload gambar dihentikan server.'
        ];

        $result['error'] = $errors[$file['error']]
            ?? 'Terjadi kesalahan saat mengunggah gambar.';

        return $result;
    }

    if (
        !isset($file['tmp_name']) ||
        !is_string($file['tmp_name']) ||
        $file['tmp_name'] === '' ||
        !is_uploaded_file($file['tmp_name'])
    ) {
        $result['error'] = 'File upload tidak valid.';
        return $result;
    }

    // Periksa ukuran file sebenarnya.
    $actualSize = filesize($file['tmp_name']);

    if ($actualSize === false || $actualSize <= 0) {
        $result['error'] = 'Ukuran gambar tidak valid.';
        return $result;
    }

    // Batas ukuran gambar 2 MB.
    if ($actualSize > 2 * 1024 * 1024) {
        $result['error'] = 'Ukuran gambar maksimal 2 MB.';
        return $result;
    }

    // Pastikan ekstensi fileinfo tersedia.
    if (!class_exists('finfo')) {
        $result['error'] = 'Server tidak mendukung pemeriksaan tipe gambar.';
        return $result;
    }

    // Periksa MIME berdasarkan isi file, bukan nama file.
    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
    } catch (Throwable $exception) {
        error_log(
            'WargaNet image MIME check failed: ' .
            $exception->getMessage()
        );

        $result['error'] = 'Gambar gagal diperiksa.';
        return $result;
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        $result['error'] = 'Format gambar harus JPG, JPEG, atau PNG.';
        return $result;
    }

    // Periksa struktur gambar.
    $imageInfo = @getimagesize($file['tmp_name']);

    if (
        $imageInfo === false ||
        !isset($imageInfo['mime'], $imageInfo[0], $imageInfo[1]) ||
        $imageInfo['mime'] !== $mimeType ||
        $imageInfo[0] <= 0 ||
        $imageInfo[1] <= 0
    ) {
        $result['error'] = 'File bukan gambar yang valid.';
        return $result;
    }

    // Batasi dimensi gambar.
    if (
        $imageInfo[0] > 8000 ||
        $imageInfo[1] > 8000 ||
        ($imageInfo[0] * $imageInfo[1]) > 40000000
    ) {
        $result['error'] = 'Dimensi gambar terlalu besar.';
        return $result;
    }

    // Buat folder tujuan jika belum tersedia.
    if (
        !is_dir($targetDirectory) &&
        !mkdir($targetDirectory, 0755, true) &&
        !is_dir($targetDirectory)
    ) {
        $result['error'] = 'Folder penyimpanan gambar gagal dibuat.';
        return $result;
    }

    $realDirectory = realpath($targetDirectory);

    if (
        $realDirectory === false ||
        !is_dir($realDirectory) ||
        !is_writable($realDirectory)
    ) {
        $result['error'] = 'Folder penyimpanan gambar tidak dapat digunakan.';
        return $result;
    }

    // Buat nama file acak.
    try {
        $randomName = bin2hex(random_bytes(16));
    } catch (Throwable $exception) {
        error_log(
            'WargaNet image random name failed: ' .
            $exception->getMessage()
        );

        $result['error'] = 'Gagal membuat nama file gambar.';
        return $result;
    }

    $extension = $allowedMimeTypes[$mimeType];
    $filename = $randomName . '.' . $extension;

    $destination = $realDirectory
        . DIRECTORY_SEPARATOR
        . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $result['error'] = 'Gambar gagal dipindahkan ke folder penyimpanan.';
        return $result;
    }

    // Atur izin file.
    @chmod($destination, 0644);

    $result['filename'] = $filename;

    return $result;
}

/**
 * Menghapus gambar dengan pemeriksaan lokasi file.
 *
 * File hanya dihapus jika benar-benar berada
 * di dalam direktori yang ditentukan.
 */
function delete_uploaded_image(
    ?string $filename,
    string $directory
): void {

    if ($filename === null || $filename === '') {
        return;
    }

    // Tolak nama file yang mengandung pemisah direktori.
    if (
        strpos($filename, '/') !== false ||
        strpos($filename, '\\') !== false ||
        strpos($filename, "\0") !== false
    ) {
        return;
    }

    $baseDirectory = realpath($directory);

    if (
        $baseDirectory === false ||
        !is_dir($baseDirectory)
    ) {
        return;
    }

    $filePath = realpath(
        $baseDirectory . DIRECTORY_SEPARATOR . $filename
    );

    if (
        $filePath === false ||
        !is_file($filePath)
    ) {
        return;
    }

    // Pastikan file berada di dalam folder yang ditentukan.
    $prefix = rtrim($baseDirectory, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR;

    if (strpos($filePath, $prefix) !== 0) {
        return;
    }

    if (!@unlink($filePath)) {
        error_log(
            'WargaNet failed to delete uploaded image: ' .
            $filename
        );
    }
}