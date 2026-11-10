<?php
/**
 * Fungsi bantu khusus TAMPILAN (format tanggal, angka, cuplikan teks).
 * Tidak menyentuh database, sesi, maupun keamanan.
 * Hasilnya berupa teks biasa: tetap bungkus dengan e() saat dicetak ke HTML.
 */

if (!function_exists('tanggal_id')) {
    /**
     * Contoh: "6 Nov 2026" (singkat) atau "6 November 2026" (panjang).
     *
     * @param mixed $waktu Timestamp atau teks tanggal.
     * @param bool $panjang Menggunakan nama bulan lengkap jika true.
     */
    function tanggal_id($waktu, bool $panjang = false): string
    {
        $ts = is_numeric($waktu)
            ? (int) $waktu
            : strtotime((string) $waktu);

        if ($ts === false || $ts === 0) {
            return '-';
        }

        $singkat = [
            1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
            'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
        ];

        $penuh = [
            1 => 'Januari', 'Februari', 'Maret', 'April',
            'Mei', 'Juni', 'Juli', 'Agustus', 'September',
            'Oktober', 'November', 'Desember'
        ];

        $bulan = $panjang ? $penuh : $singkat;

        return (int) date('j', $ts)
            . ' ' . $bulan[(int) date('n', $ts)]
            . ' ' . date('Y', $ts);
    }
}

if (!function_exists('hari_tanggal_id')) {
    /**
     * Contoh: "Jumat, 6 November 2026".
     *
     * @param mixed $waktu Timestamp, teks tanggal, atau null untuk hari ini.
     */
    function hari_tanggal_id($waktu = null): string
    {
        $ts = $waktu === null
            ? time()
            : (is_numeric($waktu)
                ? (int) $waktu
                : (int) strtotime((string) $waktu));

        $hari = [
            'Minggu', 'Senin', 'Selasa', 'Rabu',
            'Kamis', 'Jumat', 'Sabtu'
        ];

        return $hari[(int) date('w', $ts)]
            . ', ' . tanggal_id($ts, true);
    }
}

if (!function_exists('angka_id')) {
    /**
     * Contoh: 1234 menjadi "1.234".
     *
     * @param mixed $angka Angka yang akan diformat.
     */
    function angka_id($angka): string
    {
        return number_format((int) $angka, 0, ',', '.');
    }
}

if (!function_exists('cuplikan')) {
    /**
     * Ringkasan teks polos dari isi artikel, dipotong rapi di batas kata.
     *
     * @param mixed $teks Isi teks yang akan diringkas.
     * @param int $maks Batas panjang cuplikan.
     */
    function cuplikan($teks, int $maks = 180): string
    {
        $maks = max(1, $maks);

        $polos = strip_tags((string) $teks);

        // Merapikan spasi berlebih.
        $rapi = preg_replace('/\s+/u', ' ', $polos);

        if ($rapi === null) {
            // Fallback jika teks bukan UTF-8 yang valid.
            $rapi = preg_replace('/\s+/', ' ', $polos);
        }

        $bersih = trim((string) $rapi);

        if ($bersih === '') {
            return '';
        }

        if (
            function_exists('mb_strlen') &&
            mb_strlen($bersih, 'UTF-8') <= $maks
        ) {
            return $bersih;
        }

        if (!function_exists('mb_substr')) {
            return strlen($bersih) <= $maks
                ? $bersih
                : rtrim(substr($bersih, 0, $maks)) . '...';
        }

        $potong = mb_substr($bersih, 0, $maks, 'UTF-8');

        $spasi = mb_strrpos($potong, ' ', 0, 'UTF-8');

        if ($spasi !== false && $spasi > $maks * 0.6) {
            $potong = mb_substr($potong, 0, $spasi, 'UTF-8');
        }

        return rtrim($potong, " ,.;:-") . '…';
    }
}
