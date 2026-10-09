<?php
require __DIR__ . '/../app/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $_SESSION = [];
    session_destroy();
    session_start();
    flash('success', 'Anda telah keluar.');
}
redirect('login.php');
