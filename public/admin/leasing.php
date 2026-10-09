<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('admin_inventaris', 'manager'); $area = 'staff'; $pdo = db();
$canEdit = true; // Admin Inventaris & Manajer boleh mengatur parameter pembiayaan

if (is_post()) {
    $act = input('action');
    if ($act === 'add_partner') {
        $name = input('name');
        if ($name === '') flash('danger', 'Nama mitra wajib diisi.');
        else try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO leasing_partners(name,min_dp_percent,admin_fee) VALUES (?,?,?)')->execute([$name, max(10, min(80, input_float('min_dp', 20))), max(0, input_float('admin_fee'))]);
            $pid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO leasing_rates(partner_id,tenor,interest_rate,insurance_tlo_rate,insurance_ar_rate) VALUES (?,?,5,0.8,2)');
            foreach (TdpCalculator::TENORS as $t) $ins->execute([$pid, $t]);
            $pdo->commit(); flash('success', 'Mitra ditambahkan dengan tarif default; silakan sesuaikan.');
        } catch (PDOException) { $pdo->rollBack(); flash('danger', 'Nama mitra sudah ada.'); }
    } elseif ($act === 'save_partner') {
        $pid = input_int('partner_id');
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE leasing_partners SET min_dp_percent=?, admin_fee=?, is_active=? WHERE id=?')
            ->execute([max(10, min(80, input_float('min_dp'))), max(0, input_float('admin_fee')), isset($_POST['is_active']) ? 1 : 0, $pid]);
        $up = $pdo->prepare('INSERT INTO leasing_rates(partner_id,tenor,interest_rate,insurance_tlo_rate,insurance_ar_rate) VALUES (?,?,?,?,?)
                             ON DUPLICATE KEY UPDATE interest_rate=VALUES(interest_rate), insurance_tlo_rate=VALUES(insurance_tlo_rate), insurance_ar_rate=VALUES(insurance_ar_rate)');
        foreach (TdpCalculator::TENORS as $t) {
            $r = fn(string $k) => max(0, min(50, (float)($_POST[$k][$t] ?? 0)));
            $up->execute([$pid, $t, $r('interest'), $r('tlo'), $r('ar')]);
        }
        $pdo->commit(); flash('success', 'Parameter leasing disimpan.');
    }
    redirect('admin/leasing.php');
}
$partners = $pdo->query('SELECT * FROM leasing_partners ORDER BY name')->fetchAll();
$rates = [];
foreach ($pdo->query('SELECT * FROM leasing_rates')->fetchAll() as $r) $rates[$r['partner_id']][$r['tenor']] = $r;

$pageTitle = 'Parameter Leasing';
include BASE_PATH . '/app/views/header.php';
?>
<h4 class="mb-3">Dynamic Rate Management — Mitra Leasing</h4>
<?php foreach ($partners as $p): ?>
<form method="post" class="card p-3 mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="save_partner"><input type="hidden" name="partner_id" value="<?= (int)$p['id'] ?>">
  <div class="row g-2 align-items-end mb-2">
    <div class="col-md-4"><h5 class="mb-0"><?= e($p['name']) ?></h5></div>
    <div class="col-md-2"><label class="form-label small">DP minimal (%)</label><input type="number" step="0.5" name="min_dp" class="form-control form-control-sm" value="<?= e($p['min_dp_percent']) ?>"></div>
    <div class="col-md-3"><label class="form-label small">Biaya admin (Rp)</label><input type="number" name="admin_fee" class="form-control form-control-sm" value="<?= e((int)$p['admin_fee']) ?>"></div>
    <div class="col-md-1"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" id="a<?= (int)$p['id'] ?>" <?= $p['is_active'] ? 'checked' : '' ?>><label class="form-check-label small" for="a<?= (int)$p['id'] ?>">Aktif</label></div></div>
    <div class="col-md-2 d-grid"><button class="btn btn-gold btn-sm">Simpan</button></div>
  </div>
  <table class="table table-sm mb-0"><thead><tr><th>Tenor</th><th>Bunga flat %/thn</th><th>Asuransi TLO %/thn</th><th>Asuransi All Risk %/thn</th></tr></thead><tbody>
  <?php foreach (TdpCalculator::TENORS as $t): $r = $rates[$p['id']][$t] ?? ['interest_rate' => 0, 'insurance_tlo_rate' => 0, 'insurance_ar_rate' => 0]; ?>
    <tr><td><?= $t ?> bln</td>
      <td><input type="number" step="0.01" name="interest[<?= $t ?>]" class="form-control form-control-sm" value="<?= e($r['interest_rate']) ?>"></td>
      <td><input type="number" step="0.01" name="tlo[<?= $t ?>]" class="form-control form-control-sm" value="<?= e($r['insurance_tlo_rate']) ?>"></td>
      <td><input type="number" step="0.01" name="ar[<?= $t ?>]" class="form-control form-control-sm" value="<?= e($r['insurance_ar_rate']) ?>"></td></tr>
  <?php endforeach; ?></tbody></table>
</form>
<?php endforeach; ?>
<form method="post" class="card p-3"><?= csrf_field() ?><input type="hidden" name="action" value="add_partner">
  <h6>Tambah Mitra Leasing</h6>
  <div class="row g-2"><div class="col-md-5"><input name="name" class="form-control" placeholder="Nama mitra" required></div>
  <div class="col-md-2"><input type="number" name="min_dp" class="form-control" value="20" step="0.5" placeholder="DP min %"></div>
  <div class="col-md-3"><input type="number" name="admin_fee" class="form-control" value="3000000" placeholder="Biaya admin"></div>
  <div class="col-md-2 d-grid"><button class="btn btn-outline-gold">Tambah</button></div></div></form>
<?php include BASE_PATH . '/app/views/footer.php';
