<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('admin_inventaris', 'manager'); $area = 'staff'; $pdo = db();
$canEdit = $user['role'] === 'admin_inventaris';

if (is_post()) {
    if (!$canEdit) { http_response_code(403); exit('Hanya Admin Inventaris yang dapat mengubah stok.'); }
    if (input('action') === 'add') {
        $vin = strtoupper(preg_replace('/\s/', '', input('vin'))); $eng = strtoupper(input('engine_no'));
        $variant = input_int('variant_id'); $color = input_int('color_id') ?: null;
        if (!preg_match(VIN_REGEX, $vin)) flash('danger', 'VIN harus 17 karakter alfanumerik (tanpa I, O, Q).');
        elseif ($eng === '' || strlen($eng) > 30) flash('danger', 'Nomor mesin wajib diisi (maks 30 karakter).');
        else {
            try {
                if ($color) { $c = $pdo->prepare('SELECT COUNT(*) FROM variant_colors WHERE id = ? AND variant_id = ?'); $c->execute([$color, $variant]); if (!$c->fetchColumn()) $color = null; }
                $pdo->prepare('INSERT INTO vehicle_units(variant_id,color_id,vin,engine_no,received_at) VALUES (?,?,?,?,CURDATE())')->execute([$variant, $color, $vin, $eng]);
                flash('success', "Unit $vin ditambahkan (status Ready).");
            } catch (PDOException $ex) {
                flash('danger', ($ex->errorInfo[1] ?? 0) == 1062 ? 'VIN atau nomor mesin sudah terdaftar.' : 'Gagal menyimpan unit (cek varian).');
            }
        }
    } elseif (input('action') === 'delete') {
        $st = $pdo->prepare("DELETE FROM vehicle_units WHERE id = ? AND status = 'ready'");
        try { $st->execute([input_int('id')]); flash($st->rowCount() ? 'success' : 'warning', $st->rowCount() ? 'Unit dihapus.' : 'Hanya unit berstatus Ready yang dapat dihapus.'); }
        catch (PDOException) { flash('danger', 'Unit sudah punya riwayat transaksi, tidak dapat dihapus.'); }
    }
    redirect('admin/units.php');
}

$fs = input('status'); $fv = input_int('variant'); $fq = strtoupper(input('q'));
$where = ['1=1']; $p = [];
if (in_array($fs, ['ready','booked','delivered'], true)) { $where[] = 'u.status = ?'; $p[] = $fs; }
if ($fv) { $where[] = 'u.variant_id = ?'; $p[] = $fv; }
if ($fq !== '') { $where[] = 'u.vin LIKE ?'; $p[] = '%' . $fq . '%'; }
$st = $pdo->prepare('SELECT u.*, CONCAT(b.name," ",v.name) AS label, c.name AS color FROM vehicle_units u JOIN variants v ON v.id=u.variant_id JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id LEFT JOIN variant_colors c ON c.id=u.color_id WHERE ' . implode(' AND ', $where) . ' ORDER BY u.id DESC LIMIT 200');
$st->execute($p); $units = $st->fetchAll();
$variants = $pdo->query('SELECT v.id, CONCAT(b.name," ",v.name) AS label FROM variants v JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id ORDER BY label')->fetchAll();
$colors = $pdo->query('SELECT id, variant_id, name FROM variant_colors ORDER BY name')->fetchAll();

$pageTitle = 'Stok Unit (VIN)';
include BASE_PATH . '/app/views/header.php';
?>
<h4 class="mb-3">Stok Unit Fisik (VIN)</h4>
<?php if ($canEdit): ?>
<form method="post" class="card p-3 mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label small">Varian</label><select name="variant_id" id="u_variant" class="form-select" required><?php foreach ($variants as $v): ?><option value="<?= (int)$v['id'] ?>"><?= e($v['label']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label small">Warna</label><select name="color_id" id="u_color" class="form-select"></select></div>
    <div class="col-md-3"><label class="form-label small">VIN (17 karakter)</label><input name="vin" class="form-control text-uppercase" maxlength="17" required></div>
    <div class="col-md-2"><label class="form-label small">No. Mesin</label><input name="engine_no" class="form-control text-uppercase" maxlength="30" required></div>
    <div class="col-md-2 d-grid"><button class="btn btn-gold">Tambah Unit</button></div>
  </div></form>
<script>
const COLORS = <?= json_encode($colors, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const vSel = document.getElementById('u_variant'), cSel = document.getElementById('u_color');
function fillColors() { cSel.innerHTML = '<option value="">—</option>' + COLORS.filter(c => String(c.variant_id) === vSel.value).map(c => `<option value="${c.id}">${c.name.replace(/</g,'&lt;')}</option>`).join(''); }
vSel.addEventListener('change', fillColors); fillColors();
</script>
<?php endif; ?>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="status" class="form-select"><option value="">Semua status</option><?php foreach (['ready','booked','delivered'] as $o): ?><option <?= $fs === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><select name="variant" class="form-select"><option value="">Semua varian</option><?php foreach ($variants as $v): ?><option value="<?= (int)$v['id'] ?>" <?= $fv == $v['id'] ? 'selected' : '' ?>><?= e($v['label']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><input name="q" class="form-control" placeholder="Cari VIN" value="<?= e($fq) ?>"></div>
  <div class="col-md-2 d-grid"><button class="btn btn-outline-light">Filter</button></div></form>
<div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>VIN</th><th>No. Mesin</th><th>Varian</th><th>Warna</th><th>Status</th><th>Masuk</th><th></th></tr></thead><tbody>
  <?php foreach ($units as $u): ?><tr><td class="font-monospace"><?= e($u['vin']) ?></td><td><?= e($u['engine_no']) ?></td><td><?= e($u['label']) ?></td><td><?= e($u['color'] ?? '-') ?></td><td><?= badge($u['status']) ?></td><td><?= e($u['received_at']) ?></td>
    <td class="text-end"><?php if ($canEdit && $u['status'] === 'ready'): ?><form method="post" onsubmit="return confirm('Hapus unit?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-outline-danger">Hapus</button></form><?php endif; ?></td></tr>
  <?php endforeach; if (!$units) echo '<tr><td colspan="7" class="text-secondary">Tidak ada unit.</td></tr>'; ?></tbody></table></div></div>
<?php include BASE_PATH . '/app/views/footer.php';
