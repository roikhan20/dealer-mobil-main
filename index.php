<?php
// Jika web server (mis. Laragon "dealer-mobil-main.test") mengarah ke folder proyek,
// alihkan otomatis ke folder public/ tempat aplikasi berada.
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
header('Location: ' . $base . '/public/', true, 302);
exit;
