<?php
declare(strict_types=1);
/**
 * Foto bawaan katalog (tanpa perlu tabel/kolom baru di database).
 *
 * Sumber: Wikimedia Commons (lisensi bebas, mis. CC BY-SA). Ditampilkan langsung dari
 * Commons lewat Special:FilePath, jadi butuh internet. Jalankan
 *     php tools/download_car_photos.php
 * sekali untuk menyimpan salinannya ke public/assets/img/cars/ agar bisa dipakai offline.
 *
 * Urutan prioritas foto sebuah varian:
 *   1) foto yang di-upload admin (tabel variant_images)
 *   2) salinan lokal di public/assets/img/cars/<kunci>-<n>.jpg
 *   3) foto Wikimedia Commons di bawah ini
 *
 * Kunci = "merk model" huruf kecil. Kunci "merk model|kata" dipakai lebih dulu bila nama
 * varian mengandung "kata" (mis. Brio Satya vs Brio RS, Jimny 3 pintu vs 5 pintu).
 */
const CAR_PHOTOS = [
    'honda brio' => [
        '2023 Honda Brio RS (Indonesia) front view.jpg',
        'Honda Brio (Rear) (3), Jakarta, Indonesia.jpg',
        'Honda Brio RS facelift interior.jpg',
    ],
    'honda brio|satya' => [
        '2023 Honda Brio Satya E (facelift).jpg',
        '2023 Honda Brio Satya 1.2 E in Rallye Red (Rear).jpg',
        '2023 Honda Brio Satya E, Citraland, West Surabaya.jpg',
    ],
    'honda city hatchback' => [
        '2024 Honda City Hatchback RS.jpg',
        'Honda City Hatchback 1.5 RS Ignite Red Metallic - rear.jpg',
        '2021 Honda City Hatchback RS (Indonesia) front view 02.jpg',
    ],
    'honda city sedan' => [
        'Honda City (7th generation) sedan in Jambi City.jpg',
        '0 Honda City (7th generation) sedan.jpg',
    ],
    'honda civic' => [
        '2022 Honda Civic RS 1.5 FE1 (20220317) 02.jpg',
        'Honda CIVIC RS (5BA-FL1) front.jpg',
        'Honda CIVIC RS (5BA-FL1) rear.jpg',
    ],
    'honda civic type r' => [
        'The frontview of Honda CIVIC TYPE R (FL5).jpg',
        'The rearview of Honda CIVIC TYPE R (FL5).jpg',
        'Honda CIVIC TYPE R (FL5) interior.jpg',
    ],
    'honda accord' => [
        '2023 Honda Accord RS e-HEV 2.0 CY2 (20230819).jpg',
    ],
    'honda wr-v' => [
        'Honda WR-V Z+ (5BA-DG5) front.jpg',
        '2025 Honda WR-V Z rear.jpg',
    ],
    'honda br-v' => [
        '2021 Honda BR-V Prestige with Honda Sensing 1.5 DG3 L15ZF engine (20211029).jpg',
        'Honda BRV 1.5 VX 2025 (4).jpg',
    ],
    'honda hr-v' => [
        'Honda HR-V RV4 1.5 RS e-HEV Sand Khaki Pearl - front.jpg',
        'Honda HR-V RV5 FL 1.5 RS e-HEV Sand Khaki Pearl 01.jpg',
        'Honda HR-V Hybrid 1X7A0427.jpg',
    ],
    'honda cr-v' => [
        'Honda CR-V (6th generation) hybrid 1X7A0866.jpg',
        'Honda CR-V (6th generation) hybrid DSC 7919.jpg',
        'HONDA CR-V HYBRID SIXTH GENERATION CHINA VERSION INTERIOR.jpg',
    ],
    'honda e:n1' => [
        'Honda e-Ny1 IAA 2023 1X7A0562.jpg',
    ],
    'suzuki s-presso' => [
        'Suzuki S-Presso 1.0 AGS Granite Gray Metallic 02.jpg',
        '2021 Suzuki S-Presso 1.0 AMT Orange front view in Brunei.jpg',
        '2022 Suzuki S-Presso.jpg',
    ],
    'suzuki baleno' => [
        'Suzuki Baleno Hatchback (depan).jpg',
        'Suzuki Baleno Hatchback (belakang).jpg',
    ],
    'suzuki fronx' => [
        'Suzuki Fronx (front).jpg',
        'Suzuki Fronx (rear).jpg',
        '2024 Suzuki Fronx.jpg',
    ],
    'suzuki ertiga' => [
        '2024 Suzuki Ertiga 1.5 GLX Hybrid in Snow White Pearl, front right, 06-16-2024.jpg',
        '2024 Suzuki Ertiga 1.5 GLX Hybrid in Brave Khaki Pearl, rear right.jpg',
        'Suzuki Ertiga 1.5 GL Hybrid interior.jpg',
    ],
    'suzuki xl7' => [
        '2023 Suzuki XL7 Alpha Front.jpg',
        '2023 Suzuki XL7 Alpha Rear.jpg',
        '2023 Suzuki XL7 Alpha Hybrid.jpg',
    ],
    'suzuki grand vitara' => [
        '2022 Suzuki Grand Vitara GX Smart Hybrid (Indonesia) front view.jpg',
        '2022 Maruti Suzuki Grand Vitara Zeta Smart Hybrid (India) front view.jpg',
    ],
    'suzuki jimny' => [
        'Suzuki Jimny (JB74) 135159.jpg',
        'Suzuki Jimny SIERRA JC (3BA-JB74W-GJCR-J) rear.jpg',
        'Suzuki Jimny SIERRA JC (3BA-JB74W-GJCR-J) interior.jpg',
    ],
    'suzuki jimny|5-door' => [
        'Suzuki Jimny 1.5 GLX 5-Door 2024 (1).jpg',
        'Suzuki Jimny 5 door IIMS 2024.jpg',
    ],
    'suzuki apv arena' => [
        'Suzuki APV Arena SGX, Jimbaran (cropped).jpg',
        '2013 Suzuki APV Arena SGX Luxury 1.5 DN42V (20190623).jpg',
    ],
    'suzuki carry' => [
        'Suzuki Carry Mobil Pick Up.jpg',
        '2019 Suzuki Carry Flat Deck 1.5 DC61T (20190826) 01.jpg',
    ],
];

