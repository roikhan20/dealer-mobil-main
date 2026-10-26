<?php
// Jalankan sekali setelah migration_owner_role.sql:  php database/seed_owner.php
// Membuat akun owner demo. GANTI password setelah login pertama!
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$st = db()->prepare('INSERT IGNORE INTO users(name,email,password_hash,role) VALUES (?,?,?,?)');
$st->execute(['Owner', 'owner@roket.test', password_hash('Password123!', PASSWORD_DEFAULT), 'owner']);
echo "OK: owner@roket.test (owner)\n";
echo "Password: Password123!\n";
