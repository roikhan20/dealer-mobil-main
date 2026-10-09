<?php
// Konfigurasi aplikasi. Bisa dioverride lewat environment variable.
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'dealer_roket',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: 'rijal123',
    ],
    'app' => [
        'company' => 'PT Penerbang Roket',
        'address' => 'Jl. Raya Puspiptek No. 46, Buaran, Serpong, Tangerang Selatan',
        'region'  => 'Tangerang Selatan',   // wilayah harga OTR default
        'service_default_capacity' => 8,    // slot mekanik per hari
        'warranty_years' => 3,
        'warranty_km'    => 100000,
        'debug' => true,
    ],
];
