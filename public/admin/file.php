<?php
/** Penyaji file privat (foto KTP/SIM). Hanya Sales & Manajer yang login. */
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('sales', 'manager');
$f = basename((string)($_GET['f'] ?? ''));
$path = BASE_PATH . '/storage/uploads/ids/' . $f;
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $f) || !is_file($path)) { http_response_code(404); exit('Not found'); }
// Otorisasi per data: sales hanya boleh melihat KTP dari test drive pada lead miliknya.
if ($user['role'] === 'sales') {
    $own = db()->prepare('SELECT 1 FROM test_drives t JOIN leads l ON l.id = t.lead_id WHERE t.id_photo = ? AND l.sales_id = ? LIMIT 1');
    $own->execute([$f, $user['id']]);
    if (!$own->fetchColumn()) { http_response_code(403); exit('403 - Dokumen ini bukan milik lead Anda.'); }
}
header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($path));
header('Cache-Control: private, no-store');
readfile($path);
