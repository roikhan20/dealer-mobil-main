<?php
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$types = ['Servis Berkala 1.000 km','Servis Berkala 10.000 km','Servis Berkala 20.000 km','Servis Berkala 40.000 km','Perbaikan / Keluhan','Klaim Garansi'];

if (is_post()) {
    $d = ['vin' => strtoupper(preg_replace('/\s/', '', input('vin'))), 'name' => input('name'),
          'phone' => preg_replace('/[\s-]/', '', input('phone')), 'date' => input('date'),
          'type' => input('type'), 'complaint' => input('complaint')];
    $err = [];
    if (!preg_match(VIN_REGEX, $d['vin'])) $err[] = 'VIN harus 17 karakter (tanpa huruf I, O, Q).';
    if (mb_strlen($d['name']) < 3) $err[] = 'Nama minimal 3 karakter.';
    if (!valid_phone($d['phone'])) $err[] = 'Nomor HP tidak valid.';
    if (!in_array($d['type'], $types, true)) $err[] = 'Jenis servis tidak valid.';
    if (!valid_date($d['date']) || $d['date'] <= date('Y-m-d') || $d['date'] > date('Y-m-d', strtotime('+45 days'))) $err[] = 'Pilih tanggal H+1 s.d. 45 hari ke depan.';
    elseif ((int)date('w', strtotime($d['date'])) === 0) $err[] = 'Bengkel tutup hari Minggu.';
    if (!$err) {
        $c = $pdo->prepare('SELECT COUNT(*) FROM vehicle_units WHERE vin = ?'); $c->execute([$d['vin']]);
        if (!$c->fetchColumn()) $err[] = 'VIN tidak terdaftar pada dealer kami.';
    }
    if ($err) flash('danger', implode(' ', $err));
    else {
        try {
            $code = ServiceBookingService::book($pdo, $d);
            flash('success', "Booking servis berhasil. Kode booking Anda: $code (simpan kode ini untuk cek status).");
            redirect('booking_status.php?' . http_build_query(['code' => $code, 'tail' => substr($d['phone'], -4)]));
        } catch (DomainException $ex) { flash('warning', $ex->getMessage()); }
    }
}
$pageTitle = 'Booking Servis';
include BASE_PATH . '/app/views/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-7"><div class="card p-4">
  <h4><i class="bi bi-wrench-adjustable text-gold"></i> Booking Servis Berkala</h4>
  <p class="text-secondary small">Kuota mekanik dibatasi per hari. Bengkel buka Senin–Sabtu. Sudah booking? <a href="<?= e(url('booking_status.php')) ?>">Cek status booking</a>.</p>
  <form method="post"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Nomor Rangka (VIN)</label><input name="vin" class="form-control text-uppercase" maxlength="17" required value="<?= e(input('vin')) ?>"></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Nama pemilik</label><input name="name" class="form-control" required maxlength="100" value="<?= e(input('name')) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">No. HP</label><input name="phone" class="form-control" required value="<?= e(input('phone')) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Tanggal servis</label><input type="date" id="svc_date" name="date" class="form-control" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>" max="<?= date('Y-m-d', strtotime('+45 days')) ?>" value="<?= e(input('date')) ?>">
        <div class="form-text" id="slot_info"></div></div>
      <div class="col-md-6 mb-3"><label class="form-label">Jenis servis</label><select name="type" class="form-select"><?php foreach ($types as $t): ?><option <?= input('type') === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="mb-3"><label class="form-label">Keluhan (opsional)</label><textarea name="complaint" rows="3" class="form-control" maxlength="500"><?= e(input('complaint')) ?></textarea></div>
    <button class="btn btn-gold w-100">Booking Slot</button>
  </form></div></div></div>
<script>
document.getElementById('svc_date').addEventListener('change', async (e) => {
  const info = document.getElementById('slot_info');
  if (!e.target.value) { info.textContent = ''; return; }
  try {
    const r = await (await fetch('<?= e(url('api/slots.php')) ?>?date=' + encodeURIComponent(e.target.value))).json();
    info.textContent = r.remaining > 0 ? 'Sisa slot: ' + r.remaining : 'Slot penuh, pilih tanggal lain.';
    info.className = 'form-text ' + (r.remaining > 0 ? 'text-success' : 'text-danger');
  } catch (_) { info.textContent = ''; }
});
</script>
<?php include BASE_PATH . '/app/views/footer.php';