/** Kunci CAR_PHOTOS yang cocok untuk sebuah varian, atau null. */
function car_photo_key(string $brand, string $model, string $variant = ''): ?string
{
    $base = mb_strtolower(trim($brand . ' ' . $model));
    $v = mb_strtolower($variant);
    foreach (array_keys(CAR_PHOTOS) as $k) {
        if (str_starts_with($k, $base . '|') && $v !== '' && str_contains($v, substr($k, strlen($base) + 1))) return $k;
    }
    return isset(CAR_PHOTOS[$base]) ? $base : null;
}

/** Nama file lokal untuk foto ke-$i dari sebuah kunci. */
function car_photo_local_name(string $key, int $i): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', str_replace('|', ' ', $key)), '-') . '-' . ($i + 1) . '.jpg';
}

function commons_file_url(string $file, int $width = 1024): string
{
    return 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode(str_replace(' ', '_', $file)) . '?width=' . $width;
}
function commons_page_url(string $file): string
{
    return 'https://commons.wikimedia.org/wiki/File:' . rawurlencode(str_replace(' ', '_', $file));
}

/**
 * Daftar foto bawaan sebuah varian.
 * @return list<array{src:string, credit:string}> src siap dipakai di <img>, credit = URL halaman sumber
 */
function car_photos(string $brand, string $model, string $variant = ''): array
{
    $key = car_photo_key($brand, $model, $variant);
    if ($key === null) return [];
    $out = [];
    foreach (CAR_PHOTOS[$key] as $i => $file) {
        $local = car_photo_local_name($key, $i);
        $src = is_file(BASE_PATH . '/public/assets/img/cars/' . $local)
            ? url('assets/img/cars/' . $local)
            : commons_file_url($file);
        $out[] = ['src' => $src, 'credit' => commons_page_url($file)];
    }
    return $out;
}

/** Foto utama (sampul) sebuah varian: upload admin dulu, lalu foto bawaan. */
function car_cover(?string $uploaded, string $brand, string $model, string $variant = ''): ?string
{
    if ($uploaded) return url('uploads/vehicles/' . $uploaded);
    $p = car_photos($brand, $model, $variant);
    return $p[0]['src'] ?? null;
}
