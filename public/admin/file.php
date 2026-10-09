<?php
/** Penyaji file privat (foto KTP/SIM). Hanya Sales & Manajer yang login. */
require __DIR__ . '/../../app/bootstrap.php';
require_role('sales', 'manager');
$f = basename((string)($_GET['f'] ?? ''));
$path = BASE_PATH . '/storage/uploads/ids/' . $f;
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $f) || !is_file($path)) { http_response_code(404); exit('Not found'); }
header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($path));
header('Cache-Control: private, no-store');
readfile($path);
