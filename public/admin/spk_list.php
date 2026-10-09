<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('sales', 'manager'); $area = 'staff'; $pdo = db();
$where = ['1=1']; $p = [];
if ($user['role'] === 'sales') { $where[] = 's.sales_id = ?'; $p[] = $user['id']; }
if (in_array($fs = input('status'), ['active','delivered','cancelled'], true)) { $where[] = 's.status = ?'; $p[] = $fs; }
$st = $pdo->prepare("SELECT s.*, u.vin, CONCAT(b.name,' ',v.name) AS car, sl.name AS sales_name
    FROM spk s JOIN vehicle_units u ON u.id=s.unit_id JOIN variants v ON v.id=u.variant_id JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id
    JOIN users sl ON sl.id = s.sales_id WHERE " . implode(' AND ', $where) . ' ORDER BY s.id DESC LIMIT 300');
$st->execute($p); $rows = $st->fetchAll();
$pageTitle = 'Daftar SPK';
include BASE_PATH . '/app/views/header.php';
?>
<div class="d-flex justify-content-between mb-3"><h4 class="mb-0">Daftar SPK</h4>
  <?php if ($user['role'] === 'sales'): ?><a class="btn btn-gold btn-sm" href="<?= e(url('admin/spk_form.php')) ?>"><i class="bi bi-plus-lg"></i> SPK Baru</a><?php endif; ?></div>
<form class="row g-2 mb-3" method="get"><div class="col-md-3"><select name="status" class="form-select"><option value="">Semua status</option><?php foreach (['active','delivered','cancelled'] as $o): ?><option <?= $fs === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div><div class="col-md-2 d-grid"><button class="btn btn-outline-light">Filter</button></div></form>
<div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>No. SPK</th><th>Pembeli</th><th>Kendaraan / VIN</th><th>Bayar</th><?php if ($user['role'] === 'manager'): ?><th>Sales</th><?php endif; ?><th class="text-end">Booking Fee</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><?= e($r['spk_no']) ?></td><td><?= e($r['customer_name']) ?></td><td><?= e($r['car']) ?><div class="small text-secondary font-monospace"><?= e($r['vin']) ?></div></td>
    <td><?= e(strtoupper($r['payment_method'])) ?></td><?php if ($user['role'] === 'manager'): ?><td><?= e($r['sales_name']) ?></td><?php endif; ?>
    <td class="text-end"><?= rupiah($r['booking_fee']) ?></td><td><?= badge($r['status']) ?></td>
    <td class="text-end"><a class="btn btn-sm btn-outline-gold" href="<?= e(url('admin/spk_view.php?id=' . (int)$r['id'])) ?>">Detail</a></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="text-secondary">Belum ada SPK.</td></tr>'; ?></tbody></table></div></div>
<?php include BASE_PATH . '/app/views/footer.php';
