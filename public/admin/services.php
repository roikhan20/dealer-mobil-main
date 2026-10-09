<?php
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('service_advisor', 'manager'); $area = 'staff'; $pdo = db();
$canEdit = $user['role'] === 'service_advisor';
$date = valid_date(input('date')) ? input('date') : date('Y-m-d');

if (is_post()) {
    if (!$canEdit) { http_response_code(403); exit('403'); }
    if (input('action') === 'status') {
        $ns = input('status');
        if (in_array($ns, ['booked','in_progress','cancelled'], true))
            $pdo->prepare("UPDATE service_bookings SET status = ? WHERE id = ? AND status NOT IN ('done')")->execute([$ns, input_int('id')]);
        flash('success', 'Status booking diperbarui.');
    } elseif (input('action') === 'capacity') {
        $cap = max(0, min(50, input_int('capacity')));
        $d = input('date');
        if (valid_date($d)) {
            // Tidak boleh di bawah jumlah booking aktif
            $c = $pdo->prepare("SELECT COUNT(*) FROM service_bookings WHERE service_date=? AND status<>'cancelled'"); $c->execute([$d]);
            if ($cap < (int)$c->fetchColumn()) flash('danger', 'Kuota tidak boleh lebih kecil dari booking aktif.');
            else { $pdo->prepare('INSERT INTO service_capacity(service_date,capacity) VALUES (?,?) ON DUPLICATE KEY UPDATE capacity = VALUES(capacity)')->execute([$d, $cap]); flash('success', 'Kuota harian disimpan.'); }
        }
    }
    redirect('admin/services.php?date=' . urlencode(input('date', $date)));
}
$st = $pdo->prepare("SELECT sb.*, CONCAT(b.name,' ',v.name) AS car FROM service_bookings sb JOIN vehicle_units u ON u.vin=sb.vin JOIN variants v ON v.id=u.variant_id JOIN models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id
                     WHERE sb.service_date = ? ORDER BY sb.id");
$st->execute([$date]); $rows = $st->fetchAll();
$cap = $pdo->prepare('SELECT capacity FROM service_capacity WHERE service_date = ?'); $cap->execute([$date]);
$capacity = $cap->fetchColumn(); $capacity = $capacity === false ? (int)cfg('app', 'service_default_capacity') : (int)$capacity;

$pageTitle = 'Bengkel — Slot Servis';
include BASE_PATH . '/app/views/header.php';
?>
<h4 class="mb-3">Manajemen Slot Servis</h4>
<form class="row g-2 mb-3" method="get"><div class="col-md-3"><input type="date" name="date" class="form-control" value="<?= e($date) ?>"></div><div class="col-md-2 d-grid"><button class="btn btn-outline-light">Lihat</button></div>
  <div class="col-md-7 text-md-end align-self-center"><span class="badge text-bg-dark border">Kuota <?= $capacity ?></span> <span class="badge text-bg-success">Sisa <?= ServiceBookingService::remaining($pdo, $date) ?></span></div></form>
<?php if ($canEdit): ?>
<form method="post" class="row g-2 mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="capacity"><input type="hidden" name="date" value="<?= e($date) ?>">
  <div class="col-auto align-self-center">Atur kuota mekanik tanggal ini:</div><div class="col-auto"><input type="number" name="capacity" min="0" max="50" class="form-control form-control-sm" value="<?= $capacity ?>"></div>
  <div class="col-auto"><button class="btn btn-sm btn-gold">Simpan</button></div></form><?php endif; ?>
<div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Kode</th><th>Pemilik</th><th>Kendaraan / VIN</th><th>Jenis</th><th>Keluhan</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td class="small"><?= e($r['booking_code']) ?></td><td><?= e($r['customer_name']) ?><div class="small text-secondary"><?= e($r['phone']) ?></div></td>
    <td><?= e($r['car']) ?><div class="small text-secondary font-monospace"><?= e($r['vin']) ?></div></td><td class="small"><?= e($r['service_type']) ?></td><td class="small"><?= e($r['complaint'] ?? '-') ?></td><td><?= badge($r['status']) ?></td>
    <td class="text-end text-nowrap"><?php if ($canEdit && !in_array($r['status'], ['done','cancelled'], true)): ?>
      <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="status" value="in_progress"><button class="btn btn-sm btn-outline-warning">Mulai</button></form>
      <a class="btn btn-sm btn-outline-gold" href="<?= e(url('admin/service_record_form.php?booking_id=' . (int)$r['id'])) ?>">Selesai &amp; Catat</a>
      <form method="post" class="d-inline" onsubmit="return confirm('Batalkan booking?')"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-outline-danger">Batal</button></form>
    <?php endif; ?></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="text-secondary">Belum ada booking pada tanggal ini.</td></tr>'; ?></tbody></table></div></div>
<?php if ($canEdit): ?><div class="mt-3"><a class="btn btn-outline-light btn-sm" href="<?= e(url('admin/service_record_form.php')) ?>"><i class="bi bi-plus-lg"></i> Catat servis walk-in (tanpa booking)</a></div><?php endif; ?>
<?php include BASE_PATH . '/app/views/footer.php';
