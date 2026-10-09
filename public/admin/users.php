<?php
/** Manajemen akun staf (khusus Manajer): tambah, ubah peran, aktif/nonaktif, reset password. */
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('manager'); $area = 'staff'; $pdo = db();
$roles = array_keys(ROLE_LABELS);

if (is_post()) {
    $act = input('action'); $id = input_int('id');
    if ($act === 'add') {
        $name = input('name'); $email = strtolower(input('email')); $role = input('role'); $pw = (string)($_POST['password'] ?? '');
        $err = [];
        if (mb_strlen($name) < 3) $err[] = 'Nama minimal 3 karakter.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Email tidak valid.';
        if (!in_array($role, $roles, true)) $err[] = 'Peran tidak valid.';
        if (strlen($pw) < 8) $err[] = 'Password minimal 8 karakter.';
        if ($err) flash('danger', implode(' ', $err));
        else try {
            $pdo->prepare('INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?,?)')->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), $role]);
            flash('success', "Akun $email dibuat.");
        } catch (PDOException) { flash('danger', 'Email sudah terdaftar.'); }
    } elseif ($id === (int)$user['id'] && in_array($act, ['toggle', 'role'], true)) {
        flash('warning', 'Anda tidak dapat menonaktifkan atau mengubah peran akun sendiri.');
    } elseif ($act === 'toggle') {
        $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('success', 'Status akun diperbarui.');
    } elseif ($act === 'role') {
        $role = input('role');
        if (in_array($role, $roles, true)) { $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]); flash('success', 'Peran diperbarui.'); }
    } elseif ($act === 'reset') {
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 8) flash('danger', 'Password baru minimal 8 karakter.');
        else { $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]); flash('success', 'Password direset.'); }
    }
    redirect('admin/users.php');
}

$rows = $pdo->query("SELECT u.*,
        (SELECT COUNT(*) FROM leads l WHERE l.sales_id = u.id AND l.status IN ('cold','warm','hot')) AS open_leads
   FROM users u ORDER BY u.is_active DESC, u.role, u.name")->fetchAll();
$pageTitle = 'Pengguna Sistem';
include BASE_PATH . '/app/views/header.php';
?>
<h4 class="mb-3">Pengguna Sistem</h4>
<div class="row g-4">
  <div class="col-lg-8"><div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Nama</th><th>Peran</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $self = (int)$r['id'] === (int)$user['id']; ?>
      <tr class="<?= $r['is_active'] ? '' : 'opacity-50' ?>">
        <td><strong><?= e($r['name']) ?></strong><?= $self ? ' <span class="badge text-bg-dark border">Anda</span>' : '' ?><div class="small text-secondary"><?= e($r['email']) ?><?= $r['role'] === 'sales' ? ' · ' . (int)$r['open_leads'] . ' lead aktif' : '' ?></div></td>
        <td><?php if ($self): ?><?= e(ROLE_LABELS[$r['role']]) ?><?php else: ?>
          <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <select name="role" class="form-select form-select-sm" onchange="this.form.submit()"><?php foreach (ROLE_LABELS as $k => $l): ?><option value="<?= $k ?>" <?= $r['role'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></form><?php endif; ?></td>
        <td><?= $r['is_active'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#rs<?= (int)$r['id'] ?>"><i class="bi bi-key"></i> Reset</button>
          <?php if (!$self): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm <?= $r['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"><?= $r['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form><?php endif; ?>
        </td></tr>
      <tr class="collapse" id="rs<?= (int)$r['id'] ?>"><td colspan="4">
        <form method="post" class="d-flex gap-2 justify-content-end"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="password" name="password" class="form-control form-control-sm" style="max-width:260px" placeholder="Password baru (min 8 karakter)" minlength="8" required autocomplete="new-password">
          <button class="btn btn-sm btn-gold">Simpan password</button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table></div></div></div>
  <div class="col-lg-4"><form method="post" class="card p-3"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h6><i class="bi bi-person-plus text-gold"></i> Tambah Akun Staf</h6>
    <div class="mb-2"><label class="form-label small">Nama</label><input name="name" class="form-control" required maxlength="100"></div>
    <div class="mb-2"><label class="form-label small">Email</label><input type="email" name="email" class="form-control" required maxlength="150"></div>
    <div class="mb-2"><label class="form-label small">Peran</label><select name="role" class="form-select"><?php foreach (ROLE_LABELS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label small">Password awal</label><input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password"></div>
    <button class="btn btn-gold">Buat Akun</button>
  </form></div>
</div>
<?php include BASE_PATH . '/app/views/footer.php';
