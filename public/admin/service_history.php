<?php
/** Daftar seluruh riwayat servis yang sudah dicatat bengkel. */
require __DIR__ . '/../../app/bootstrap.php';
$user = require_role('service_advisor', 'manager'); $area = 'staff'; $pdo = db();
$q = strtoupper(trim(input('q'))); $from = input('from'); $to = input('to'); $wc = input('warranty');
$where = ['1=1']; $p = [];
if ($q !== '') { $where[] = '(r.vin LIKE ? OR sb.booking_code LIKE ? OR sb.customer_name LIKE ?)'; array_push($p, "%$q%", "%$q%", '%' . input('q') . '%'); }
if (valid_date($from)) { $where[] = 'r.service_date >= ?'; $p[] = $from; }
if (valid_date($to))   { $where[] = 'r.service_date <= ?'; $p[] = $to; }
if ($wc === '1') $where[] = 'r.warranty_claim = 1';

$st = $pdo->prepare(
    "SELECT r.*, sb.booking_code, sb.customer_name, a.name AS advisor, CONCAT(b.name,' ',v.name) AS car,
            (SELECT GROUP_CONCAT(CONCAT(sp.part_name,' ×',sp.qty) SEPARATOR ', ') FROM service_parts sp WHERE sp.record_id = r.id) AS parts
       FROM service_records r JOIN vehicle_units u ON u.vin = r.vin JOIN variants v ON v.id = u.variant_id
       JOIN models m ON m.id = v.model_id JOIN brands b ON b.id = m.brand_id
       LEFT JOIN service_bookings sb ON sb.id = r.booking_id LEFT JOIN users a ON a.id = r.advisor_id
      WHERE " . implode(' AND ', $where) . ' ORDER BY r.service_date DESC, r.id DESC LIMIT 300');
$st->execute($p); $rows = $st->fetchAll();

$pageTitle = 'Riwayat Servis';
include BASE_PATH . '/app/views/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><h4 class="mb-0">Riwayat Servis</h4>
  <?php if ($user['role'] === 'service_advisor'): ?><a class="btn btn-gold btn-sm" href="<?= e(url('admin/service_record_form.php')) ?>"><i class="bi bi-plus-lg"></i> Catat Servis Walk-in</a><?php endif; ?></div>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Cari VIN / kode booking / nama" value="<?= e(input('q')) ?>"></div>
  <div class="col-6 col-md-2"><input type="date" name="from" class="form-control" value="<?= e($from) ?>" title="Dari tanggal"></div>
  <div class="col-6 col-md-2"><input type="date" name="to" class="form-control" value="<?= e($to) ?>" title="Sampai tanggal"></div>
  <div class="col-md-2"><select name="warranty" class="form-select"><option value="">Semua</option><option value="1" <?= $wc === '1' ? 'selected' : '' ?>>Klaim garansi</option></select></div>
  <div class="col-md-2 d-grid"><button class="btn btn-outline-light">Filter</button></div>
</form>
<div class="card p-3"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Tanggal</th><th>Kendaraan / VIN</th><th class="text-end">Odometer</th><th>Pekerjaan</th><th>Advisor</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr>
    <td class="text-nowrap"><?= e(date('d M Y', strtotime($r['service_date']))) ?><div class="small text-secondary"><?= e($r['booking_code'] ?? 'Walk-in') ?></div></td>
    <td><?= e($r['car']) ?><div class="small text-secondary font-monospace"><?= e($r['vin']) ?></div><?= $r['customer_name'] ? '<div class="small">' . e($r['customer_name']) . '</div>' : '' ?></td>
    <td class="text-end"><?= number_format((int)$r['odometer'], 0, ',', '.') ?> km</td>
    <td class="small"><?= e($r['work_done']) ?><?= $r['parts'] ? '<div class="text-secondary">Part: ' . e($r['parts']) . '</div>' : '' ?><?= $r['warranty_claim'] ? ' <span class="badge text-bg-info">Klaim Garansi</span>' : '' ?></td>
    <td class="small"><?= e($r['advisor'] ?? '-') ?></td>
    <td class="text-end"><a class="btn btn-sm btn-outline-gold" target="_blank" href="<?= e(url('vin_lookup.php?vin=' . urlencode($r['vin']))) ?>" title="Lihat seperti pelanggan"><i class="bi bi-box-arrow-up-right"></i></a></td>
  </tr><?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-secondary">Belum ada riwayat servis.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php include BASE_PATH . '/app/views/footer.php';
