<?php
declare(strict_types=1);
/**
 * Bootstrap: session aman, koneksi PDO, autoload service, helper global.
 */
define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = require BASE_PATH . '/config/config.php';

ini_set('display_errors', $GLOBALS['config']['app']['debug'] ? '1' : '0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Jakarta');

session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true,
    'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']),
]);
session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

spl_autoload_register(static function (string $class): void {
    $file = BASE_PATH . '/app/Services/' . $class . '.php';
    if (is_file($file)) require $file;
});
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/car_photos.php';
