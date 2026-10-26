<?php
require __DIR__ . '/../app/bootstrap.php';

if (auth_user()) redirect(auth_user()['role'] === 'owner' ? 'admin/owner_dashboard.php' : 'admin/index.php');
if (is_post()) {
    // Pembatasan percobaan login sederhana berbasis sesi (tambahkan limiter per-IP di produksi).
    $_SESSION['login_fail'] = $_SESSION['login_fail'] ?? 0;
    $_SESSION['login_lock'] = $_SESSION['login_lock'] ?? 0;
    if ($_SESSION['login_lock'] > time()) {
        flash('danger', 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.');
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
        $st->execute([strtolower(input('email'))]);
        $u = $st->fetch();
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltuQ7qC2h4h1Z6mHqkq9m3Nf0fXkVJi'; // cegah timing attack
        if (password_verify((string)($_POST['password'] ?? ''), $hash) && $u) {
            session_regenerate_id(true);
            $_SESSION['login_fail'] = 0;
            $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'role' => $u['role']];
                        redirect($u['role'] === 'owner' ? 'admin/owner_dashboard.php' : 'admin/index.php');
        }
        if (++$_SESSION['login_fail'] >= 5) { $_SESSION['login_lock'] = time() + 300; $_SESSION['login_fail'] = 0; }
        flash('danger', 'Email atau password salah.');
    }
    redirect('login.php');
}

$pageTitle = 'Login Staf';
include BASE_PATH . '/app/views/header.php';
?>
<div class="row justify-content-center"><div class="col-md-5 col-lg-4">
  <div class="card p-4">
    <h4 class="mb-1">Login Staf</h4>
    <p class="text-secondary small">Khusus karyawan PT Penerbang Roket.</p>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <button class="btn btn-gold w-100">Masuk</button>
    </form>
  </div>
  <?php
    // Bantuan demo: tampilkan akun contoh dari seed_users.php (domain @roket.test) bila ada.
    try { $demo = db()->query("SELECT name, email, role FROM users WHERE email LIKE '%@roket.test' AND is_active = 1 ORDER BY FIELD(role,'manager','admin_inventaris','sales','service_advisor'), name")->fetchAll(); }
    catch (Throwable) { $demo = []; }
  ?>
  <div class="card p-3 mt-3 small">
    <div class="fw-semibold mb-2"><i class="bi bi-info-circle text-gold"></i> Akun demo <span class="text-secondary fw-normal">(password: <code>Password123!</code>)</span></div>
    <?php if ($demo): foreach ($demo as $d): ?>
      <button type="button" class="btn btn-sm btn-outline-secondary text-start mb-1 w-100 demo-acc" data-email="<?= e($d['email']) ?>">
        <strong><?= e(ROLE_LABELS[$d['role']] ?? $d['role']) ?></strong> — <?= e($d['email']) ?></button>
    <?php endforeach; else: ?>
      <div class="text-secondary">Belum ada akun staf. Jalankan <code>php database/seed_users.php</code> sekali dari folder proyek.</div>
    <?php endif; ?>
  </div>
  <script>
  document.querySelectorAll('.demo-acc').forEach(b => b.addEventListener('click', () => {
    document.querySelector('input[name=email]').value = b.dataset.email;
    const pw = document.querySelector('input[name=password]'); pw.value = 'Password123!'; pw.focus();
  }));
  </script>
</div></div>
<?php include BASE_PATH . '/app/views/footer.php';
