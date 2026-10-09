<?php
/** Profil staf yang sedang login: ubah nama & ganti password sendiri. */
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role(); $area = 'staff'; $pdo = db();
$st = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $st->execute([$user['id']]); $me = $st->fetch();
if (!$me) { $_SESSION = []; redirect('login.php'); }

if (is_post()) {
    if (input('action') === 'name') {
        $name = input('name');
        if (mb_strlen($name) < 3) flash('danger', 'Nama minimal 3 karakter.');
        else {
            $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $me['id']]);
            $_SESSION['user']['name'] = $name;
            flash('success', 'Nama diperbarui.');
        }
    } elseif (input('action') === 'password') {
        $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? ''); $rep = (string)($_POST['repeat'] ?? '');
        if (!password_verify($cur, $me['password_hash'])) flash('danger', 'Password saat ini salah.');
        elseif (strlen($new) < 8) flash('danger', 'Password baru minimal 8 karakter.');
        elseif ($new !== $rep) flash('danger', 'Konfirmasi password tidak sama.');
        else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Password berhasil diganti.');
        }
    }
    redirect('admin/profile.php');
}
$pageTitle = 'Profil Saya';
include BASE_PATH . '/app/views/header.php';
?>
<h4 class="mb-3">Profil Saya</h4>
<div class="row g-4">
  <div class="col-lg-5"><form method="post" class="card p-4"><?= csrf_field() ?><input type="hidden" name="action" value="name">
    <div class="d-flex align-items-center gap-3 mb-3"><i class="bi bi-person-circle display-5 text-gold"></i>
      <div><div class="fw-semibold"><?= e($me['email']) ?></div><div class="small text-secondary"><?= e(ROLE_LABELS[$me['role']]) ?> · bergabung <?= e(date('d M Y', strtotime($me['created_at']))) ?></div></div></div>
    <label class="form-label">Nama tampilan</label><input name="name" class="form-control mb-3" required maxlength="100" value="<?= e($me['name']) ?>">
    <div><button class="btn btn-gold">Simpan</button></div>
  </form></div>
  <div class="col-lg-7"><form method="post" class="card p-4"><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h6><i class="bi bi-shield-lock text-gold"></i> Ganti Password</h6>
    <div class="mb-2"><label class="form-label small">Password saat ini</label><input type="password" name="current" class="form-control" required autocomplete="current-password"></div>
    <div class="row g-2 mb-3">
      <div class="col-md-6"><label class="form-label small">Password baru</label><input type="password" name="new" class="form-control" minlength="8" required autocomplete="new-password"></div>
      <div class="col-md-6"><label class="form-label small">Ulangi password baru</label><input type="password" name="repeat" class="form-control" minlength="8" required autocomplete="new-password"></div>
    </div>
    <div><button class="btn btn-gold">Ganti Password</button></div>
  </form></div>
</div>
<?php include BASE_PATH . '/app/views/footer.php';
