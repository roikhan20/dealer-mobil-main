<?php
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$vin = strtoupper(preg_replace('/\s/', '', input('vin')));
$unit = null; $records = []; $warranty = null;

if ($vin !== '') {
    if (!preg_match(VIN_REGEX, $vin)) flash('danger', 'Format VIN tidak valid (17 karakter, tanpa I, O, Q).');
    else {
        $st = $pdo->prepare('SELECT u.*, v.name AS variant, b.name AS brand, c.name AS color
                               FROM vehicle_units u JOIN variants v ON v.id=u.variant_id JOIN models m ON m.id=v.model_id
                               JOIN brands b ON b.id=m.brand_id LEFT JOIN variant_colors c ON c.id=u.color_id WHERE u.vin = ?');
        $st->execute([$vin]);
        $unit = $st->fetch();
        if (!$unit) flash('warning', 'VIN tidak ditemukan.');
        else {
            $st = $pdo->prepare('SELECT * FROM service_records WHERE vin = ? ORDER BY service_date DESC, id DESC');
            $st->execute([$vin]);
            $records = $st->fetchAll();
            $ps = $pdo->prepare('SELECT part_name, qty FROM service_parts WHERE record_id = ?');
            foreach ($records as &$r) { $ps->execute([$r['id']]); $r['parts'] = $ps->fetchAll(); }
            unset($r);

            // Status garansi: X tahun sejak serah terima ATAU Y km (mana yang lebih dulu).
            if ($unit['status'] === 'delivered' && $unit['delivered_at']) {
                $until = date('Y-m-d', strtotime($unit['delivered_at'] . ' +' . cfg('app', 'warranty_years') . ' years'));
                $maxKm = $records ? max(array_column($records, 'odometer')) : 0;
                $byDate = $until >= date('Y-m-d'); $byKm = $maxKm <= cfg('app', 'warranty_km');
                $warranty = ['active' => $byDate && $byKm, 'until' => $until, 'km' => $maxKm,
                             'reason' => !$byDate ? 'Masa garansi berakhir' : (!$byKm ? 'Melebihi batas kilometer' : '')];
            }
        }
    }
}
$pageTitle = 'Riwayat Servis (VIN)';
include BASE_PATH . '/app/views/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
  <div class="card p-4 mb-4"><h4><i class="bi bi-search text-gold"></i> Cek Riwayat Servis</h4>
    <form method="get" class="row g-2"><div class="col-md-9"><input name="vin" class="form-control text-uppercase" maxlength="17" placeholder="Masukkan 17 karakter Nomor Rangka (VIN)" value="<?= e($vin) ?>" required></div>
    <div class="col-md-3 d-grid"><button class="btn btn-gold">Cari</button></div></form></div>

<?php if ($unit): ?>
  <div class="card p-4 mb-4">
    <div class="d-flex justify-content-between flex-wrap gap-2"><div><h5 class="mb-0"><?= e($unit['brand'] . ' ' . $unit['variant']) ?></h5>
      <div class="text-secondary small">VIN <?= e($unit['vin']) ?> · Mesin <?= e($unit['engine_no']) ?> · <?= e($unit['color'] ?? '-') ?></div></div>
      <div><?= badge($unit['status']) ?></div></div>
    <hr>
    <?php if ($warranty): ?>
      <div class="alert alert-<?= $warranty['active'] ? 'success' : 'warning' ?> mb-0">
        <strong>Garansi: <?= $warranty['active'] ? 'AKTIF' : 'TIDAK AKTIF' ?></strong> —
        berlaku s.d. <?= e(date('d M Y', strtotime($warranty['until']))) ?> atau <?= number_format(cfg('app', 'warranty_km'), 0, ',', '.') ?> km.
        Km tercatat terakhir: <?= number_format($warranty['km'], 0, ',', '.') ?> km. <?= e($warranty['reason']) ?></div>
    <?php else: ?><div class="alert alert-secondary mb-0">Unit belum diserahterimakan, garansi belum berjalan.</div><?php endif; ?>
  </div>
  <h5 class="mb-3">Riwayat Perawatan</h5>
  <?php if (!$records): ?><div class="alert alert-secondary">Belum ada riwayat servis.</div><?php endif; ?>
  <?php foreach ($records as $r): ?>
    <div class="card p-3 mb-3"><div class="d-flex justify-content-between"><strong><?= e(date('d M Y', strtotime($r['service_date']))) ?></strong>
      <span><span class="badge text-bg-dark border"><?= number_format($r['odometer'], 0, ',', '.') ?> km</span> <?= $r['warranty_claim'] ? '<span class="badge text-bg-info">Klaim Garansi</span>' : '' ?></span></div>
      <div class="small mt-2"><span class="text-secondary">Keluhan:</span> <?= e($r['complaint'] ?: '-') ?></div>
      <div class="small"><span class="text-secondary">Pekerjaan:</span> <?= e($r['work_done']) ?></div>
      <?php if ($r['parts']): ?><div class="small mt-1"><span class="text-secondary">Spare part:</span> <?= e(implode(', ', array_map(fn($p) => $p['part_name'] . ' ×' . $p['qty'], $r['parts']))) ?></div><?php endif; ?></div>
  <?php endforeach; ?>
<?php endif; ?>
</div></div>
<?php include BASE_PATH . '/app/views/footer.php';
