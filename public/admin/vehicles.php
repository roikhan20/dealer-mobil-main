<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('admin_inventaris'); $area = 'staff'; $pdo = db();

if (is_post()) {
    $id = input_int('id');
    if (input('action') === 'toggle') {
        $pdo->prepare('UPDATE variants SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('success', 'Status tampil katalog diperbarui.');
    } elseif (input('action') === 'delete') {
        $c = $pdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE variant_id = ?'); $c->execute([$id]);
        if ($c->fetchColumn()) flash('danger', 'Varian memiliki unit fisik, tidak dapat dihapus. Nonaktifkan saja.');
        else {
            try {
                $imgs = $pdo->prepare('SELECT path FROM variant_images WHERE variant_id = ?'); $imgs->execute([$id]); $imgs = $imgs->fetchAll(PDO::FETCH_COLUMN);
                $pdo->prepare('DELETE FROM variants WHERE id = ?')->execute([$id]);
                foreach ($imgs as $f) @unlink(BASE_PATH . '/public/uploads/vehicles/' . basename($f));
                flash('success', 'Varian dihapus.');
            } catch (PDOException) { flash('danger', 'Varian sudah dipakai di jadwal test drive, tidak dapat dihapus. Sembunyikan saja dari katalog.'); }
        }
    }
    redirect('admin/vehicles.php');
}
$rows = $pdo->prepare(
    'SELECT v.id, v.name, v.is_active, b.name AS brand, m.name AS model, m.body_type, vp.price,
            (SELECT path FROM variant_images i WHERE i.variant_id = v.id ORDER BY i.sort, i.id LIMIT 1) AS img,
            (SELECT COUNT(*) FROM vehicle_units u WHERE u.variant_id = v.id AND u.status = "ready") AS stock
       FROM variants v JOIN models m ON m.id = v.model_id JOIN brands b ON b.id = m.brand_id
       LEFT JOIN variant_prices vp ON vp.variant_id = v.id AND vp.region = ? ORDER BY b.name, m.name, v.name');
$rows->execute([cfg('app', 'region')]); $rows = $rows->fetchAll();

$pageTitle = 'Master Kendaraan';
include BASE_PATH . '/app/views/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h4 class="mb-0">Master Kendaraan</h4>
  <a href="<?= e(url('admin/vehicle_form.php')) ?>" class="btn btn-gold btn-sm"><i class="bi bi-plus-lg"></i> Tambah Varian</a></div>
<div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th></th><th>Merk / Model</th><th>Varian</th><th>Tipe</th><th class="text-end">OTR</th><th class="text-center">Ready</th><th>Katalog</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?php $cv = car_cover($r['img'], $r['brand'], $r['model'], $r['name']); if ($cv): ?><img class="car-img car-thumb" src="<?= e($cv) ?>" alt="" loading="lazy"><?php endif; ?></td><td><?= e($r['brand'] . ' ' . $r['model']) ?></td><td><?= e($r['name']) ?></td><td><?= e($r['body_type']) ?></td>
      <td class="text-end"><?= $r['price'] ? rupiah($r['price']) : '-' ?></td><td class="text-center"><?= (int)$r['stock'] ?></td>
      <td><?= $r['is_active'] ? '<span class="badge text-bg-success">Tampil</span>' : '<span class="badge text-bg-secondary">Disembunyikan</span>' ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(url('vehicle.php?id=' . (int)$r['id'])) ?>" title="Lihat di katalog"><i class="bi bi-eye"></i></a>
        <a class="btn btn-sm btn-outline-light" href="<?= e(url('admin/vehicle_form.php?id=' . (int)$r['id'])) ?>">Edit</a>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-sm btn-outline-warning">Tampil/Sembunyi</button></form>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus varian ini?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="text-secondary">Belum ada data.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php include BASE_PATH . '/app/views/footer.php';
