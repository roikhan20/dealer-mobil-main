<?php
/** Pelanggan mengecek status booking servis dengan kode booking + 4 digit terakhir No. HP. */
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$code = strtoupper(trim(input('code')));
$tail = preg_replace('/\D/', '', input('tail'));
$bk = null; $record = null;

if ($code !== '') {
    if (!preg_match('/^SVC-\d{6}-[A-F0-9]{6}$/', $code) || strlen($tail) !== 4) {
        flash('danger', 'Masukkan kode booking (contoh SVC-261009-A1B2C3) dan 4 digit terakhir nomor HP.');
    } else {
        $st = $pdo->prepare("SELECT sb.*, CONCAT(b.name,' ',v.name) AS car
                               FROM service_bookings sb JOIN vehicle_units u ON u.vin = sb.vin JOIN variants v ON v.id = u.variant_id
                               JOIN models m ON m.id = v.model_id JOIN brands b ON b.id = m.brand_id WHERE sb.booking_code = ?");
        $st->execute([$code]);
        $bk = $st->fetch();
        if (!$bk || substr(preg_replace('/\D/', '', $bk['phone']), -4) !== $tail) {
            $bk = null;
            flash('warning', 'Booking tidak ditemukan. Periksa kembali kode dan nomor HP Anda.');
        } elseif ($bk['status'] === 'done') {
            $r = $pdo->prepare('SELECT * FROM service_records WHERE booking_id = ? ORDER BY id DESC LIMIT 1');
            $r->execute([$bk['id']]); $record = $r->fetch() ?: null;
        }
    }
}
$steps = ['booked' => 'Terjadwal', 'in_progress' => 'Sedang dikerjakan', 'done' => 'Selesai'];
$pageTitle = 'Cek Status Booking Servis';
include BASE_PATH . '/app/views/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-7">
  <div class="card p-4 mb-4">
    <h4><i class="bi bi-ticket-detailed text-gold"></i> Cek Status Booking Servis</h4>
    <p class="text-secondary small">Gunakan kode booking yang Anda terima saat mendaftar servis.</p>
    <form method="get" class="row g-2">
      <div class="col-md-6"><label class="form-label small">Kode booking</label><input name="code" class="form-control text-uppercase" placeholder="SVC-261009-A1B2C3" value="<?= e($code) ?>" required></div>
      <div class="col-md-3"><label class="form-label small">4 digit akhir No. HP</label><input name="tail" class="form-control" inputmode="numeric" maxlength="4" pattern="\d{4}" value="<?= e($tail) ?>" required></div>
      <div class="col-md-3 d-grid align-self-end"><button class="btn btn-gold"><i class="bi bi-search"></i> Cek</button></div>
    </form>
  </div>

<?php if ($bk): ?>
  <div class="card p-4">
    <div class="d-flex justify-content-between flex-wrap gap-2">
      <div><h5 class="mb-0"><?= e($bk['car']) ?></h5><div class="small text-secondary">VIN <?= e($bk['vin']) ?> · <?= e($bk['booking_code']) ?></div></div>
      <div><?= badge($bk['status']) ?></div>
    </div>
    <hr>
    <?php if ($bk['status'] === 'cancelled'): ?>
      <div class="alert alert-secondary mb-3">Booking ini dibatalkan. Silakan <a href="<?= e(url('service_booking.php')) ?>">buat booking baru</a>.</div>
    <?php else: $keys = array_keys($steps); $pos = array_search($bk['status'], $keys, true); ?>
      <div class="d-flex justify-content-between text-center mb-3">
        <?php foreach ($steps as $k => $l): $i = array_search($k, $keys, true); ?>
          <div class="flex-fill"><i class="bi <?= $i <= $pos ? 'bi-check-circle-fill text-gold' : 'bi-circle text-secondary' ?> fs-3"></i><div class="small <?= $i <= $pos ? '' : 'text-secondary' ?>"><?= e($l) ?></div></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <table class="table table-sm mb-0">
      <tr><td>Nama</td><td><?= e($bk['customer_name']) ?></td></tr>
      <tr><td>Tanggal servis</td><td><?= e(date('d M Y', strtotime($bk['service_date']))) ?></td></tr>
      <tr><td>Jenis servis</td><td><?= e($bk['service_type']) ?></td></tr>
      <tr><td>Keluhan</td><td><?= e($bk['complaint'] ?: '-') ?></td></tr>
      <?php if ($record): ?>
        <tr><td>Pekerjaan</td><td><?= e($record['work_done']) ?></td></tr>
        <tr><td>Odometer</td><td><?= number_format((int)$record['odometer'], 0, ',', '.') ?> km</td></tr>
      <?php endif; ?>
    </table>
    <a class="btn btn-outline-gold btn-sm mt-3 align-self-start" href="<?= e(url('vin_lookup.php?vin=' . urlencode($bk['vin']))) ?>"><i class="bi bi-clock-history"></i> Lihat seluruh riwayat servis kendaraan</a>
  </div>
<?php endif; ?>
</div></div>
<?php include BASE_PATH . '/app/views/footer.php';
