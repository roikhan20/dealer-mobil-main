<?php
/**
 * (Opsional) Simpan semua foto bawaan katalog ke komputer agar bisa tampil tanpa internet.
 * Jalankan dari folder proyek:   php tools/download_car_photos.php
 * Tidak mengubah database apa pun. Foto disimpan ke public/assets/img/cars/.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
function url(string $p = ''): string { return '/' . ltrim($p, '/'); }   // stub, tidak dipakai saat download
require BASE_PATH . '/app/car_photos.php';

$dir = BASE_PATH . '/public/assets/img/cars';
if (!is_dir($dir)) mkdir($dir, 0775, true);
$ctx = stream_context_create(['http' => [
    'header' => "User-Agent: DealerMobilDemo/1.0 (student project; PHP)\r\n",
    'follow_location' => 1, 'max_redirects' => 5, 'timeout' => 60,
]]);
$ok = $fail = $skip = 0;
foreach (CAR_PHOTOS as $key => $files) {
    foreach ($files as $i => $file) {
        $target = $dir . '/' . car_photo_local_name($key, $i);
        if (is_file($target) && filesize($target) > 1000) { $skip++; continue; }
        echo str_pad($key, 24) . ' #' . ($i + 1) . ' ... ';
        $data = @file_get_contents(commons_file_url($file, 1280), false, $ctx);
        if ($data === false || strlen($data) < 1000 || !@getimagesizefromstring($data)) { echo "GAGAL\n"; $fail++; continue; }
        file_put_contents($target, $data);
        echo 'OK (' . round(strlen($data) / 1024) . " KB)\n"; $ok++;
        usleep(300000); // sopan ke server Wikimedia
    }
}
echo "\nSelesai: $ok diunduh, $skip sudah ada, $fail gagal.\n";
if ($fail) echo "Yang gagal tetap tampil langsung dari Wikimedia selama ada internet.\n";
