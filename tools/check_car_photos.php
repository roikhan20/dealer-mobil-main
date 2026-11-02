<?php
/**
 * Memeriksa semua foto bawaan katalog di app/car_photos.php: apakah berkasnya masih ada di Wikimedia Commons.
 * Jalankan dari folder proyek di komputer yang terhubung internet:
 *     php tools/check_car_photos.php
 * Hanya membaca (HTTP HEAD). Tidak mengubah apa pun.
 * Berkas yang gagal ditandai RUSAK -> ganti namanya di app/car_photos.php atau upload foto lewat Master Kendaraan.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
function url(string $p = ''): string { return '/' . ltrim($p, '/'); }   // stub
require BASE_PATH . '/app/car_photos.php';

$ok = $bad = 0;
foreach (CAR_PHOTOS as $key => $files) {
    foreach ($files as $i => $file) {
        $target = commons_file_url($file, 640);
        $ctx = stream_context_create(['http' => [
            'method' => 'HEAD', 'follow_location' => 1, 'max_redirects' => 5, 'timeout' => 20, 'ignore_errors' => true,
            'header' => "User-Agent: DealerMobilDemo/1.0 (student project; PHP)\r\n",
        ]]);
        @file_get_contents($target, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int)$m[1];
        }
        $label = str_pad($key, 24) . ' #' . ($i + 1) . '  ' . $file;
        if ($status === 200) { $ok++; echo "OK     $label\n"; }
        else { $bad++; echo "RUSAK  $label  (HTTP $status)\n"; }
        usleep(250000);
    }
}
echo "\nTotal: $ok OK, $bad rusak.\n";
