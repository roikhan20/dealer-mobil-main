<?php
// Konfigurasi aplikasi. Semua nilai sensitif diambil dari environment variable.
// Jangan menulis password asli di file ini.
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'dealer_roket',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
    ],
    'app' => [
        'company' => 'PT Penerbang Roket',
        'address' => 'Jl. Raya Puspiptek No. 46, Buaran, Serpong, Tangerang Selatan',
        'region'  => 'Tangerang Selatan',   // wilayah harga OTR default
        'service_default_capacity' => 8,    // slot mekanik per hari
        'testdrive_slot_capacity'  => 2,    // jumlah test drive serentak per jam (sesuai jumlah unit demo)
        'warranty_years' => 3,
        'warranty_km'    => 100000,
        'debug' => getenv('APP_DEBUG') === '1',   // tampilkan error PHP hanya saat pengembangan
    ],
];
