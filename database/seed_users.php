<?php
// Jalankan sekali:  php database/seed_users.php
// Membuat 5 akun staf demo. GANTI password setelah login pertama!
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$users = [
  ['Admin Inventaris', 'inventaris@roket.test', 'admin_inventaris'],
  ['Sales Budi',       'budi@roket.test',       'sales'],
  ['Sales Sari',       'sari@roket.test',       'sales'],
  ['Service Advisor',  'service@roket.test',    'service_advisor'],
  ['Manajer Penjualan','manager@roket.test',    'manager'],
];
$st = db()->prepare('INSERT IGNORE INTO users(name,email,password_hash,role) VALUES (?,?,?,?)');
foreach ($users as [$n, $e, $r]) {
    $st->execute([$n, $e, password_hash('Password123!', PASSWORD_DEFAULT), $r]);
    echo "OK: $e ($r)\n";
}
echo "Password semua akun: Password123!\n";
